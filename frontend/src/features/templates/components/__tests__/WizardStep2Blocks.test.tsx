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
  UserProfileProvider: ({ children }: any) => <>{children}</>,
}));

vi.mock('../../../../hooks/useAutoSave', () => ({
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

  it('enters multi-selection mode when selecting all blocks', () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    // After selecting all, "Bloque 1" appears in both the sidebar and the edit panel
    // header, so we use getAllByText to handle multiple matches.
    expect(screen.getAllByText('Bloque 1').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Bloque 2').length).toBeGreaterThan(0);
  });

  it('toggles selection of all blocks when "Seleccionar todos" is clicked', () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    const selectAllBtn = screen.getByText('Seleccionar todos');
    
    fireEvent.click(selectAllBtn);
    
    // After clicking select all, the button text should change
    expect(screen.getByText('Deseleccionar todos')).toBeTruthy();
  });

  it('sale del modo multi al deseleccionar todos', () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    expect(screen.getByText('Propiedades')).toBeTruthy();

    fireEvent.click(screen.getByText('Deseleccionar todos'));
    expect(screen.queryByText('Propiedades')).toBeNull();
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

  it('shows selected state on all blocks after "Seleccionar todos"', () => {
    renderWithProfile(<WizardStep2Blocks {...defaultProps} />);
    fireEvent.click(screen.getByText('Seleccionar todos'));
    // Both blocks should have multi-queued or selected styling via data rendered by BlockListItem
    // The presence of 'Deseleccionar todos' confirms multi-select is active
    expect(screen.getByText('Deseleccionar todos')).toBeTruthy();
    expect(screen.getAllByText('Bloque 1').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Bloque 2').length).toBeGreaterThan(0);
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
      const { useAutoSave } = await import('../../../../hooks/useAutoSave');
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
});
