import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeAll, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ToastProvider } from '@ceedcv-maya/shared-ui-react';
import i18n from '../../../../i18n';
import { DocumentWizard } from '../DocumentWizard';
import { UserProfileProvider } from '../../../../features/user-profile';
import {
  fetchDocument,
  fetchDocumentReviewers,
  fetchDocumentReviews,
  fetchDocumentMigrationPayload,
  createDocument,
  updateDocument,
} from '../../../../api/documents';
import { fetchTemplate } from '../../../../api/templates';

// --- Mocks ---

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('../../../../api/documents');
vi.mock('../../../../api/templates');

vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_doc_wizard',
      email: null,
      name: 'Tester',
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: [],
      locale: 'es',
      source: 'fdw' as const,
    },
  }),
  searchOwnerCandidates: vi.fn().mockResolvedValue({ data: [] }),
}));

vi.mock('../../../hierarchy', () => ({
  useHierarchy: () => ({ hierarchy: [], teams: [], loading: false, error: null }),
  HierarchyProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('../../hooks/useDocumentComments', () => ({
  useDocumentCommentsQuery: () => ({ data: { data: [] } }),
  documentCommentsKey: (id: string) => ['documents', id, 'comments'],
}));

vi.mock('../../../../hooks/useProcesses', () => ({
  useProcessesQuery: () => ({ data: { data: [] } }),
}));

vi.mock('../../hooks/useCompletedBlocks', () => ({
  useCompletedBlocks: () => ({
    isCompleted: () => false,
    toggle: vi.fn(),
    completed: new Set<string>(),
  }),
}));

vi.mock('@ceedcv-maya/shared-hooks-react', () => ({
  useAutoSave: () => ({
    saveStatus: 'idle' as const,
    triggerSave: vi.fn(),
    forceSave: vi.fn().mockResolvedValue(undefined),
  }),
  useBackNavigation: () => ({ goBack: vi.fn(), backTarget: null, hasBackState: false }),
  useFlushOnPageLeave: vi.fn(),
}));

vi.mock('@ceedcv-maya/shared-layout-react', () => ({
  useDarkMode: () => ({ isDark: false }),
}));

vi.mock('@ceedcv-maya/shared-ui-react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ceedcv-maya/shared-ui-react')>();
  return {
    ...actual,
    DatePicker: ({
      value,
      onChange,
      ariaLabel,
      disabled,
    }: {
      value: string | null | undefined;
      onChange: (date: string | null) => void;
      ariaLabel?: string;
      disabled?: boolean;
    }) => (
      <input
        type="date"
        aria-label={ariaLabel}
        disabled={disabled}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value ? e.target.value : null)}
      />
    ),
  };
});

vi.mock('../../../templates/components/BlockNoteEditorPanel', () => ({
  BlockNoteEditorPanel: () => <div data-testid="bn-editor-panel" />,
}));

vi.mock('../ContinuousDocumentEditor', () => ({
  ContinuousDocumentEditor: () => <div data-testid="continuous-editor" />,
}));

// --- Factories ---

const makeTemplate = (overrides: Record<string, unknown> = {}) =>
  ({
    id: 't1',
    name: 'Plantilla Demo',
    visibility_level: 'personal',
    process_id: 'proc-1',
    study_type_id: null,
    study_id: null,
    module_id: null,
    team_id: null,
    team: null,
    review_mode: 'parallel',
    document_review_mode: null,
    document_delivery_deadline: '2099-06-01T00:00:00Z',
    ...overrides,
  }) as any;

const makeBlock = (overrides: Record<string, unknown> = {}) =>
  ({
    document_block_id: 'db1',
    template_block_id: 'tb1',
    title: 'Bloque Uno',
    sort_order: 0,
    block_state: 'editable',
    block_type: 'content',
    content: null,
    default_content: null,
    description: null,
    mandatory: true,
    ...overrides,
  }) as any;

const makeDetail = (overrides: Record<string, unknown> = {}) =>
  ({
    id: 'd1',
    title: 'Mi documento',
    status: 'draft',
    delivery_deadline: '2099-01-01T00:00:00Z',
    study_type_id: null,
    study_id: null,
    module_id: null,
    team_id: null,
    template_id: 't1',
    template_version_id: 'tv1',
    visibility_level: 'personal',
    current_version: 1,
    owner_id: 'usr_doc_wizard',
    owner_name: 'Tester',
    submission_changelog: null,
    blocks: [makeBlock()],
    ...overrides,
  }) as any;

describe('DocumentWizard', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('es');
  });

  beforeEach(() => {
    vi.clearAllMocks();
    // El modo de vista del paso Bloques se persiste en localStorage scoped por
    // documentId; limpiar evita que un test contamine el siguiente.
    window.localStorage.clear();
    (fetchTemplate as any).mockResolvedValue(makeTemplate());
    (fetchDocument as any).mockResolvedValue(makeDetail());
    (fetchDocumentReviewers as any).mockResolvedValue({
      kind: 'none',
      review_mode: 'parallel',
      reviewers: [],
    });
    (fetchDocumentReviews as any).mockResolvedValue([]);
    // Sin versión nueva → el paso de migración no se muestra.
    (fetchDocumentMigrationPayload as any).mockRejectedValue(new Error('no new version'));
    (createDocument as any).mockResolvedValue(makeDetail({ id: 'd-new' }));
    (updateDocument as any).mockResolvedValue(makeDetail());
  });

  const renderWizard = async (props: Record<string, unknown> = {}) => {
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    });
    let result: ReturnType<typeof render> | null = null;
    await act(async () => {
      result = render(
        <QueryClientProvider client={queryClient}>
          <ToastProvider>
            <MemoryRouter>
              <UserProfileProvider>
                <DocumentWizard {...props} />
              </UserProfileProvider>
            </MemoryRouter>
          </ToastProvider>
        </QueryClientProvider>,
      );
    });
    return result!;
  };

  it('renders the properties step for a new document (templateId, no documentId)', async () => {
    await renderWizard({ templateId: 't1' });

    await waitFor(() => {
      // El nombre de la plantilla solo aparece tras resolver fetchTemplate.
      expect(screen.getByText('Plantilla Demo')).toBeTruthy();
    });
    expect(screen.getByRole('button', { name: 'Continuar' })).toBeTruthy();
  });

  it('blocks creation when the title is empty (step 1 zod gate)', async () => {
    await renderWizard({ templateId: 't1' });
    await waitFor(() => expect(screen.getByText('Plantilla base')).toBeTruthy());

    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));

    await waitFor(() => {
      expect(screen.getByText('El título es obligatorio.')).toBeTruthy();
    });
    expect(createDocument).not.toHaveBeenCalled();
  });

  it('creates the document when step 1 is valid (happy path)', async () => {
    const { container } = await renderWizard({ templateId: 't1' });
    await waitFor(() => expect(screen.getByText('Plantilla base')).toBeTruthy());

    const titleInput = container.querySelector('#doc-title-input') as HTMLInputElement;
    fireEvent.change(titleInput, { target: { value: 'Mi nuevo documento' } });
    const dateInput = container.querySelector('input[type="date"]') as HTMLInputElement;
    fireEvent.change(dateInput, { target: { value: '2099-12-31' } });

    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));

    await waitFor(() => {
      expect(createDocument).toHaveBeenCalled();
    });
    expect((createDocument as any).mock.calls[0][0]).toEqual(
      expect.objectContaining({ title: 'Mi nuevo documento', template_id: 't1' }),
    );
  });

  it('selects a block when clicked in the blocks-step sidebar', async () => {
    (fetchDocument as any).mockResolvedValue(
      makeDetail({
        blocks: [
          makeBlock(),
          makeBlock({ document_block_id: 'db2', template_block_id: 'tb2', title: 'Bloque Dos', sort_order: 1 }),
        ],
      }),
    );

    await renderWizard({ documentId: 'd1' });
    await waitFor(() => expect(screen.getByText(/Bloques \(2\)/)).toBeTruthy());

    // Bloque Uno está activo al cargar (cabecera + lista = 2). "Bloque Dos" solo en la lista (1).
    expect(screen.getAllByText('Bloque Dos').length).toBe(1);
    fireEvent.click(screen.getByText('Bloque Dos'));

    // Tras seleccionarlo aparece también como cabecera del bloque activo (2).
    await waitFor(() => {
      expect(screen.getAllByText('Bloque Dos').length).toBe(2);
    });
  });

  it('renders the per-block editor for an editable content block', async () => {
    (fetchDocument as any).mockResolvedValue(makeDetail({ blocks: [makeBlock()] }));

    await renderWizard({ documentId: 'd1' });

    await waitFor(() => {
      expect(screen.getByTestId('bn-editor-panel')).toBeTruthy();
    });
  });

  it('switches to the continuous view mode', async () => {
    (fetchDocument as any).mockResolvedValue(makeDetail({ blocks: [makeBlock()] }));

    await renderWizard({ documentId: 'd1' });
    await waitFor(() => expect(screen.getByText(/Bloques \(1\)/)).toBeTruthy());

    fireEvent.click(screen.getByText('Continuo'));

    await waitFor(() => {
      expect(screen.getByTestId('continuous-editor')).toBeTruthy();
    });
  });

  it('loads an existing draft and shows the blocks step with its block list', async () => {
    (fetchDocument as any).mockResolvedValue(
      makeDetail({ blocks: [makeBlock(), makeBlock({ document_block_id: 'db2', template_block_id: 'tb2', title: 'Bloque Dos', sort_order: 1 })] }),
    );

    await renderWizard({ documentId: 'd1' });

    await waitFor(() => {
      expect(screen.getByText(/Bloques \(2\)/)).toBeTruthy();
    });
    // "Bloque Uno" aparece en la lista lateral y como cabecera del bloque activo.
    expect(screen.getAllByText('Bloque Uno').length).toBeGreaterThan(0);
    expect(screen.getByText('Bloque Dos')).toBeTruthy();
  });

  it('shows the not-in-review guard in validate mode for a draft document', async () => {
    (fetchDocument as any).mockResolvedValue(makeDetail({ status: 'draft' }));

    await renderWizard({ documentId: 'd1', mode: 'validate' });

    await waitFor(() => {
      expect(screen.getByText(/no está en revisión/i)).toBeTruthy();
    });
  });

  it('shows a load error when the document cannot be fetched', async () => {
    (fetchDocument as any).mockRejectedValue(new Error('Boom al cargar'));

    await renderWizard({ documentId: 'd1' });

    await waitFor(() => {
      expect(screen.getByText('Boom al cargar')).toBeTruthy();
    });
  });

  it('renders the summary step in validate mode for an in-review document', async () => {
    (fetchDocument as any).mockResolvedValue(makeDetail({ status: 'in_review' }));
    (fetchDocumentReviews as any).mockResolvedValue([
      { id: 'rev1', status: 'pending', reviewer_id: 'usr_doc_wizard', stage: 1 },
    ]);

    await renderWizard({ documentId: 'd1', mode: 'validate' });

    await waitFor(() => {
      // Texto del encabezado del resumen en modo validación + acción de previsualizar.
      expect(screen.getByText(/Revisa el resumen del documento/i)).toBeTruthy();
    });
    expect(screen.getByRole('button', { name: 'PREVISUALIZAR' })).toBeTruthy();
  });
});
