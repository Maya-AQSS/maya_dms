import { useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
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
import { useServerThemesTable } from '../hooks/useServerThemesTable';
import type { Theme, ThemeStatus } from '../../../types/themes';

// S-01: etiquetas de estado via keys i18n existentes (`themes:identity.statusOptions.*`
// y `values.all` del canon shared) — textos es byte-idénticos a los antiguos literales.
const STATUS_VALUES: ThemeStatus[] = ['draft', 'published', 'archived'];

export function ThemesListPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation(['themes', 'common']);
  const { hasPermission } = useUserProfile();
  const mayCreate = canCreateTheme(hasPermission);

  const { hiddenIds, toggleHidden } = useTablePreferences({
    storageKey: 'maya:dms:themes',
  });

  const { rows, meta, loading, error, filters, setFilter, resetFilters,
          filtersActiveCount, onPageChange, pageSize, onPageSizeChange,
          sortBy, onSortChange } = useServerThemesTable();

  const columns: ColumnDef<Theme>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        alwaysVisible: true,
        sortable: true,
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
        sortable: true,
        cell: (theme) => (
          <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusBadgeClass(theme.status)}`}
          >
            {t(`themes:identity.statusOptions.${theme.status}`, { defaultValue: theme.status })}
          </span>
        ),
      },
    ],
    [t],
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
              onClick={() => navigate('/themes/new', { state: buildBackState(location) })}
            >
              + {t('common:actions.create')}
            </Button>
          ) : undefined
        }
      />

      {error && (
        <div className="my-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          {error.message}
        </div>
      )}

      <DataTable<Theme>
        columns={columns}
        rows={rows}
        rowKey={(theme) => theme.id}
        loading={loading}
        emptyMessage={t('themes:emptyMessage')}
        onRowClick={(theme) => navigate(`/themes/${theme.id}`, { state: buildBackState(location) })}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        filtersLabel="Filtros"
        columnsLabel="Columnas"
        clearFiltersLabel="Limpiar filtros"
        pageSizeLabel="Por página"
        filtersActiveCount={filtersActiveCount}
        onClearFilters={resetFilters}
        filtersStorageKey="maya:dms:themes"
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                placeholder="Buscar por nombre…"
                value={filters.search}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setFilter('search', e.target.value || undefined)
                }
              />
            </FilterField>
            <FilterField label="Estado">
              <Select
                fieldSize="sm"
                value={filters.status}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  setFilter('status', (e.target.value as ThemeStatus) || undefined)
                }
              >
                <option value="">{t('themes:values.all')}</option>
                {STATUS_VALUES.map((value) => (
                  <option key={value} value={value}>
                    {t(`themes:identity.statusOptions.${value}`, { defaultValue: value })}
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
          onChange={onPageChange}
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
