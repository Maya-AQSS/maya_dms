import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { WizardStep4Summary } from '../WizardStep4Summary';

describe('WizardStep4Summary', () => {
  const mockData = {
    template: {
      id: 't1',
      name: 'Test Template',
      description: 'This is a description',
      visibility_level: 'study_type',
      study_type_id: 'st1',
      review_mode: 'ordenada',
    } as any,
    validators: [
      { userId: 'u1', name: 'Val 1' },
      { userId: 'u2', name: 'Val 2' },
    ],
    validationType: 'ordenada' as const,
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

  it('renders correctly', () => {
    render(<WizardStep4Summary {...mockData} />);
    
    expect(screen.getByText('Test Template')).toBeTruthy();
    expect(screen.getByText('This is a description')).toBeTruthy();
  });
});


