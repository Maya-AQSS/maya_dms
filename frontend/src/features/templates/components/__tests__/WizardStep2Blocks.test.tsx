import type { ReactElement } from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep2Blocks } from '../WizardStep2Blocks';
import { useTemplateBlocks } from '../../hooks/useTemplateBlocks';
import { UserProfileProvider } from '../../../../features/user-profile';

// --- Mocks ---

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
      teams: [],
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
}));

vi.mock('../../../hooks/useAutoSave', () => ({
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
  { id: 'b1', title: 'Bloque 1', mandatory: true, block_state: 'locked' },
  { id: 'b2', title: 'Bloque 2', mandatory: false, block_state: 'editable' },
];

function renderWithProfile(ui: ReactElement) {
  return render(<UserProfileProvider>{ui}</UserProfileProvider>);
}

describe('WizardStep2Blocks', () => {
  const defaultProps = {
    template: { id: 't1', title: 'Template', created_by: 'other-user', status: 'draft' } as any,
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
    }, { timeout: 1000 });
  });

  it('shows all blocks after selecting all', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    expect(screen.getByText('Bloque 1')).toBeTruthy();
    expect(screen.getByText('Bloque 2')).toBeTruthy();
  });

  it('toggles button label between "Seleccionar todos" and "Deseleccionar todos"', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    const btn = screen.getByText('Seleccionar todos');
    fireEvent.click(btn);
    expect(screen.getByText('Deseleccionar todos')).toBeTruthy();
    fireEvent.click(screen.getByText('Deseleccionar todos'));
    expect(screen.getByText('Seleccionar todos')).toBeTruthy();
  });

  it('returns to empty panel after deselecting all blocks', () => {
    render(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    fireEvent.click(screen.getByText('Deseleccionar todos'));
    expect(screen.queryByText('Propiedades')).toBeNull();
    expect(screen.queryByText('Eliminar')).toBeNull();
  });
});
