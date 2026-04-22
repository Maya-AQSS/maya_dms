import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, ConfirmDialog } from '../../../ui';
import type { Template } from '../../../types/templates';
import { visibilityLabel } from '../constants';
import { useUserProfile } from '../../../features/user-profile';

type Props = {
  template: Template;
  onDelete: (id: string) => Promise<void>;
  onClone: (id: string) => Promise<void>;
};

export function TemplateCard({ template: t, onDelete, onClone }: Props) {
  const navigate = useNavigate();
  const { profile, hasPermission } = useUserProfile();
  const [dialog, setDialog] = useState<'delete' | 'clone' | null>(null);
  const [dialogLoading, setDialogLoading] = useState(false);
  const canClone = t.status === 'published';
  const canEdit = t.status === 'draft' && profile?.id === t.created_by;
  const canDelete = profile?.id === t.created_by || hasPermission('templates.delete');

  const closeDialog = () => {
    if (dialogLoading) return;
    setDialog(null);
  };

  const confirmDelete = async () => {
    setDialogLoading(true);
    try {
      await onDelete(t.id);
    } catch {
      /* el hook ya registró actionError */
    } finally {
      setDialogLoading(false);
      setDialog(null);
    }
  };

  const confirmClone = async () => {
    setDialogLoading(true);
    try {
      await onClone(t.id);
    } catch {
      /* el hook ya registró actionError */
    } finally {
      setDialogLoading(false);
      setDialog(null);
    }
  };

  return (
    <div
      className={[
        'rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card p-4 shadow-card',
        dialog === null && canEdit ? 'cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors' : '',
      ].join(' ')}
      onClick={dialog === null && canEdit ? () => navigate(`/templates/${t.id}/edit`) : undefined}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1 space-y-2">
          <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            {t.name}
          </h3>
          {t.description ? (
            <p className="text-xs text-text-secondary dark:text-text-dark-secondary">{t.description}</p>
          ) : null}
          <div className="flex flex-wrap gap-2 text-xs text-text-muted dark:text-text-dark-muted">
            <span className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5">
              {visibilityLabel(t.visibility_level)}
            </span>
            <span className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5">{t.status}</span>
            <span>v{t.version}</span>
          </div>
        </div>
        <div
          className="flex flex-wrap gap-2"
          onClick={(e) => e.stopPropagation()}
        >
          {canClone && (
            <Button
              type="button"
              variant="outlineTeal"
              size="xs"
              disabled={dialog !== null}
              onClick={() => setDialog('clone')}
            >
              Clonar
            </Button>
          )}
          {canDelete && (
            <Button
              type="button"
              variant="outlineWarning"
              size="xs"
              disabled={dialog !== null}
              onClick={() => setDialog('delete')}
            >
              Eliminar
            </Button>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={dialog === 'delete'}
        title="¿Eliminar plantilla?"
        description={
          <>
            Se eliminará por completo la plantilla «<strong className="font-medium">{t.name}</strong>» si no tiene
            documentos vinculados; en caso contrario se archivará.
          </>
        }
        confirmLabel="Eliminar"
        variant="danger"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmDelete}
      />
      <ConfirmDialog
        open={dialog === 'clone'}
        title="¿Clonar plantilla?"
        description={
          <>
            Se creará un <strong className="font-medium">borrador</strong> copiando «{t.name}», con el sufijo «(copia)»
            en el nombre.
          </>
        }
        confirmLabel="Clonar"
        cancelLabel="No clonar"
        variant="teal"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmClone}
      />
    </div>
  );
}
