import type { ReactElement } from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep3Users } from '../WizardStep3Users';
import {
  searchTemplateReviewerCandidates,
  searchDocumentReviewerCandidates,
  fetchMe,
} from '../../../../api/users';
import { UserProfileProvider } from '../../../../features/user-profile';

vi.mock('../../../../api/users', () => ({
  searchTemplateReviewerCandidates: vi.fn(),
  searchDocumentReviewerCandidates: vi.fn(),
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_step3',
      email: null,
      name: null,
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: ['users.search'],
      teams: [],
      source: 'fdw' as const,
    },
  }),
}));

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

function renderWithProfile(ui: ReactElement) {
  return render(<UserProfileProvider>{ui}</UserProfileProvider>);
}

describe('WizardStep3Users', () => {
  const defaultProps = {
    validators: mockValidators,
    onValidatorsChange: vi.fn(),
    validationType: 'libre' as const,
    onValidationTypeChange: vi.fn(),
    documentValidators: [],
    onDocumentValidatorsChange: vi.fn(),
    documentValidationType: 'libre' as const,
    onDocumentValidationTypeChange: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchMe).mockResolvedValue({
      data: {
        id: 'usr_step3',
        email: null,
        name: null,
        department: null,
        study_type_ids: [],
        study_ids: [],
        module_ids: [],
        team_ids: [],
        permissions: ['users.search'],
        teams: [],
        source: 'fdw',
      },
    });
    vi.mocked(searchTemplateReviewerCandidates).mockResolvedValue({ data: mockSearchResults });
    vi.mocked(searchDocumentReviewerCandidates).mockResolvedValue({ data: mockSearchResults });
  });

  it('renders validators correctly', () => {
    renderWithProfile(<WizardStep3Users {...defaultProps} />);
    expect(screen.getByText('User 1')).toBeTruthy();
  });

  it('switches between Libre and Ordenada', () => {
    renderWithProfile(<WizardStep3Users {...defaultProps} />);
    const orderedBtn = screen.getAllByRole('button', { name: 'Ordenada' })[0];
    fireEvent.click(orderedBtn);
    expect(defaultProps.onValidationTypeChange).toHaveBeenCalledWith('ordenada');
  });

  it('removes a validator after confirmation', async () => {
    renderWithProfile(<WizardStep3Users {...defaultProps} />);
    const removeBtn = screen.getByText('✕');
    fireEvent.click(removeBtn);
    expect(screen.getByText(/¿Eliminar a/i)).toBeTruthy();
    const confirmBtn = screen.getByRole('button', { name: 'Eliminar definitivamente' });
    fireEvent.click(confirmBtn);
    expect(defaultProps.onValidatorsChange).toHaveBeenCalledWith([]);
  });

  it('no llama a la API de búsqueda sin permiso users.search', () => {
    vi.mocked(fetchMe).mockResolvedValue({
      data: {
        id: 'usr_step3',
        email: null,
        name: null,
        department: null,
        study_type_ids: [],
        study_ids: [],
        module_ids: [],
        team_ids: [],
        permissions: [],
        teams: [],
        source: 'fdw',
      },
    });
    renderWithProfile(<WizardStep3Users {...defaultProps} validators={[]} />);

    const searchInput = screen.getAllByPlaceholderText('Filtrar usuarios...')[0];
    expect(searchInput).toHaveProperty('disabled', true);
    expect(screen.getAllByText(/users\.search/i).length).toBeGreaterThan(0);
    expect(searchTemplateReviewerCandidates).not.toHaveBeenCalled();
    expect(searchDocumentReviewerCandidates).not.toHaveBeenCalled();
  });

  it('searches and adds a new validator', async () => {
    renderWithProfile(<WizardStep3Users {...defaultProps} validators={[]} />);

    const searchInputs = screen.getAllByPlaceholderText('Filtrar usuarios...');
    fireEvent.change(searchInputs[0], { target: { value: 'User 2' } });

    await waitFor(() => {
      expect(searchTemplateReviewerCandidates).toHaveBeenCalledWith('User 2');
      expect(screen.getAllByText('User 2').length).toBeGreaterThan(0);
    });

    fireEvent.click(screen.getAllByText('User 2')[0].closest('button')!);

    expect(defaultProps.onValidatorsChange).toHaveBeenCalledWith([
      { userId: 'u2', name: 'User 2', role: 'Staff' }
    ]);
  });

  it('no excluye al creador: la búsqueda de candidatos no envía exclude_user_id', async () => {
    renderWithProfile(<WizardStep3Users {...defaultProps} validators={[]} />);

    const searchInputs = screen.getAllByPlaceholderText('Filtrar usuarios...');
    fireEvent.change(searchInputs[0], { target: { value: 'ab' } });
    fireEvent.change(searchInputs[1], { target: { value: 'ab' } });

    await waitFor(() => {
      expect(searchTemplateReviewerCandidates).toHaveBeenCalledWith('ab');
      expect(searchDocumentReviewerCandidates).toHaveBeenCalledWith('ab');
    });
  });
});
