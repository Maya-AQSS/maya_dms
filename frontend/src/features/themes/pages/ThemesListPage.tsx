import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Button,
  DataTable,
  FilterField,
  Pagination,
  PageTitle,
  Select,
  TextInput,
  statusBadgeClass,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '@ceedcv-maya/shared-profile-react';
import { canCreateTheme } from '../../../permissions';
import { useThemes } from '../hooks/useThemes';
import type { Theme, ThemeStatus } from '../../../types/themes';

const STATUS_LABEL: Record<ThemeStatus, string> = {
  draft: 'Borrador',
  published: 'Publicado',
  archived: 'Archivado',
};

const STATUS_OPTIONS: { value: '' | ThemeStatus; label: string }[] = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'published', label: 'Publicado' },
  { value: 'archived', label: 'Archivado' },
];

const STORAGE_KEY = 'maya:dms:themes-table';

export function ThemesListPage() {
  const navigate = useNavigate();
  const { t } = useTranslation(['themes', 'common']);
  const { hasPermission } = useUserProfile();
  const mayCreate = canCreateTheme(hasPermission);

  const { hiddenIds, toggleHidden, pageSize, setPageSize } = useTablePreferences({
    storageKey: STORAGE_KEY,
  });

  const { items, meta, loading, listError, filters, applyFilters, goToPage } = useThemes({
    per_page: pageSize,
  });

  // Búsqueda por nombre con debounce → filtro server-side (`search`).
  const [nameInput, setNameInput] = useState(filters.search ?? '');
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setNameInput(value);
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    nameDebounceRef.current = setTimeout(() => {
      applyFilters({ search: value || undefined });
    }, 400);
  };

  const statusFilter = filters.status ?? '';

  const filtersActiveCount =
    (filters.search ? 1 : 0) + (filters.status ? 1 : 0);

  const clearFilters = () => {
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    setNameInput('');
    applyFilters({ search: undefined, status: undefined });
  };

  // Si la página actual queda fuera de rango tras filtrar, retrocede.
  useEffect(() => {
    if (meta && meta.current_page > meta.last_page) {
      goToPage(meta.last_page);
    }
  }, [meta, goToPage]);

  const columns: ColumnDef<Theme>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        alwaysVisible: true,
        cell: (theme) => <span className="font-medium">{theme.name}</span>,
      },
      {
        id: 'description',
        header: 'Descripción',
        cell: (theme) => (
          <span className="text-text-muted dark:text-text-dark-muted">
            {theme.description || '—'}
          </span>
        ),
      },
      {
        id: 'palette',
        header: 'Paleta',
        cell: (theme) => (
          <div className="flex gap-1">
            {[
              theme.palette.primary,
              theme.palette.secondary,
              theme.palette.accent,
              theme.palette.text,
              theme.palette.background,
            ]
              .filter(Boolean)
              .map((color, idx) => (
                <span
                  key={`${theme.id}-${idx}`}
                  title={String(color)}
                  style={{ backgroundColor: String(color) }}
                  className="inline-block h-4 w-4 rounded-full border border-ui-border"
                />
              ))}
          </div>
        ),
      },
      {
        id: 'status',
        header: 'Estado',
        cell: (theme) => (
          <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusBadgeClass(theme.status)}`}
          >
            {STATUS_LABEL[theme.status]}
          </span>
        ),
      },
    ],
    [],
  );

  return (
    <>
      <PageTitle
        title={t('themes:title')}
        subtitle={t('themes:subtitle')}
        actions={
          mayCreate ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/themes/new')}
            >
              + {t('common:actions.create')}
            </Button>
          ) : undefined
        }
      />

      {listError && (
        <div className="my-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          {listError}
        </div>
      )}

      <DataTable<Theme>
        columns={columns}
        rows={items}
        rowKey={(theme) => theme.id}
        loading={loading}
        emptyMessage={t('themes:emptyMessage')}
        onRowClick={(theme) => navigate(`/themes/${theme.id}`)}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size);
          applyFilters({ per_page: size });
        }}
        filtersLabel="Filtros"
        columnsLabel="Columnas"
        clearFiltersLabel="Limpiar filtros"
        pageSizeLabel="Por página"
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey={STORAGE_KEY}
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                placeholder="Buscar por nombre…"
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label="Estado">
              <Select
                fieldSize="sm"
                value={statusFilter}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  applyFilters({ status: (e.target.value as ThemeStatus) || undefined })
                }
              >
                {STATUS_OPTIONS.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
          </>
        }
      />

      {meta && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={goToPage}
          info={
            meta.total > 0
              ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(
                  meta.current_page * meta.per_page,
                  meta.total,
                )} de ${meta.total}`
              : undefined
          }
        />
      )}
    </>
  );
}
