import { cleanup, fireEvent, render, screen, waitFor, act } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
// Inicializa i18next con las traducciones reales (locale por defecto `es`),
// igual que los tests del wizard de plantillas que SÍ pasan. La carga de
// recursos es asíncrona: hay que esperar a `changeLanguage('es')` antes de
// renderizar, si no `t()` devuelve la clave en bruto (p. ej.
// "processes:form.newTitle") y las aserciones por texto en español fallan.
import i18n from '../../../../i18n';
import { ProcessFormModal } from '../ProcessFormModal';
import type { Process } from '../../../../types/processes';
import type { ProcessPayload } from '../../../../api/processes';

const rootProcess: Process = {
  id: 'proc-root-1',
  code: 'PE01',
  name: 'Proceso Educación',
  alias: 'edu',
  icon: null,
  color: null,
  description: null,
  process_parent_id: null,
};

const childProcess: Process = {
  id: 'proc-child-1',
  code: 'PE01.01',
  name: 'Sub-proceso Primaria',
  alias: 'edu-sub',
  icon: null,
  color: null,
  description: null,
  process_parent_id: 'proc-root-1',
};

function renderModal(
  props: Partial<React.ComponentProps<typeof ProcessFormModal>> = {},
) {
  const defaults = {
    open: true,
    onClose: vi.fn(),
    onSave: vi.fn<(payload: ProcessPayload) => Promise<void>>().mockResolvedValue(undefined),
    processes: [] as Process[],
  };
  return render(<ProcessFormModal {...defaults} {...props} />);
}

describe('ProcessFormModal', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('es');
  });

  afterEach(cleanup);

  describe('rendering', () => {
    it('renders nothing when closed', () => {
      const { container } = renderModal({ open: false });
      expect(container.firstChild).toBeNull();
    });

    it('shows create title and button when no initial process', () => {
      renderModal();
      expect(screen.getByText('Nuevo proceso')).toBeTruthy();
      expect(screen.getByText('Crear proceso')).toBeTruthy();
    });

    it('shows edit title and button when initial process provided', () => {
      renderModal({ initial: rootProcess, processes: [rootProcess] });
      expect(screen.getByText('Editar proceso')).toBeTruthy();
      expect(screen.getByText('Guardar cambios')).toBeTruthy();
    });

    it('pre-fills form fields from initial process', () => {
      renderModal({ initial: rootProcess, processes: [rootProcess] });
      const code = screen.getByPlaceholderText('Ej. PE01') as HTMLInputElement;
      const name = screen.getByPlaceholderText('Nombre completo del proceso') as HTMLInputElement;
      const alias = screen.getByPlaceholderText('Etiqueta corta') as HTMLInputElement;
      expect(code.value).toBe('PE01');
      expect(name.value).toBe('Proceso Educación');
      expect(alias.value).toBe('edu');
    });
  });

  describe('parent candidates', () => {
    it('lists only root processes as parent options', () => {
      renderModal({ processes: [rootProcess, childProcess] });
      const select = screen.getByRole('combobox') as HTMLSelectElement;
      const optionTexts = Array.from(select.options).map((o) => o.text);
      expect(optionTexts.some((t) => t.includes('PE01'))).toBe(true);
      expect(optionTexts.some((t) => t.includes('PE01.01'))).toBe(false);
    });

    it('excludes the process being edited from parent options', () => {
      renderModal({ initial: rootProcess, processes: [rootProcess, childProcess] });
      const select = screen.getByRole('combobox') as HTMLSelectElement;
      const optionTexts = Array.from(select.options).map((o) => o.text);
      expect(optionTexts.some((t) => t.includes('PE01'))).toBe(false);
    });
  });

  describe('validation', () => {
    it('shows required errors when submitting an empty form', async () => {
      renderModal();
      await act(async () => {
        fireEvent.click(screen.getByText('Crear proceso'));
      });
      await waitFor(() => {
        expect(screen.getByText('El código es obligatorio.')).toBeTruthy();
        expect(screen.getByText('El nombre es obligatorio.')).toBeTruthy();
        expect(screen.getByText('El alias es obligatorio.')).toBeTruthy();
      });
    });

    it('does not call onSave when validation fails', async () => {
      const onSave = vi.fn();
      renderModal({ onSave });
      await act(async () => {
        fireEvent.click(screen.getByText('Crear proceso'));
      });
      expect(onSave).not.toHaveBeenCalled();
    });
  });

  describe('submission', () => {
    async function fillAndSubmit() {
      fireEvent.change(screen.getByPlaceholderText('Ej. PE01'), {
        target: { value: 'PA01' },
      });
      fireEvent.change(screen.getByPlaceholderText('Nombre completo del proceso'), {
        target: { value: 'Proceso Admin' },
      });
      fireEvent.change(screen.getByPlaceholderText('Etiqueta corta'), {
        target: { value: 'admin' },
      });
      await act(async () => {
        fireEvent.click(screen.getByText('Crear proceso'));
      });
    }

    it('calls onSave with correct payload', async () => {
      const onSave = vi.fn<(payload: ProcessPayload) => Promise<void>>().mockResolvedValue(undefined);
      renderModal({ onSave });
      await fillAndSubmit();
      await waitFor(() => {
        expect(onSave).toHaveBeenCalledWith(
          expect.objectContaining({
            code: 'PA01',
            name: 'Proceso Admin',
            alias: 'admin',
            description: null,
            process_parent_id: null,
          }),
        );
      });
    });

    it('calls onClose after successful save', async () => {
      const onClose = vi.fn();
      renderModal({ onClose });
      await fillAndSubmit();
      await waitFor(() => expect(onClose).toHaveBeenCalled());
    });

    it('shows error message when onSave rejects', async () => {
      const onSave = vi.fn<(payload: ProcessPayload) => Promise<void>>().mockRejectedValue(
        new Error('El servidor rechazó la solicitud'),
      );
      renderModal({ onSave });
      await fillAndSubmit();
      await waitFor(() => {
        expect(screen.getByText('El servidor rechazó la solicitud')).toBeTruthy();
      });
    });

    it('does not call onClose when onSave rejects', async () => {
      const onClose = vi.fn();
      const onSave = vi.fn<(payload: ProcessPayload) => Promise<void>>().mockRejectedValue(new Error('fallo'));
      renderModal({ onSave, onClose });
      await fillAndSubmit();
      await waitFor(() => {
        expect(screen.queryByText('fallo')).toBeTruthy();
      });
      expect(onClose).not.toHaveBeenCalled();
    });
  });

  describe('close button', () => {
    it('calls onClose when cancel button is clicked', () => {
      const onClose = vi.fn();
      renderModal({ onClose });
      fireEvent.click(screen.getByText('Cancelar'));
      expect(onClose).toHaveBeenCalled();
    });

    it('calls onClose when backdrop is clicked', () => {
      const onClose = vi.fn();
      renderModal({ onClose });
      const backdrop = document.querySelector('[aria-hidden="true"]') as HTMLElement;
      fireEvent.click(backdrop);
      expect(onClose).toHaveBeenCalled();
    });
  });
});
