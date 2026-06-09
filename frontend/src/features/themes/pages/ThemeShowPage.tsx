import { useNavigate, useParams } from 'react-router-dom';
import {
  Button,
  ConfirmDialog,
  PageTitle,
  statusBadgeClass,
  useConfirm,
} from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '@ceedcv-maya/shared-profile-react';
import {
  canCloneTheme,
  canDeleteTheme,
  canUpdateTheme,
} from '../../../permissions';
import { PagedThemedPreview } from '../../documents/components/PagedThemedPreview';
import { useTheme } from '../hooks/useTheme';
import { useThemes } from '../hooks/useThemes';
import type { ThemeStatus } from '../../../types/themes';

const STATUS_LABEL: Record<ThemeStatus, string> = {
  draft: 'Borrador',
  published: 'Publicado',
  archived: 'Archivado',
};

const labelClass = 'block text-sm font-medium text-text-secondary dark:text-text-dark-secondary';
const displayClass =
  'mt-1 min-h-[2.75rem] rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary flex items-center';

export function ThemeShowPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { profile, hasPermission } = useUserProfile();
  const { confirmState, confirm, closeConfirm } = useConfirm();

  const { theme, loading, error } = useTheme(id);
  const { cloneTheme, deleteTheme, archiveTheme, actionError, clearActionError } = useThemes();

  if (loading && !theme) {
    return (
      <div className="px-4 py-6 sm:px-6 lg:px-8">
        <PageTitle title="Tema" onBack={() => navigate('/themes')} backLabel="Volver a Temas" />
        <div className="mt-4 rounded-lg border border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          Cargando tema…
        </div>
      </div>
    );
  }

  if (error || !theme || !id) {
    return (
      <div className="px-4 py-6 sm:px-6 lg:px-8">
        <PageTitle title="Tema" onBack={() => navigate('/themes')} backLabel="Volver a Temas" />
        <div className="mt-4 rounded-lg border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          {error || 'No se ha podido cargar el tema.'}
        </div>
      </div>
    );
  }

  const isDraft = theme.status === 'draft';
  const isPublished = theme.status === 'published';
  const mayEdit = canUpdateTheme(hasPermission, profile?.id, theme.created_by);
  const mayClone = canCloneTheme(hasPermission);
  const mayDelete = canDeleteTheme(hasPermission, profile?.id, theme.created_by);

  const handleClone = async () => {
    try {
      const created = await cloneTheme(theme.id);
      navigate(`/themes/${created.id}/edit`);
    } catch {
      /* el banner de actionError lo muestra */
    }
  };

  const handleDelete = () =>
    confirm({
      title: 'Eliminar tema',
      description: `¿Eliminar “${theme.name}”? Las plantillas que lo usen quedarán sin tema asignado.`,
      confirmLabel: 'Eliminar',
      variant: 'danger',
      onConfirm: async () => {
        await deleteTheme(theme.id);
        navigate('/themes');
      },
    });

  const handleArchive = () =>
    confirm({
      title: 'Archivar tema',
      description: `¿Archivar “${theme.name}”? Dejará de ofrecerse para nuevas plantillas.`,
      confirmLabel: 'Archivar',
      onConfirm: async () => {
        await archiveTheme(theme.id);
        navigate('/themes');
      },
    });

  const actions = (
    <div className="flex gap-2">
      {isDraft && mayEdit && (
        <Button type="button" variant="outline" size="sm" onClick={() => navigate(`/themes/${id}/edit`)}>
          Editar
        </Button>
      )}
      {mayClone && (
        <Button type="button" variant="outline" size="sm" onClick={() => void handleClone()}>
          Clonar
        </Button>
      )}
      {isDraft && mayDelete && (
        <Button
          type="button"
          variant="danger"
          size="sm"
          onClick={handleDelete}
        >
          Eliminar
        </Button>
      )}
      {isPublished && mayEdit && (
        <Button type="button" variant="outline" size="sm" onClick={handleArchive}>
          Archivar
        </Button>
      )}
    </div>
  );

  return (
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={theme.name}
        subtitle="Tema · Identidad visual reutilizable"
        onBack={() => navigate('/themes')}
        backLabel="Volver a Temas"
        actions={actions}
      />

      {actionError && (
        <div className="my-3 flex items-center justify-between gap-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="underline">
            cerrar
          </button>
        </div>
      )}

      <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        {/* Previsualización centrada */}
        <div className="flex min-h-[70vh] flex-col overflow-hidden rounded-lg border border-ui-border bg-ui-body dark:border-ui-dark-border dark:bg-ui-dark-bg">
          <div className="border-b border-ui-border px-4 py-2 text-sm font-semibold text-text-primary dark:border-ui-dark-border dark:text-text-dark-primary">
            Previsualización
          </div>
          <div className="flex flex-1 min-h-0 justify-center overflow-auto p-4">
            <PagedThemedPreview kind="theme" id={id} />
          </div>
        </div>

        {/* Propiedades resumidas */}
        <aside className="space-y-3">
          <div>
            <span className={labelClass}>Nombre</span>
            <div className={displayClass}>{theme.name}</div>
          </div>
          <div>
            <span className={labelClass}>Descripción</span>
            <div className={displayClass}>
              {theme.description || (
                <span className="italic text-text-muted dark:text-text-dark-muted">Sin descripción</span>
              )}
            </div>
          </div>
          <div>
            <span className={labelClass}>Estado</span>
            <div className={displayClass}>
              <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusBadgeClass(theme.status)}`}>
                {STATUS_LABEL[theme.status]}
              </span>
            </div>
          </div>
          <div>
            <span className={labelClass}>Bloques</span>
            <div className={displayClass}>{theme.layout?.regions?.length ?? 0}</div>
          </div>
          <div>
            <span className={labelClass}>Paleta</span>
            <div className={`${displayClass} gap-1.5`}>
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
                    className="inline-block h-5 w-5 rounded-full border border-ui-border"
                  />
                ))}
            </div>
          </div>
        </aside>
      </div>

      <ConfirmDialog {...confirmState} onCancel={closeConfirm} />
    </div>
  );
}
