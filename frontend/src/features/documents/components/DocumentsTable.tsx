import { useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocuments } from '../hooks/useDocuments';
import {
  DataTable,
  DatePicker,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  statusBadgeClass,
  visibilityBadgeClass,
  type ColumnDef,
} from '@maya/shared-ui-react';
import type { Document, DocumentStatus } from '../../../types/documents';
import { FAVORITES_FILTER_OPTIONS, VISIBILITY_OPTIONS, visibilityLabel } from '../../templates/constants';
import type { TemplateVisibilityLevel } from '../../../types/templates';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { useUserProfile } from '../../../features/user-profile';

// Estado y visibilidad: clases provenientes del módulo compartido `badges`
// (los colores hex viven en `maya_infra/configs/styles/index.css`).

const STATUS_LABEL: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

const STATUS_FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'in_review', label: 'En revisión' },
  { value: 'published', label: 'Publicado' },
];

const VISIBILITY_FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Todas' },
  ...VISIBILITY_OPTIONS,
];


function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

type Filters = {
  name: string;
  visibility: string;
  status: string;
  authorName: string;
  date: string;
  /** '' = sin filtro; 'favorites' = solo marcados como favoritos */
  favorites: string;
};

function applyClientFilters(
  docs: Document[],
  filters: Filters,
  favoriteDocumentIds: ReadonlySet<string>,
): Document[] {
  return docs.filter((doc) => {
    if (filters.favorites === 'favorites' && !favoriteDocumentIds.has(doc.id)) {
      return false;
    }
    if (filters.name) {
      const title = (doc.title ?? '').toLowerCase();
      if (!title.includes(filters.name.toLowerCase())) return false;
    }
    if (filters.status && doc.status !== filters.status) return false;
    if (filters.visibility && doc.visibility_level !== filters.visibility) return false;
    if (filters.authorName) {
      const name = (doc.owner_name ?? '').toLowerCase();
      if (!name.includes(filters.authorName.toLowerCase())) return false;
    }
    if (filters.date && doc.delivery_deadline) {
      if (!doc.delivery_deadline.startsWith(filters.date)) return false;
    } else if (filters.date && !doc.delivery_deadline) {
      return false;
    }
    return true;
  });
}


type Props = {
  /** Filtra el listado por proceso. No se expone en el panel de filtros. */
  processId?: string;
};

export function DocumentsTable({ processId }: Props = {}) {
  const navigate = useNavigate();
  const { profile, hasPermission } = useUserProfile();
  const { documentIds: favoriteDocumentIds } = useFavoritesIds();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:documents-table',
  });
  const { documents, loading, error } = useDocuments(processId);

  const columns: ColumnDef<Document>[] = useMemo(
    () => [
      {
        id: 'title',
        header: 'Nombre',
        alwaysVisible: true,
        cell: (doc) => (
          <span className="flex items-center gap-2 min-w-0">
            {favoriteDocumentIds.has(doc.id) && <FavoriteInlineMark />}
            <span className="font-medium truncate">{doc.title}</span>
          </span>
        ),
        sortable: true,
      },
      {
        id: 'visibility_level',
        header: 'Visibilidad',
        cell: (doc) => {
          const visLevel = (doc.visibility_level ?? 'personal') as TemplateVisibilityLevel;
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${visibilityBadgeClass(visLevel)}`}>
              {visibilityLabel(visLevel)}
            </span>
          );
        },
      },
      {
        id: 'owner_name',
        header: 'Autor',
        cell: (doc) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">{doc.owner_name ?? '—'}</span>
        ),
      },
      {
        id: 'status',
        header: 'Estado',
        cell: (doc) => {
          const status = doc.status as DocumentStatus;
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(status)}`}>
              {STATUS_LABEL[status] ?? status}
            </span>
          );
        },
      },
      {
        id: 'delivery_deadline',
        header: 'Fecha',
        sortable: true,
        cell: (doc) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">{formatDate(doc.delivery_deadline)}</span>
        ),
      },
    ],
    [favoriteDocumentIds],
  );

  const [filters, setFilters] = useState<Filters>({
    name: '',
    visibility: '',
    status: '',
    authorName: '',
    date: '',
    favorites: '',
  });
  const [nameInput, setNameInput] = useState('');
  const [authorInput, setAuthorInput] = useState('');
  const [page, setPage] = useState(1);
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const displayDocuments = useMemo(() => {
    const out: Document[] = [];
    for (const d of documents) {
      const hasPublishedFallback =
        d.status !== 'published' &&
        !!d.latest_published_version_id;
      const isAssignedReviewer =
        d.status === 'in_review' &&
        hasPermission('documents.review');
      const canSeeLive =
        (profile?.id != null && (profile.id === d.created_by || profile.id === d.owner_id)) ||
        d.share_permission === 'edit' ||
        isAssignedReviewer;

      if (!hasPublishedFallback) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
        continue;
      }

      const publishedFallback: Document = {
        ...d,
        title: d.latest_published_title ?? d.title,
        status: 'published',
        current_version: d.latest_published_version_number ?? d.current_version,
        list_variant: 'published_fallback',
        list_row_id: `${d.id}:published`,
      };

      if (canSeeLive) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
      }
      out.push(publishedFallback);
    }
    return out;
  }, [documents, hasPermission, profile?.id]);

  const filtered = useMemo(
    () => applyClientFilters(displayDocuments, filters, favoriteDocumentIds),
    [displayDocuments, filters, favoriteDocumentIds],
  );

  const sorted = useMemo(() => {
    if (!sortBy) return filtered;
    const { columnId, direction } = sortBy;
    const dir = direction === 'asc' ? 1 : -1;

    return [...filtered].sort((a, b) => {
      let valA: string | number = '';
      let valB: string | number = '';

      if (columnId === 'title') {
        return (a.title ?? '').localeCompare(b.title ?? '', 'es') * dir;
      } else if (columnId === 'delivery_deadline') {
        valA = a.delivery_deadline ?? '';
        valB = b.delivery_deadline ?? '';
      } else if (columnId === 'status') {
        valA = a.status ?? '';
        valB = b.status ?? '';
      }

      if (valA < valB) return -1 * dir;
      if (valA > valB) return 1 * dir;
      return 0;
    });
  }, [filtered, sortBy]);

  const totalPages = Math.max(1, Math.ceil(sorted.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageSlice = sorted.slice((safePage - 1) * pageSize, safePage * pageSize);

  const filtersActiveCount =
    (filters.favorites ? 1 : 0) +
    [filters.name, filters.visibility, filters.status, filters.authorName, filters.date].filter(Boolean).length;

  const handleFilterChange = (patch: Partial<Filters>) => {
    setFilters((f) => ({ ...f, ...patch }));
    setPage(1);
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setNameInput(value);
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    nameDebounceRef.current = setTimeout(() => {
      handleFilterChange({ name: value });
    }, 400);
  };

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      handleFilterChange({ authorName: value });
    }, 400);
  };

  const clearFilters = () => {
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setNameInput('');
    setAuthorInput('');
    setFilters({ name: '', visibility: '', status: '', authorName: '', date: '', favorites: '' });
    setPage(1);
  };

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          Error al cargar documentos: {error.message}
        </div>
      )}

      <DataTable
        columns={columns}
        rows={pageSlice}
        loading={loading && documents.length === 0}
        rowKey={(doc) => doc.list_row_id ?? doc.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={setSortBy}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size)
          setPage(1)
        }}
        emptyMessage="No hay documentos con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:documents-table"
        onRowClick={(doc) => {
          if (doc.list_variant === 'published_fallback' && doc.latest_published_version_id) {
            navigate(`/documents/${doc.id}?documentVersionId=${encodeURIComponent(doc.latest_published_version_id)}`, {
              state: { backTo: processId ? `/procesos/${processId}` : '/dashboard', processId },
            });
            return;
          }
          navigate(`/documents/${doc.id}`, {
            state: { backTo: processId ? `/procesos/${processId}` : '/dashboard', processId },
          });
        }}
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder="Buscar por nombre..."
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label="Visibilidad">
              <Select
                fieldSize="sm"
                value={filters.visibility}
                onChange={(e) => handleFilterChange({ visibility: e.target.value })}
              >
                {VISIBILITY_FILTER_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Estado">
              <Select
                fieldSize="sm"
                value={filters.status}
                onChange={(e) => handleFilterChange({ status: e.target.value })}
              >
                {STATUS_FILTER_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Autor">
              <TextInput
                fieldSize="sm"
                placeholder="Nombre del autor..."
                value={authorInput}
                onChange={handleAuthorChange}
              />
            </FilterField>
            <FilterField label="Favoritos">
              <Select
                fieldSize="sm"
                value={filters.favorites}
                onChange={(e) => handleFilterChange({ favorites: e.target.value })}
              >
                {FAVORITES_FILTER_OPTIONS.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Fecha">
              <DatePicker
                value={filters.date || null}
                onChange={(d) => handleFilterChange({ date: d ?? '' })}
                placeholder="Seleccionar fecha..."
              />
            </FilterField>
          </>
        }
      />

      <Pagination
        currentPage={safePage}
        totalPages={totalPages}
        onChange={setPage}
        info={`Página ${safePage} de ${totalPages} — ${filtered.length} documentos`}
      />
    </div>
  );
}
