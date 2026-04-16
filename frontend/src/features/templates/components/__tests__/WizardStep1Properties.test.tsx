import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { WizardStep1Properties } from '../WizardStep1Properties';

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
    groupId: '',
    setGroupId: vi.fn(),
    errors: {},
  };

  it('renders correctly with default props', () => {
    render(<WizardStep1Properties {...defaultProps} />);
    expect(screen.getByLabelText(/Nombre de la plantilla/i)).toBeTruthy();
    expect(screen.getByLabelText(/Visibilidad/i)).toBeTruthy();
  });

  it('shows error message when passed via props', () => {
    render(<WizardStep1Properties {...defaultProps} errors={{ name: 'El nombre es obligatorio.' }} />);
    expect(screen.getByText(/El nombre es obligatorio./i)).toBeTruthy();
  });

  it('calls setName on input change', () => {
    render(<WizardStep1Properties {...defaultProps} />);
    const input = screen.getByLabelText(/Nombre de la plantilla/i);
    fireEvent.change(input, { target: { value: 'Nueva Plantilla' } });
    expect(defaultProps.setName).toHaveBeenCalledWith('Nueva Plantilla');
  });

  it('shows academic hierarchy fields only when visibility requires it', () => {
    const { rerender } = render(<WizardStep1Properties {...defaultProps} visibility="personal" />);
    expect(screen.queryByLabelText(/Tipo de estudio/i)).toBeNull();

    rerender(<WizardStep1Properties {...defaultProps} visibility="study_type" />);
    expect(screen.getByLabelText(/Tipo de estudio/i)).toBeTruthy();
  });
});
