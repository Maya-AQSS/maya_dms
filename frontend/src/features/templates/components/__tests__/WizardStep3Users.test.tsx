import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep3Users } from '../WizardStep3Users';
import { searchUsers } from '../../../../api/users';

vi.mock('../../../../api/users');

// Mock dnd-kit for Step 3
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

const mockValidators = [
  { userId: 'u1', name: 'User 1', role: 'Teacher' },
];

const mockSearchResults = [
  { id: 'u2', name: 'User 2', role: 'Staff' },
];

describe('WizardStep3Users', () => {
  const defaultProps = {
    validators: mockValidators,
    onValidatorsChange: vi.fn(),
    validationType: 'libre' as const,
    onValidationTypeChange: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    (searchUsers as any).mockResolvedValue({ data: mockSearchResults });
  });

  it('renders validators correctly', () => {
    render(<WizardStep3Users {...defaultProps} />);
    expect(screen.getByText('User 1')).toBeTruthy();
  });

  it('switches between Libre and Ordenada', () => {
    render(<WizardStep3Users {...defaultProps} />);
    const orderedBtn = screen.getByText(/Validación Ordenada/i);
    
    fireEvent.click(orderedBtn);
    expect(defaultProps.onValidationTypeChange).toHaveBeenCalledWith('ordenada');
  });

  it('removes a validator after confirmation', async () => {
    render(<WizardStep3Users {...defaultProps} />);
    const removeBtn = screen.getByText('✕');
    
    fireEvent.click(removeBtn);
    
    // Should show "¿Eliminar?" confirmation
    expect(screen.getByText('¿Eliminar?')).toBeTruthy();
    
    const confirmYes = screen.getByText('Sí');
    fireEvent.click(confirmYes);
    
    expect(defaultProps.onValidatorsChange).toHaveBeenCalledWith([]);
  });

  it('searches and adds a new validator', async () => {
    render(<WizardStep3Users {...defaultProps} validators={[]} />);
    
    const addBtn = screen.getByText(/\+ Añadir validador/i);
    fireEvent.click(addBtn);
    
    const searchInput = screen.getByPlaceholderText(/Buscar por nombre o correo/i);
    
    await act(async () => {
      fireEvent.change(searchInput, { target: { value: 'User 2' } });
      // Wait for debounce (300ms)
      await new Promise((r) => setTimeout(r, 400));
    });

    await waitFor(() => {
      expect(searchUsers).toHaveBeenCalledWith('User 2');
      expect(screen.getByText('User 2')).toBeTruthy();
    });

    const addButtonInRow = screen.getByText('+ Añadir');
    fireEvent.click(addButtonInRow);

    expect(defaultProps.onValidatorsChange).toHaveBeenCalledWith([
      { userId: 'u2', name: 'User 2', role: 'Staff' }
    ]);
  });
});
