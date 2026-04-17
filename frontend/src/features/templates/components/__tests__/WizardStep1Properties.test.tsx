import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep1Properties } from '../WizardStep1Properties';

vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: { teams: [] },
  }),
}));

vi.mock('../../../../features/hierarchy', () => ({
  useHierarchy: () => ({ hierarchy: [], loading: false, error: null }),
  HierarchyProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

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
    render(<WizardStep1Properties {...defaultProps} />);
    expect(screen.getAllByPlaceholderText(/Acta de Evaluación Final/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Visibilidad/i)).toBeTruthy();
  });

  it('shows error message when passed via props', () => {
    render(<WizardStep1Properties {...defaultProps} errors={{ name: 'El nombre es obligatorio.' }} />);
    expect(screen.getByText(/El nombre es obligatorio./i)).toBeTruthy();
  });

  it('calls setName on input change', () => {
    render(<WizardStep1Properties {...defaultProps} />);
    const [input] = screen.getAllByPlaceholderText(/Acta de Evaluación Final/i);
    fireEvent.change(input, { target: { value: 'Nueva Plantilla' } });
    expect(defaultProps.setName).toHaveBeenCalledWith('Nueva Plantilla');
  });

  it('shows academic hierarchy fields only when visibility requires it', () => {
    const { rerender } = render(<WizardStep1Properties {...defaultProps} visibility="personal" />);
    expect(screen.queryByText('— Seleccionar —')).toBeNull();

    rerender(<WizardStep1Properties {...defaultProps} visibility="study_type" />);
    expect(screen.getAllByText('— Seleccionar —').length).toBeGreaterThan(0);
  });
});
