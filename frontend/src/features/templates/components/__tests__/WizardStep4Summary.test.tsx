import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { WizardStep4Summary } from '../WizardStep4Summary';

describe('WizardStep4Summary', () => {
  const mockData = {
    name: 'Test Template',
    description: 'This is a description',
    visibility: 'study_type' as const,
    studyTypeId: 'st1',
    blocksCount: 5,
    validatorsCount: 2,
    validationType: 'ordenada' as const,
    onEditStep: vi.fn(),
  };

  it('renders summary data correctly', () => {
    render(<WizardStep4Summary {...mockData} />);
    
    expect(screen.getByText('Test Template')).toBeTruthy();
    expect(screen.getByText('This is a description')).toBeTruthy();
    expect(screen.getByText('Por tipo de estudio')).toBeTruthy();
    
    // Check counts
    expect(screen.getByText('5 bloques definidos')).toBeTruthy();
    expect(screen.getByText('2 validadores asignados')).toBeTruthy();
    
    // Check validation type
    expect(screen.getByText(/Validación Ordenada/i)).toBeTruthy();
  });

  it('calls onEditStep when edit buttons are clicked', () => {
    const onEditStep = vi.fn();
    render(<WizardStep4Summary {...mockData} onEditStep={onEditStep} />);
    
    // There are several "Editar" buttons in the summary
    const editBtns = screen.getAllByRole('button', { name: /Editar/i });
    
    // Clicking the first one (Properties)
    fireEvent.click(editBtns[0]);
    expect(onEditStep).toHaveBeenCalledWith(1);
  });
});

// Import fireEvent since it was missing in the previous thought block
import { fireEvent } from '@testing-library/react';
