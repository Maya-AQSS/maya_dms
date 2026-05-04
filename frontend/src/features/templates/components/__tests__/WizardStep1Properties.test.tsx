import type { ReactElement } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep1Properties } from '../WizardStep1Properties';
import { UserProfileProvider } from '../../../../features/user-profile';

vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_test_fixture',
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

import { useHierarchy } from '../../../../features/hierarchy';

vi.mock('../../../../features/hierarchy', () => ({
  useHierarchy: vi.fn(() => ({ hierarchy: [], loading: false, error: null })),
  HierarchyProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

function renderWithProfile(ui: ReactElement) {
  return render(<UserProfileProvider>{ui}</UserProfileProvider>);
}

describe('WizardStep1Properties', () => {
  const defaultProps = {
    name: '',
    setName: vi.fn(),
    description: '',
    setDescription: vi.fn(),
    visibility: 'personal' as const,
    setVisibility: vi.fn(),
    deliveryDeadline: '',
    setDeliveryDeadline: vi.fn(),
    studyTypeId: '',
    setStudyTypeId: vi.fn(),
    studyId: '',
    setStudyId: vi.fn(),
    moduleId: '',
    setModuleId: vi.fn(),
    teamId: '',
    setTeamId: vi.fn(),
    errors: {},
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders correctly with default props', () => {
    renderWithProfile(<WizardStep1Properties {...defaultProps} />);
    expect(screen.getAllByPlaceholderText(/Acta de Evaluación Final/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Visibilidad/i)).toBeTruthy();
  });

  it('shows error message when passed via props', () => {
    renderWithProfile(<WizardStep1Properties {...defaultProps} errors={{ name: 'El nombre es obligatorio.' }} />);
    expect(screen.getByText(/El nombre es obligatorio./i)).toBeTruthy();
  });

  it('calls setName on input change', () => {
    renderWithProfile(<WizardStep1Properties {...defaultProps} />);
    const [input] = screen.getAllByPlaceholderText(/Acta de Evaluación Final/i);
    fireEvent.change(input, { target: { value: 'Nueva Plantilla' } });
    expect(defaultProps.setName).toHaveBeenCalledWith('Nueva Plantilla');
  });

  it('shows academic hierarchy fields only when visibility requires it', () => {
    const { rerender } = renderWithProfile(<WizardStep1Properties {...defaultProps} visibility="personal" />);
    expect(screen.queryByText('— Seleccionar —')).toBeNull();

    rerender(
      <UserProfileProvider>
        <WizardStep1Properties {...defaultProps} visibility="study_type" />
      </UserProfileProvider>,
    );
    expect(screen.getByText('No tienes tipos de estudio asignados, contacta con un administrador')).toBeTruthy();
  });

  it('auto-selects study type if only one is available', () => {
    vi.mocked(useHierarchy).mockReturnValue({
      hierarchy: [{ id: 'ST_1', name: 'Único Tipo', studies: [] }],
      loading: false,
      error: null,
    } as any);

    renderWithProfile(<WizardStep1Properties {...defaultProps} visibility="study_type" />);
    expect(defaultProps.setStudyTypeId).toHaveBeenCalledWith('ST_1');
  });
});
