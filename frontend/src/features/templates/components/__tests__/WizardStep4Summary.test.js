import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { WizardStep4Summary } from '../WizardStep4Summary';
import { useTemplateBlocks } from '../../hooks/useTemplateBlocks';
vi.mock('../../hooks/useTemplateBlocks');
describe('WizardStep4Summary', () => {
    const mockTemplate = {
        id: 'tpl-1',
        name: 'Test Template',
        description: 'This is a description',
        visibility_level: 'study_type',
        delivery_deadline: null,
        study_type_id: 'st1',
        study_id: null,
        module_id: null,
        team_id: null,
        created_by: 'u1',
        status: 'draft',
        version: 1,
        review_stages: 1,
        review_mode: 'parallel',
    };
    const mockBlocks = Array.from({ length: 5 }, (_, i) => ({
        id: `b${i}`,
        template_id: 'tpl-1',
        type: 'text',
        title: `Bloque ${i + 1}`,
        description: '',
        default_content: null,
        block_state: 'locked',
        mandatory: true,
        sort_order: i,
    }));
    const validators = [
        { userId: 'v1', name: 'Validator One', role: 'Teacher' },
        { userId: 'v2', name: 'Validator Two', role: 'Staff' },
    ];
    beforeEach(() => {
        vi.clearAllMocks();
        useTemplateBlocks.mockReturnValue({
            blocks: mockBlocks,
            loading: false,
            createBlock: vi.fn(),
            updateBlock: vi.fn(),
            deleteBlock: vi.fn(),
            reorderBlocks: vi.fn(),
        });
    });
    it('renders summary data correctly', () => {
        render(<WizardStep4Summary template={mockTemplate} validators={validators} validationType="ordenada"/>);
        expect(screen.getByText('Test Template')).toBeTruthy();
        expect(screen.getByText('This is a description')).toBeTruthy();
        expect(screen.getByText('Visibilidad')).toBeTruthy();
        expect(screen.getAllByText('Tipo de Estudio').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('st1')).toBeTruthy();
        expect(screen.getByText(/Bloques \(5\)/)).toBeTruthy();
        expect(screen.getByText('Validator One')).toBeTruthy();
        expect(screen.getByText('Validator Two')).toBeTruthy();
        expect(screen.getByText(/ordenada/i)).toBeTruthy();
    });
});
