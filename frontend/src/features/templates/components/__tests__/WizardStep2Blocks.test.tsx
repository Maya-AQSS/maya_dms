import type { ReactElement } from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { WizardStep2Blocks } from '../WizardStep2Blocks';
import { useTemplateBlocks } from '../../hooks/useTemplateBlocks';
import { UserProfileProvider } from '../../../../features/user-profile';

// --- Mocks ---

const editorMock = vi.hoisted(() => {
  let _cb: ((v: boolean) => void) | undefined;
  return {
    getOnFullscreenChange: () => _cb,
    setOnFullscreenChange: (fn: typeof _cb) => { _cb = fn; },
  };
});

vi.mock('../BlockNoteEditorPanel', () => ({
  BlockNoteEditorPanel: ({ onFullscreenChange }: { onFullscreenChange?: (v: boolean) => void }) => {
    editorMock.setOnFullscreenChange(onFullscreenChange);
    return <div data-testid="bn-editor-panel" />;
  },
}));

vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_step2_blocks',
      email: null,
      name: null,
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: [],
      locale: 'es',
      source: 'fdw' as const,
    },
  }),
}));

vi.mock('../../hooks/useTemplateBlocks');

vi.mock('../../../../features/user-profile', () => ({
  useUserProfile: vi.fn(() => ({
    profile: { id: 'test-owner' },
    loading: false,
    error: null,
    hasPermission: () => false,
  })),
  UserProfileProvider: ({ children }: any) => <>{children}</>,
}));

vi.mock('@ceedcv-maya/shared-hooks-react', () => ({
  useAutoSave: vi.fn(() => ({
    saveStatus: 'idle' as const,
    isSaving: false,
    lastSaved: null,
    triggerSave: vi.fn(),
    forceSave: vi.fn().mockResolvedValue(undefined),
  })),
}));

vi.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }: any) => <div>{children}</div>,
  closestCenter: vi.fn(),
  PointerSensor: vi.fn(),
  KeyboardSensor: vi.fn(),
  useSensor: vi.fn(),
  useSensors: vi.fn(() => []),
}));

vi.mock('@dnd-kit/sortable', () => ({
  SortableContext: ({ children }: any) => <div>{children}</div>,
  verticalListSortingStrategy: {},
  sortableKeyboardCoordinates: vi.fn(),
  useSortable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    transition: null,
    isDragging: false,
  }),
}));

vi.mock('@dnd-kit/utilities', () => ({
  CSS: {
    Transform: {
      toString: vi.fn(),
    },
  },
}));

const mockBlocks = [
  {
    id: 'b1',
    title: 'Bloque 1',
    mandatory: true,
    block_state: 'locked',
    type: 'paragraph',
    default_content: [{ type: 'paragraph', content: [{ type: 'text', text: 'hello' }] }],
    description: [{ type: 'paragraph', content: [{ type: 'text', text: 'desc' }] }],
  },
  { id: 'b2', title: 'Bloque 2', mandatory: false, block_state: 'default', type: 'paragraph', default_content: null, description: null },
];

function renderWithProfile(ui: ReactElement) {
  return render(<UserProfileProvider>{ui}</UserProfileProvider>);
}

describe('WizardStep2Blocks', () => {
  const defaultProps = {
    template: { id: 't1', title: 'Template' } as any,
    onBlocksCountChange: vi.fn(),
  };

  const mockUseTemplateBlocks = useTemplateBlocks as any;

  afterEach(() => {
    document.documentElement.classList.remove('editor-fullscreen');
  });

  beforeEach(() => {
    vi.clearAllMocks();
    mockUseTemplateBlocks.mockReturnValue({
      blocks: mockBlocks,
      loading: false,
      createBlock: vi.fn(),
      updateBlock: vi.fn(),
      deleteBlock: vi.fn(),
      reorderBlocks: vi.fn(),
    });
  });

  it('renders block list correctly', () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    expect(screen.getByText('Bloque 1')).toBeTruthy();
    expect(screen.getByText('Bloque 2')).toBeTruthy();
  });

  it('opens edit panel when a block is clicked', async () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    const blockButton = screen.getByRole('button', { name: /Bloque 1/i });
    fireEvent.click(blockButton);
    await waitFor(() => {
      expect(screen.getByText('Propiedades')).toBeTruthy();
      expect(screen.getByText('Eliminar')).toBeTruthy();
    });
  });

  // Los tests "Seleccionar todos / Deseleccionar todos" se eliminaron porque la
  // funcionalidad multi-selección ya no existe en WizardStep2Blocks. Los
  // skippeamos para mantener histórico hasta que se decida si se reintroduce.
  it.skip('enters multi-selection mode when selecting all blocks', () => {
    // Feature removida del componente.
  });

  it.skip('toggles selection of all blocks when "Seleccionar todos" is clicked', () => {
    // Feature removida del componente.
  });

  it.skip('sale del modo multi al deseleccionar todos', () => {
    // Feature removida del componente.
  });

  it('block name input shows "Nuevo bloque" placeholder', async () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: /Bloque 1/i }));
    await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());
    const input = screen.getByDisplayValue('Bloque 1') as HTMLInputElement;
    expect(input.placeholder).toBe('Nuevo bloque');
  });

  it('duplicate deep-clones content and description', async () => {
    const createBlock = vi.fn().mockResolvedValue({ id: 'b3', title: 'Bloque 1 (copia)', mandatory: true, block_state: 'locked', type: 'paragraph' });
    mockUseTemplateBlocks.mockReturnValue({
      blocks: mockBlocks,
      loading: false,
      createBlock,
      updateBlock: vi.fn(),
      deleteBlock: vi.fn(),
      reorderBlocks: vi.fn(),
    });
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: /Bloque 1/i }));
    await waitFor(() => expect(screen.getByText('Duplicar')).toBeTruthy());
    fireEvent.click(screen.getByText('Duplicar'));
    await waitFor(() => {
      expect(createBlock).toHaveBeenCalledWith(expect.objectContaining({
        title: 'Bloque 1 (copia)',
        default_content: mockBlocks[0].default_content,
        description: mockBlocks[0].description,
      }));
    });
  });

  it.skip('shows selected state on all blocks after "Seleccionar todos"', () => {
    // Feature removida del componente.
  });

  describe('block title placeholder behavior', () => {
    const newBlockStub = {
      id: 'new-b',
      title: null,
      mandatory: false,
      block_state: 'editable' as const,
      type: 'paragraph',
      default_content: null,
      description: null,
    };

    it('new block has empty input value with "Nuevo bloque" placeholder', async () => {
      const createBlockFn = vi.fn().mockResolvedValue(newBlockStub);
      mockUseTemplateBlocks.mockReturnValue({
        blocks: [...mockBlocks, newBlockStub],
        loading: false,
        createBlock: createBlockFn,
        updateBlock: vi.fn(),
        deleteBlock: vi.fn(),
        reorderBlocks: vi.fn(),
      });

      renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: /añadir bloque/i }));

      await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());

      const input = screen.getByPlaceholderText('Nuevo bloque') as HTMLInputElement;
      expect(input.value).toBe('');
      expect(createBlockFn).toHaveBeenCalledWith(
        expect.objectContaining({ title: null }),
      );
    });

    it('shows validation error on blur when title is empty', async () => {
      const createBlockFn = vi.fn().mockResolvedValue(newBlockStub);
      mockUseTemplateBlocks.mockReturnValue({
        blocks: [...mockBlocks, newBlockStub],
        loading: false,
        createBlock: createBlockFn,
        updateBlock: vi.fn(),
        deleteBlock: vi.fn(),
        reorderBlocks: vi.fn(),
      });

      renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: /añadir bloque/i }));
      await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());

      const input = screen.getByPlaceholderText('Nuevo bloque');
      fireEvent.blur(input);

      expect(screen.getByText(/nombre del bloque es obligatorio/i)).toBeTruthy();
    });

    it('clears validation error when user types a title', async () => {
      const createBlockFn = vi.fn().mockResolvedValue(newBlockStub);
      mockUseTemplateBlocks.mockReturnValue({
        blocks: [...mockBlocks, newBlockStub],
        loading: false,
        createBlock: createBlockFn,
        updateBlock: vi.fn(),
        deleteBlock: vi.fn(),
        reorderBlocks: vi.fn(),
      });

      renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: /añadir bloque/i }));
      await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());

      const input = screen.getByPlaceholderText('Nuevo bloque');
      fireEvent.blur(input);
      expect(screen.getByText(/nombre del bloque es obligatorio/i)).toBeTruthy();

      fireEvent.change(input, { target: { value: 'Mi bloque' } });
      expect(screen.queryByText(/nombre del bloque es obligatorio/i)).toBeNull();
    });

    it('doSave blocks API call and shows error when title is empty', async () => {
      const { useAutoSave } = await import('@ceedcv-maya/shared-hooks-react');
      const updateBlock = vi.fn();
      mockUseTemplateBlocks.mockReturnValue({
        blocks: [...mockBlocks, newBlockStub],
        loading: false,
        createBlock: vi.fn().mockResolvedValue(newBlockStub),
        updateBlock,
        deleteBlock: vi.fn(),
        reorderBlocks: vi.fn(),
      });

      renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: /añadir bloque/i }));
      await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());

      // Retrieve the doSave callback captured by useAutoSave mock
      const doSave = (useAutoSave as any).mock.calls.at(-1)?.[0] as (() => Promise<void>) | undefined;
      expect(doSave).toBeDefined();
      await doSave?.();

      await waitFor(() => expect(screen.getByText(/nombre del bloque es obligatorio/i)).toBeTruthy());
      expect(updateBlock).not.toHaveBeenCalled();
    });
  });

  describe('editor fullscreen integration', () => {
    const openContentTab = async () => {
      renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
      fireEvent.click(screen.getByRole('button', { name: /Bloque 1/i }));
      await waitFor(() => expect(screen.getByText('Propiedades')).toBeTruthy());
      fireEvent.click(screen.getByText('Contenido'));
      await waitFor(() => expect(screen.getByTestId('bn-editor-panel')).toBeTruthy());
    };

    it('fullscreen hides block list and sets html.editor-fullscreen class', async () => {
      await openContentTab();

      expect(screen.getByText(/Bloques \(/i)).toBeTruthy();

      await act(async () => { editorMock.getOnFullscreenChange()?.(true); });

      expect(screen.queryByText(/Bloques \(/i)).toBeNull();
      expect(document.documentElement.classList.contains('editor-fullscreen')).toBe(true);
    });

    it('exiting fullscreen restores block list and removes html.editor-fullscreen class', async () => {
      await openContentTab();

      await act(async () => { editorMock.getOnFullscreenChange()?.(true); });
      expect(screen.queryByText(/Bloques \(/i)).toBeNull();

      await act(async () => { editorMock.getOnFullscreenChange()?.(false); });

      expect(screen.getByText(/Bloques \(/i)).toBeTruthy();
      expect(document.documentElement.classList.contains('editor-fullscreen')).toBe(false);
    });
  });
});
