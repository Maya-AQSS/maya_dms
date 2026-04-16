import { render, screen, fireEvent, act } from '@testing-library/react';
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

  it('opens summary panel when a block is clicked', async () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    const blockButton = screen.getByText('Bloque 1');
    
    fireEvent.click(blockButton);
    
    // Summary panel should show "Editar" and "Eliminar" buttons
    expect(screen.getByText('Editar')).toBeTruthy();
    expect(screen.getByText('Eliminar')).toBeTruthy();
  });

  it('enters multi-selection mode on double click', async () => {
    vi.useFakeTimers();
    render(<WizardStep2Blocks {...defaultProps} />);
    const block1 = screen.getByText('Bloque 1');
    
    // Double click
    fireEvent.click(block1);
    fireEvent.click(block1);
    
    act(() => {
      vi.runAllTimers();
    });

    expect(screen.getByText('EDITANDO SELECCIÓN')).toBeTruthy();
    expect(screen.getByText('1 / 1')).toBeTruthy();
    vi.useRealTimers();
  });

  it('toggles selection of all blocks when "Seleccionar todos" is clicked', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    const selectAllBtn = screen.getByText('Seleccionar todos');
    
    fireEvent.click(selectAllBtn);
    
    // After clicking select all, the button text should change
    expect(screen.getByText('Deseleccionar todos')).toBeTruthy();
  });

  it('navigates through multi-selection items', async () => {
    vi.useFakeTimers();
    render(<WizardStep2Blocks {...defaultProps} />);
    
    // Select both blocks via multi-selection
    fireEvent.click(screen.getByText('Seleccionar todos'));
    
    // Double click one to enter multi-mode
    const block1 = screen.getByText('Bloque 1');
    fireEvent.click(block1);
    fireEvent.click(block1);
    
    act(() => {
      vi.runAllTimers();
    });

    expect(screen.getByText('1 / 2')).toBeTruthy();
    
    // Click next arrow (find by text → or role if accessible)
    const nextBtn = screen.getByText('→');
    fireEvent.click(nextBtn);
    
    expect(screen.getByText('2 / 2')).toBeTruthy();
    vi.useRealTimers();
  });
});
