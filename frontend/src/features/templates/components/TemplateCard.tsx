import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import type { Template, TemplateStatus } from '../../../types/templates';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption } from '../../../utils/academicContextSearch';
import { shouldOpenTemplateEditorFromList } from '../templateListNavigation';

const STATUS_LABEL: Record<TemplateStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
  rejected: 'Rechazada',
};

type Props = {
  template: Template;
  onDelete: (id: string) => Promise<void>;
  onClone: (id: string) => Promise<void>;
};

export function TemplateCard({ template: t, onDelete, onClone }: Props) {
  const navigate = useNavigate();
  const { profile, hasPermission } = useUserProfile();
  const { hierarchy } = useHierarchy();
  const visibilityCaption = formatListRowVisibilityCaption(hierarchy, {
    visibility_level: t.visibility_level,
    study_type_id: t.study_type_id,
    study_id: t.study_id,
    module_id: t.module_id,
    team_id: t.team_id,
    team: t.team,
  });
  const [dialog, setDialog] = useState<'delete' | 'clone' | null>(null);
  const [dialogLoading, setDialogLoading] = useState(false);
  const canClone = t.can_clone === true;
  const isOwner = profile?.id === t.created_by;
  const canEdit = isOwner && (t.status === 'draft' || t.status === 'rejected');
  const openEditorFromList = shouldOpenTemplateEditorFromList(t, isOwner);
  const isAuthorized =
    profile?.id === t.created_by || hasPermission(DMS_PERMISSIONS.templateDelete);
  const hasDiscardableDraft = !!(t.working_version_id && (t.status === 'draft' || t.status === 'in_review'));
  const canDelete = isAuthorized && (!t.latest_published_version_id || hasDiscardableDraft);

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

  const myReviewerRow = t.status === 'in_review' && profile?.id
    ? (t.reviewers?.find((r) => r.user_id === profile.id) ?? null)
    : null;
  const isAssignedReviewer = myReviewerRow !== null;
  const isBlockedBySequentialOrder =
    isAssignedReviewer &&
    t.review_mode === 'sequential' &&
    myReviewerRow !== null &&
    (t.reviewers ?? []).some(
      (r) => r.stage < myReviewerRow.stage && r.status !== 'approved',
    );
  const canValidate = isAssignedReviewer && !isBlockedBySequentialOrder;
  const isRejected = t.status === 'rejected';

  return (
    <div
      className={[
        'relative rounded-lg border p-4 shadow-card transition-all duration-300',
        canValidate 
          ? 'border-odoo-teal dark:border-odoo-dark-teal bg-odoo-teal/5 dark:bg-odoo-dark-teal/10 cursor-pointer hover:shadow-card-md' 
          : isRejected
            ? 'border-warning/40 dark:border-warning/30 bg-warning-light/20 dark:bg-warning-dark/10 cursor-pointer shadow-sm hover:shadow-md'
            : 'border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card',
        dialog === null && (canEdit || canValidate || isRejected) ? 'cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors' : '',
      ].join(' ')}
      onClick={
        dialog === null
          ? canValidate
            ? () => navigate(`/templates/${t.id}/review`)
            : isRejected
              ? () => navigate(`/templates/${t.id}`)  // rejected → preview with readonly comments first
              : openEditorFromList
                ? () => navigate(`/templates/${t.id}/edit`)
              : canEdit
                ? () => navigate(`/templates/${t.id}`)
                : undefined
          : undefined
      }
    >
      {isRejected && (
        <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-3/4 bg-warning/60 rounded-r-full" />
      )}

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1 space-y-2">
          <div className="flex items-center gap-2">
            <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
              {t.name}
            </h3>
            {isRejected && (
              <span className="shrink-0 flex items-center gap-1.5 text-xs font-black uppercase tracking-widest bg-warning-dark text-text-inverse px-2 py-0.5 rounded-full shadow-sm border border-warning">
                <span className="motion-safe:animate-pulse">●</span> Rechazada
              </span>
            )}
          </div>
          {t.description ? (
            <p className="text-xs text-text-secondary dark:text-text-dark-secondary line-clamp-2">{t.description}</p>
          ) : null}
          <div className="flex flex-wrap gap-2 text-xs text-text-muted dark:text-text-dark-muted font-medium">
            <span
              className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5 border border-ui-border dark:border-ui-dark-border max-w-full min-w-0 truncate inline-block align-bottom"
              title={visibilityCaption}
            >
              {visibilityCaption}
            </span>
            {t.author_name ? (
              <span className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5 border border-ui-border dark:border-ui-dark-border opacity-70">
                {t.author_name}
              </span>
            ) : null}
            <span className={[
              'rounded px-2 py-0.5 border',
              isRejected 
                ? 'text-warning-dark dark:text-warning-light bg-warning-light/50 dark:bg-warning-dark/30 border-warning/40 dark:border-warning-dark font-bold'
                : 'bg-ui-body dark:bg-ui-dark-bg border-ui-border dark:border-ui-dark-border'
            ].join(' ')}>
              {isRejected ? 'Requiere cambios' : (STATUS_LABEL[t.status] ?? t.status)}
            </span>
            <span className="px-2 py-0.5 opacity-70 italic">v{t.version}</span>
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
              variant="danger"
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
        icon="🗑️"
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
        icon="📋"
      />
    </div>
  );
}
