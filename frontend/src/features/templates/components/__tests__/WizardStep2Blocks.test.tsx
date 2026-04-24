import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep2Blocks } from '../WizardStep2Blocks';
import { useTemplateBlocks } from '../../hooks/useTemplateBlocks';

// --- Mocks ---

vi.mock('../../hooks/useTemplateBlocks');

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
  { id: 'b1', title: 'Bloque 1', mandatory: true, block_state: 'locked' },
  { id: 'b2', title: 'Bloque 2', mandatory: false, block_state: 'default' },
];

describe('WizardStep2Blocks', () => {
  const defaultProps = {
    template: { id: 't1', title: 'Template' } as any,
    onBlocksCountChange: vi.fn(),
  };

  const mockUseTemplateBlocks = useTemplateBlocks as any;

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
    render(<WizardStep2Blocks {...defaultProps} />);
    expect(screen.getByText('Bloque 1')).toBeTruthy();
    expect(screen.getByText('Bloque 2')).toBeTruthy();
  });

  it('opens edit panel when a block is clicked', async () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    const blockButton = screen.getByRole('button', { name: /Bloque 1/i });
    fireEvent.click(blockButton);
    await waitFor(() => {
      expect(screen.getByText('Propiedades')).toBeTruthy();
      expect(screen.getByText('Eliminar')).toBeTruthy();
    });
  });

  it('enters multi-selection mode when selecting all blocks', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    expect(screen.getByText(/Edición múltiple/i)).toBeTruthy();
    expect(screen.getByText(/\(1 de 2\)/i)).toBeTruthy();
  });

  it('toggles selection of all blocks when "Seleccionar todos" is clicked', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    const selectAllBtn = screen.getByText('Seleccionar todos');
    
    fireEvent.click(selectAllBtn);
    
    // After clicking select all, the button text should change
    expect(screen.getByText('Deseleccionar todos')).toBeTruthy();
  });

  it('navigates through multi-selection items', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    expect(screen.getByText(/\(1 de 2\)/i)).toBeTruthy();

    const nextBtn = screen.getByRole('button', { name: 'Siguiente →' });
    fireEvent.click(nextBtn);
    expect(screen.getByText(/\(2 de 2\)/i)).toBeTruthy();
  });
});
