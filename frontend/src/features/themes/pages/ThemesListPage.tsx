import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Button,
  ConfirmDialog,
  DataTable,
  Pagination,
  PageTitle,
  useConfirm,
  type ColumnDef,
} from '@maya/shared-ui-react';
import { useUserProfile } from '@maya/shared-profile-react';
import {
  canCloneTheme,
  canCreateTheme,
  canDeleteTheme,
  canUpdateTheme,
} from '../../../permissions';
import { useThemes } from '../hooks/useThemes';
import type { Theme, ThemeStatus } from '../../../types/themes';

const STATUS_LABEL: Record<ThemeStatus, string> = {
  draft: 'Borrador',
  published: 'Publicado',
  archived: 'Archivado',
};

const STATUS_CLASS: Record<ThemeStatus, string> = {
  draft: 'bg-yellow-100 text-yellow-800',
  published: 'bg-green-100 text-green-800',
  archived: 'bg-gray-100 text-gray-700',
};

export function ThemesListPage() {
  const navigate = useNavigate();
  const { t } = useTranslation(['themes', 'common']);
  const { confirmState, confirm, closeConfirm } = useConfirm();
  const { profile, hasPermission } = useUserProfile();
  const mayCreate = canCreateTheme(hasPermission);
  const mayClone = canCloneTheme(hasPermission);

  const {
    items,
    meta,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
    deleteTheme,
    cloneTheme,
    goToPage,
  } = useThemes();

  const columns: ColumnDef<Theme>[] = [
    {
      id: 'name',
      header: 'Nombre',
      cell: (theme) => {
        const mayEdit = canUpdateTheme(hasPermission, profile?.id, theme.created_by);
        if (!mayEdit) {
          return <span className="font-medium">{theme.name}</span>;
        }
        return (
          <button
            type="button"
            onClick={() => navigate(`/themes/${theme.id}/edit`)}
            className="text-left font-medium text-odoo-purple hover:underline"
          >
            {theme.name}
          </button>
        );
      },
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
                className="inline-block h-4 w-4 rounded-full border border-gray-300"
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
          className={`inline-flex rounded px-2 py-0.5 text-xs font-medium ${STATUS_CLASS[theme.status]}`}
        >
          {STATUS_LABEL[theme.status]}
        </span>
      ),
    },
    {
      id: 'actions',
      header: '',
      cell: (theme) => {
        const mayEdit = canUpdateTheme(hasPermission, profile?.id, theme.created_by);
        const mayDelete = canDeleteTheme(hasPermission, profile?.id, theme.created_by);

        return (
          <div className="flex justify-end gap-2">
            {mayEdit && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => navigate(`/themes/${theme.id}/edit`)}
              >
                Editar
              </Button>
            )}
            {mayClone && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => void cloneTheme(theme.id)}
              >
                Clonar
              </Button>
            )}
            {mayDelete && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() =>
                  confirm({
                    title: 'Eliminar theme',
                    description: `¿Eliminar “${theme.name}”? Las plantillas que lo usen quedarán sin theme asignado.`,
                    confirmLabel: 'Eliminar',
                    variant: 'danger',
                    onConfirm: async () => {
                      await deleteTheme(theme.id);
                    },
                  })
                }
              >
                Eliminar
              </Button>
            )}
          </div>
        );
      },
    },
  ];

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
        <div className="my-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="my-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
          <span>{actionError}</span>
          <button
            type="button"
            onClick={clearActionError}
            className="ml-3 underline"
            aria-label={t('themes:closeErrorMessage')}
          >
            cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="my-3 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-700">
          <span>{actionInfo}</span>
          <button
            type="button"
            onClick={clearActionInfo}
            className="ml-3 underline"
            aria-label={t('themes:closeInfoMessage')}
          >
            cerrar
          </button>
        </div>
      )}

      <DataTable<Theme>
        columns={columns}
        rows={items}
        rowKey={(theme) => theme.id}
        loading={loading}
        emptyMessage={t('themes:emptyMessage')}
      />

      {meta && meta.total > meta.per_page && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={goToPage}
        />
      )}

      <ConfirmDialog {...confirmState} onCancel={closeConfirm} />
    </>
  );
}
