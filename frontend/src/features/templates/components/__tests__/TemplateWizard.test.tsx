import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TemplateWizard } from '../TemplateWizard';
import { createTemplate } from '../../../../api/templates';
import { fetchBlocks } from '../../../../api/blocks';
import { MemoryRouter } from 'react-router-dom';

// --- Mocks ---

vi.mock('../../../../api/templates');
vi.mock('../../../../api/blocks');
vi.mock('../../../../api/users');

// Mock dnd-kit globally for the wizard
vi.mock('@dnd-kit/core', () => ({
  DndContext: ({ children }: any) => <div>{children}</div>,
  closestCenter: vi.fn(),
  PointerSensor: vi.fn(),
  useSensor: vi.fn(),
  useSensors: vi.fn(() => []),
}));

vi.mock('@dnd-kit/sortable', () => ({
  SortableContext: ({ children }: any) => <div>{children}</div>,
  verticalListSortingStrategy: {},
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
  beforeEach(() => {
    vi.clearAllMocks();
    (fetchBlocks as any).mockResolvedValue({ data: [] });
  });

  const renderWizard = (props = {}) => {
    return render(
      <MemoryRouter>
        <TemplateWizard {...props} />
      </MemoryRouter>
    );
  };

  it('completes full "Create" flow from Step 1 to Step 4', async () => {
    const mockNewTemplate = { id: 't123', name: 'New Template', visibility_level: 'personal' };
    (createTemplate as any).mockResolvedValue({ data: mockNewTemplate });
    (fetchBlocks as any).mockResolvedValue({ data: [{ id: 'b1', title: 'Block 1', mandatory: true, block_state: 'locked' }] });

    renderWizard();

    // Step 1: Properties
    expect(screen.getByText('Nueva plantilla')).toBeTruthy();
    
    const nameInput = screen.getByLabelText(/Nombre de la plantilla/i);
    fireEvent.change(nameInput, { target: { value: 'New Template' } });
    
    const continueBtn = screen.getByText(/Guardar y continuar/i);
    fireEvent.click(continueBtn);

    await waitFor(() => {
      expect(createTemplate).toHaveBeenCalled();
      expect(screen.getByText('Editando «New Template»')).toBeTruthy();
      expect(screen.getByText('BLOQUES (1)')).toBeTruthy(); // Transitioned to Step 2
    });

    // Step 2: Blocks
    // We already have 1 block from mocked fetchBlocks
    fireEvent.click(screen.getByText(/Continuar/i));

    await waitFor(() => {
      expect(screen.getByText('Validadores asignados (0)')).toBeTruthy(); // Transitioned to Step 3
    });

    // Step 3: Users
    fireEvent.click(screen.getByText(/Continuar/i));

    await waitFor(() => {
      expect(screen.getByText('Revisión final')).toBeTruthy(); // Transitioned to Step 4 (Resumen)
    });

    // Step 4: Summary -> Finish
    fireEvent.click(screen.getByText(/Publicar plantilla/i));
    
    expect(mockNavigate).toHaveBeenCalledWith('/templates');
  });

  it('handles Step 1 validation errors', async () => {
    renderWizard();
    
    const continueBtn = screen.getByText(/Guardar y continuar/i);
    fireEvent.click(continueBtn);

    expect(screen.getByText('El nombre es obligatorio.')).toBeTruthy();
    expect(createTemplate).not.toHaveBeenCalled();
  });

  it('allows moving back to previous steps', async () => {
    const mockTemplate = { id: 't1', name: 'Existing', visibility_level: 'personal' };
    (fetchBlocks as any).mockResolvedValue({ data: [{ id: 'b1', title: 'B1' }] });

    renderWizard({ template: mockTemplate });
    
    // We start at Step 1, but let's go to Step 2
    fireEvent.change(screen.getByLabelText(/Nombre/i), { target: { value: 'Modified' } });
    fireEvent.click(screen.getByText(/Guardar y continuar/i));

    await waitFor(() => {
      expect(screen.getByText('BLOQUES (1)')).toBeTruthy();
    });

    // Now go back
    fireEvent.click(screen.getByText(/Volver a Propiedades/i));
    
    expect(screen.getByLabelText(/Nombre de la plantilla/i)).toBeTruthy();
  });

  it('shows leave guard when dirty', async () => {
    renderWizard();
    
    const nameInput = screen.getByLabelText(/Nombre de la plantilla/i);
    fireEvent.change(nameInput, { target: { value: 'Some change' } });

    // Try to go back to templates list via back arrow
    fireEvent.click(screen.getByLabelText('Volver'));

    expect(screen.getByText(/Tienes cambios sin guardar/i)).toBeTruthy();
  });
});
