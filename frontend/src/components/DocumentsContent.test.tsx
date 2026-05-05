import type { ReactElement } from 'react';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentsContent } from './DocumentsContent';
import { UserProfileProvider } from '../features/user-profile';
import { fetchMe } from '../api/users';

vi.mock('../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_docs_test',
      email: null,
      name: null,
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: ['documents.create', 'documents.read'],
      teams: [],
      source: 'fdw' as const,
    },
  }),
}));

const mockUseDocuments = vi.fn();
const mockUseFilteredDocuments = vi.fn();
const mockUseHierarchy = vi.fn();
const mockFetchDocumentCreationOptions = vi.fn();
const mockCreateDocumentFromModule = vi.fn();
const mockNavigate = vi.fn();

vi.mock('./CascadeFilters', () => ({
  CascadeFilters: ({ onFilterChange }: { onFilterChange: (filters: { studyTypeId: string; studyId: string; moduleId: string }) => void }) => (
    <button onClick={() => onFilterChange({ studyTypeId: 'st1', studyId: 's1', moduleId: 'm1' })}>
      seleccionar-modulo
    </button>
  ),
}));

vi.mock('../features/documents', () => ({
  useDocuments: () => mockUseDocuments(),
  useFilteredDocuments: (...args: unknown[]) => mockUseFilteredDocuments(...args),
}));

vi.mock('../features/hierarchy', () => ({
  useHierarchy: () => mockUseHierarchy(),
}));

vi.mock('../api/documents', () => ({
  fetchDocumentCreationOptions: (...args: unknown[]) => mockFetchDocumentCreationOptions(...args),
  createDocumentFromModule: (...args: unknown[]) => mockCreateDocumentFromModule(...args),
}));

const mockFetchTemplateVersion = vi.fn();

vi.mock('../api/templates', () => ({
  fetchTemplateVersion: (...args: unknown[]) => mockFetchTemplateVersion(...args),
}));

vi.mock('../features/templates/components/BlockContentHtml', () => ({
  BlockContentHtml: () => <div data-testid="mock-block-html" />,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

function renderWithProfile(ui: ReactElement) {
  return render(
    <MemoryRouter>
      <UserProfileProvider>{ui}</UserProfileProvider>
    </MemoryRouter>,
  );
}

/** Evita clics mientras `fetchMe` del perfil sigue en curso (botón con title de carga). */
async function waitForUserProfileReady() {
  await waitFor(() => {
    const btn = screen.getByRole('button', { name: 'Nueva Programación' });
    expect(btn.getAttribute('title')).not.toBe('Cargando perfil de usuario…');
  });
}

/** Abre el panel de filtros si hace falta (DataTable cachea `filtersOpen` en memoria por `filtersStorageKey`). */
async function openFiltersAndSelectModule() {
  const filtrosBtn = screen.getAllByRole('button', { name: /Filtros/i })[0]!;
  if (filtrosBtn.getAttribute('aria-expanded') !== 'true') {
    fireEvent.click(filtrosBtn);
  }
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'seleccionar-modulo' })).toBeTruthy();
  });
  fireEvent.click(screen.getByRole('button', { name: 'seleccionar-modulo' }));
}

const baseDocument = {
  id: 'd-1',
  template_id: 't-1',
  template_version_id: null,
  title: 'Doc',
  study_type_id: null,
  study_id: 's1',
  module_id: 'm1',
  created_by: 'u1',
  owner_id: 'u1',
  status: 'draft' as const,
  current_version: 1,
  submitted_at: null,
  published_at: null,
};

describe('DocumentsContent creation flow', () => {
  afterEach(() => {
    cleanup();
  });

  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchMe).mockResolvedValue({
      data: {
        id: 'usr_docs_test',
        email: null,
        name: null,
        department: null,
        study_type_ids: [],
        study_ids: [],
        module_ids: [],
        team_ids: [],
        permissions: ['documents.create', 'documents.read'],
        teams: [],
        source: 'fdw',
      },
    });
    mockUseDocuments.mockReturnValue({
      documents: [baseDocument],
      loading: false,
      error: null,
      reload: vi.fn().mockResolvedValue(undefined),
    });
    mockUseFilteredDocuments.mockImplementation((docs: unknown[]) => docs);
    mockUseHierarchy.mockReturnValue({ hierarchy: [], loading: false, error: null });
  });

  it('deshabilita nueva programación sin permiso documents.create', async () => {
    vi.mocked(fetchMe).mockResolvedValue({
      data: {
        id: 'usr_docs_test',
        email: null,
        name: null,
        department: null,
        study_type_ids: [],
        study_ids: [],
        module_ids: [],
        team_ids: [],
        permissions: ['documents.read'],
        teams: [],
        source: 'fdw',
      },
    });
    renderWithProfile(<DocumentsContent />);

    await waitFor(() => {
      expect(screen.getByText(/documents\.create/i)).toBeTruthy();
    });
    const button = screen.getByRole('button', { name: 'Nueva Programación' });
    expect(button).toHaveProperty('disabled', true);
  });

  it('deshabilita nueva programación sin módulo seleccionado', async () => {
    renderWithProfile(<DocumentsContent />);

    await waitFor(() => {
      expect(
        screen.getByText('Selecciona un módulo para crear una nueva programación.'),
      ).toBeTruthy();
    });
    const button = screen.getByRole('button', { name: 'Nueva Programación' });
    expect(button).toHaveProperty('disabled', true);
  });

  it('muestra estado none cuando no hay plantillas disponibles', async () => {
    mockFetchDocumentCreationOptions.mockResolvedValue({
      can_create: false,
      mode: 'none',
      message: 'No hay plantillas publicadas disponibles para este módulo.',
      options: [],
    });

    renderWithProfile(<DocumentsContent />);
    const filtrosBtn = screen.getByRole('button', { name: /Filtros/i });
    if (filtrosBtn.getAttribute('aria-expanded') !== 'true') {
      fireEvent.click(filtrosBtn);
    }
    fireEvent.click(await screen.findByRole('button', { name: 'seleccionar-modulo' }));

    await waitFor(() =>
      expect(mockFetchDocumentCreationOptions).toHaveBeenCalledWith('m1'),
    );
    expect(screen.getByRole('button', { name: 'Nueva Programación' })).toHaveProperty('disabled', true);
    expect(
      screen.getByText('No hay plantillas publicadas disponibles para este módulo.'),
    ).toBeTruthy();
  });

  it('crea automáticamente y navega al editor con modo auto', async () => {
    const reload = vi.fn().mockResolvedValue(undefined);
    mockUseDocuments.mockReturnValue({
      documents: [baseDocument],
      loading: false,
      error: null,
      reload,
    });
    mockFetchDocumentCreationOptions.mockResolvedValue({
      can_create: true,
      mode: 'auto',
      message: null,
      options: [
        {
          template_id: 'tpl-1',
          template_version_id: 'ver-1',
          name: 'Plantilla única',
          description: 'Desc',
        },
      ],
    });
    mockCreateDocumentFromModule.mockResolvedValue({ ...baseDocument, id: 'doc-new' });

    renderWithProfile(<DocumentsContent />);
    const filtrosBtn = screen.getByRole('button', { name: /Filtros/i });
    if (filtrosBtn.getAttribute('aria-expanded') !== 'true') {
      fireEvent.click(filtrosBtn);
    }
    fireEvent.click(await screen.findByRole('button', { name: 'seleccionar-modulo' }));

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Nueva Programación' })).toHaveProperty('disabled', false),
    );
    fireEvent.click(screen.getByRole('button', { name: 'Nueva Programación' }));

    await waitFor(() =>
      expect(mockNavigate).toHaveBeenCalledWith('/nueva-programacion/tpl-1/wizard', {
        state: { moduleId: 'm1' },
      }),
    );
  });

  it('muestra listado, previsualiza y crea con la plantilla elegida en modo select', async () => {
    const reload = vi.fn().mockResolvedValue(undefined);
    mockUseDocuments.mockReturnValue({
      documents: [baseDocument],
      loading: false,
      error: null,
      reload,
    });
    mockFetchDocumentCreationOptions.mockResolvedValue({
      can_create: true,
      mode: 'select',
      message: null,
      options: [
        {
          template_id: 'tpl-1',
          template_version_id: 'ver-1',
          name: 'Plantilla A',
          description: 'Desc A',
        },
        {
          template_id: 'tpl-2',
          template_version_id: 'ver-2',
          name: 'Plantilla B',
          description: 'Desc B',
        },
      ],
    });
    mockFetchTemplateVersion.mockResolvedValue({
      id: 'ver-2',
      template_id: 'tpl-2',
      version_number: 1,
      blocks_snapshot: [
        {
          id: 'blk-1',
          type: 'paragraph',
          title: 'Bloque demo',
          default_content: [],
          sort_order: 0,
          mandatory: true,
          block_state: 'editable',
        },
      ],
      changelog: 'Seed',
      published_by: null,
      published_at: null,
    });
    mockCreateDocumentFromModule.mockResolvedValue({ ...baseDocument, id: 'doc-sel' });

    renderWithProfile(<DocumentsContent />);
    const filtrosBtn = screen.getByRole('button', { name: /Filtros/i });
    if (filtrosBtn.getAttribute('aria-expanded') !== 'true') {
      fireEvent.click(filtrosBtn);
    }
    fireEvent.click(await screen.findByRole('button', { name: 'seleccionar-modulo' }));

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Nueva Programación' })).toHaveProperty('disabled', false),
    );
    fireEvent.click(screen.getByRole('button', { name: 'Nueva Programación' }));

    await waitFor(() =>
      expect(mockNavigate).toHaveBeenCalledWith('/nueva-programacion', {
        state: { moduleId: 'm1' },
      }),
    );
  });

  it('vuelve al listado desde la previsualización con Elegir otra plantilla', async () => {
    mockFetchDocumentCreationOptions.mockResolvedValue({
      can_create: true,
      mode: 'select',
      message: null,
      options: [
        {
          template_id: 'tpl-1',
          template_version_id: 'ver-1',
          name: 'Plantilla A',
          description: null,
        },
        {
          template_id: 'tpl-2',
          template_version_id: 'ver-2',
          name: 'Plantilla B',
          description: null,
        },
      ],
    });
    mockFetchTemplateVersion.mockResolvedValue({
      id: 'ver-1',
      template_id: 'tpl-1',
      version_number: 1,
      blocks_snapshot: [],
      changelog: null,
      published_by: null,
      published_at: null,
    });

    renderWithProfile(<DocumentsContent />);
    const filtrosBtn = screen.getByRole('button', { name: /Filtros/i });
    if (filtrosBtn.getAttribute('aria-expanded') !== 'true') {
      fireEvent.click(filtrosBtn);
    }
    fireEvent.click(await screen.findByRole('button', { name: 'seleccionar-modulo' }));

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Nueva Programación' })).toHaveProperty('disabled', false),
    );
    fireEvent.click(screen.getByRole('button', { name: 'Nueva Programación' }));
    expect(mockNavigate).toHaveBeenCalledWith('/nueva-programacion', {
      state: { moduleId: 'm1' },
    });
  });
});

