import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TemplateWizard } from '../TemplateWizard';
import { createTemplate, updateTemplate, syncTemplateValidators, publishTemplate } from '../../../../api/templates';
import { fetchBlocks } from '../../../../api/blocks';
import { fetchMe } from '../../../../api/users';
import { MemoryRouter } from 'react-router-dom';
import { UserProfileProvider } from '../../../../features/user-profile';

// --- Mocks ---

vi.mock('@maya/shared-ui-react', async () => {
  const actual = await vi.importActual<typeof import('@maya/shared-ui-react')>('@maya/shared-ui-react');
  return {
    ...actual,
    DatePicker: ({ onChange, ariaLabel, value }: { onChange: (d: string | null) => void; ariaLabel?: string; value?: string | null }) => (
      <input
        type="date"
        aria-label={ariaLabel}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value || null)}
      />
    ),
  };
});

vi.mock('../../../../api/templates');
vi.mock('../../../../api/blocks');
vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_wizard_test',
      email: null,
      name: null,
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: ['templates.create', 'templates.read', 'users.search'],
      teams: [],
      source: 'fdw' as const,
    },
  }),
  searchTemplateReviewerCandidates: vi.fn().mockResolvedValue({ data: [] }),
  searchDocumentReviewerCandidates: vi.fn().mockResolvedValue({ data: [] }),
}));
vi.mock('../../../../features/hierarchy', () => ({
  useHierarchy: () => ({ hierarchy: [], loading: false, error: null }),
  HierarchyProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@maya/shared-ui-react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@maya/shared-ui-react')>();
  return {
    ...actual,
    DatePicker: ({
      value,
      onChange,
      ariaLabel,
      disabled,
    }: {
      value: string | null | undefined;
      onChange: (date: string | null) => void;
      ariaLabel?: string;
      disabled?: boolean;
    }) => (
      <input
        type="date"
        aria-label={ariaLabel}
        disabled={disabled}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value ? e.target.value : null)}
      />
    ),
  };
});

// Mock dnd-kit globally for the wizard
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
  arrayMove: (arr: any) => arr,
  useSortable: () => ({
    attributes: {},
    listeners: {},
    setNodeRef: vi.fn(),
    transform: null,
    transition: null,
    isDragging: false,
  }),
}));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

describe('TemplateWizard Integration', () => {
  const fullTemplate = (overrides: Partial<Record<string, unknown>> = {}) => ({
    id: 't1',
    name: 'Existing',
    description: null,
    visibility_level: 'personal',
    delivery_deadline: '2099-01-01T00:00:00Z',
    study_type_id: null,
    study_id: null,
    module_id: null,
    team_id: null,
    created_by: 'u1',
    status: 'draft',
    version: 1,
    review_stages: 1,
    review_mode: 'parallel',
    ...overrides,
  });

  beforeEach(() => {
    vi.clearAllMocks();
    (fetchBlocks as any).mockResolvedValue({ data: [] });
    (createTemplate as any).mockImplementation(async (payload: { name?: string }) => ({
      data: fullTemplate({ id: 't123', name: payload.name ?? 'Existing' }),
    }));
    (updateTemplate as any).mockImplementation(async (_id: string, payload: { name?: string }) => ({
      data: fullTemplate({ name: payload.name ?? 'Existing' }),
    }));
    (syncTemplateValidators as any).mockResolvedValue({ data: [] });
    (publishTemplate as any).mockResolvedValue({ data: { success: true } });
  });

  const renderWizard = (props = {}) => {
    return render(
      <MemoryRouter>
        <UserProfileProvider>
          <TemplateWizard {...props} />
        </UserProfileProvider>
      </MemoryRouter>,
    );
  };

  it('completes full "Create" flow from Step 1 to Step 4', async () => {
    const mockNewTemplate = fullTemplate({ id: 't123', name: 'New Template' });
    (createTemplate as any).mockResolvedValue({ data: mockNewTemplate });
    (fetchBlocks as any).mockResolvedValue({ data: [{ id: 'b1', title: 'Block 1', mandatory: true, block_state: 'locked' }] });

    renderWizard({ processId: 'test-process' });

    // Step 1: Properties
    expect(screen.getByText('Nueva plantilla')).toBeTruthy();
    
    const nameInput = screen.getAllByPlaceholderText(/Acta de Evaluación Final/i)[0];
    fireEvent.change(nameInput, { target: { value: 'New Template' } });

    const deadlineInput = screen.getByLabelText(/Plazo de entrega/i);
    fireEvent.change(deadlineInput, { target: { value: '2099-01-01' } });

    await waitFor(() => {
      expect((deadlineInput as HTMLInputElement).value).toBe('2099-01-01');
    });

    const continueBtn = screen.getByRole('button', { name: /Guardar y continuar →/ });
    fireEvent.click(continueBtn);

    await waitFor(() => {
      expect(createTemplate).toHaveBeenCalled();
      expect(screen.getByText('Editar plantilla')).toBeTruthy();
      expect(screen.getByText(/Bloques \(/i)).toBeTruthy(); // Transitioned to Step 2
    }, { timeout: 10000 });

    // Step 2: Blocks
    // We already have 1 block from mocked fetchBlocks
    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /Guardar y continuar →/ }) as HTMLButtonElement;
      expect(btn.disabled).toBe(false);
    }, { timeout: 10000 });
    fireEvent.click(screen.getByRole('button', { name: /Guardar y continuar →/ }));

    await waitFor(() => {
      expect(screen.getAllByText(/validadores.*\(0\)/i).length).toBeGreaterThan(0); // Paso 3
    }, { timeout: 10000 });

    // Step 3: Users
    fireEvent.click(screen.getByRole('button', { name: /Guardar y continuar →/ }));

    await waitFor(() => {
      expect(screen.getByText('Revisión final')).toBeTruthy(); // Transitioned to Step 4 (Resumen)
    }, { timeout: 10000 });

    // Step 4: Summary -> Finish (esperar a que la bandeja de bloques del resumen cargue; sin esto
    // «Publicar plantilla» sigue disabled por blocksLoading/blocksCount y el clic no navega)
    await waitFor(() => {
      const publishBtn = screen.getByRole('button', { name: /Publicar plantilla/ }) as HTMLButtonElement;
      expect(publishBtn.disabled).toBe(false);
    }, { timeout: 10000 });
    fireEvent.click(screen.getByRole('button', { name: /Publicar plantilla/ }));

    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/procesos');
    }, { timeout: 10000 });
  }, 15000);

  it('handles Step 1 validation errors', async () => {
    renderWizard();
    
    const continueBtn = screen.getByRole('button', { name: /Guardar y continuar →/ });
    fireEvent.click(continueBtn);

    expect(screen.getByText('El nombre es obligatorio.')).toBeTruthy();
    expect(createTemplate).not.toHaveBeenCalled();
  });

  it('allows moving back to previous steps', async () => {
    const mockTemplate = fullTemplate();
    (fetchBlocks as any).mockResolvedValue({
      data: [{
        id: 'b1',
        template_id: 't1',
        type: 'paragraph',
        title: 'B1',
        default_content: null,
        block_state: 'locked',
        mandatory: true,
        sort_order: 0,
      }],
    });

    renderWizard({ template: mockTemplate });
    
    // We start at Step 1, but let's go to Step 2
    fireEvent.change(screen.getAllByPlaceholderText(/Acta de Evaluación Final/i)[0], { target: { value: 'Modified' } });
    fireEvent.click(screen.getByRole('button', { name: /Guardar y continuar →/ }));

    await waitFor(() => {
      expect(screen.getByText(/Bloques \(/i)).toBeTruthy();
    }, { timeout: 10000 });

    // Now go back using the stepper
    fireEvent.click(screen.getByRole('button', { name: /Propiedades/i }));
    
    expect(screen.getAllByPlaceholderText(/Acta de Evaluación Final/i)[0]).toBeTruthy();
  });

  it('shows leave guard when dirty', async () => {
    renderWizard();
    
    const nameInput = screen.getAllByPlaceholderText(/Acta de Evaluación Final/i)[0];
    fireEvent.change(nameInput, { target: { value: 'Some change' } });

    // Try to go back to templates list via back arrow
    fireEvent.click(screen.getByLabelText('Volver'));

    expect(screen.getByText(/Tienes cambios sin guardar/i)).toBeTruthy();
  });
});
