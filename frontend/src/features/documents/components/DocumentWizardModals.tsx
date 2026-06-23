import { ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { VersionChangelogModal } from '../../../components/VersionChangelogModal';

interface ChangelogModal {
  open: boolean;
  willSubmit: boolean;
  intro: ReactNode;
  initialValue?: string | null;
  submitting: boolean;
  error: string | null;
  onCancel: () => void;
  onConfirm: (changelog: string) => Promise<boolean>;
}

interface DocumentWizardModalsProps {
  emptyEditableBlocks: string[] | null;
  onCloseEmptyEditable: () => void;
  pendingMigrationBlocks: string[] | null;
  onClosePendingMigration: () => void;
  showDeleteBlock: boolean;
  onCancelDeleteBlock: () => void;
  onConfirmDeleteBlock: () => void | Promise<void>;
  validateConfirm: 'approve' | 'reject' | null;
  validatorHasCommented: boolean;
  validationModalError: string | null;
  validationActionLoading: boolean;
  onCancelValidate: () => void;
  onApprove: () => void;
  onReject: () => void;
  summaryConfirmSave: boolean;
  onCancelSummarySave: () => void;
  onConfirmSummarySave: () => void;
  changelog: ChangelogModal;
  noValidatorsOpen: boolean;
  onCancelNoValidators: () => void;
  onConfirmNoValidators: () => void;
}

/** All confirm/changelog dialogs for the document wizard (presentational; logic stays in the parent). */
export function DocumentWizardModals({
  emptyEditableBlocks,
  onCloseEmptyEditable,
  pendingMigrationBlocks,
  onClosePendingMigration,
  showDeleteBlock,
  onCancelDeleteBlock,
  onConfirmDeleteBlock,
  validateConfirm,
  validatorHasCommented,
  validationModalError,
  validationActionLoading,
  onCancelValidate,
  onApprove,
  onReject,
  summaryConfirmSave,
  onCancelSummarySave,
  onConfirmSummarySave,
  changelog,
  noValidatorsOpen,
  onCancelNoValidators,
  onConfirmNoValidators,
}: DocumentWizardModalsProps) {
  const { t } = useTranslation(['documents', 'common']);

  return (
    <>
      <ConfirmDialog
        open={emptyEditableBlocks !== null}
        title={t('documents:wizard.unfilledBlocksTitle')}
        description={
          <div className="space-y-2">
            <p>{t('wizard.fillBlocksFirst')}</p>
            <ul className="space-y-1">
              {(emptyEditableBlocks ?? []).map((name, i) => (
                // biome-ignore lint/suspicious/noArrayIndexKey: static, non-reorderable display list; the index guarantees a unique key when block titles collide.
                <li key={`${i}-${name}`} className="font-medium">
                  • {name}
                </li>
              ))}
            </ul>
          </div>
        }
        confirmLabel={t('common:actions.understood')}
        onConfirm={onCloseEmptyEditable}
        onCancel={onCloseEmptyEditable}
      />
      <ConfirmDialog
        open={pendingMigrationBlocks !== null}
        title={t('documents:migration.pendingTitle')}
        description={
          <div className="space-y-2">
            <p>{t('documents:migration.pendingDescription')}</p>
            <ul className="space-y-1">
              {(pendingMigrationBlocks ?? []).map((name, i) => (
                // biome-ignore lint/suspicious/noArrayIndexKey: static, non-reorderable display list; the index guarantees a unique key when block titles collide.
                <li key={`${i}-${name}`} className="font-medium">
                  • {name}
                </li>
              ))}
            </ul>
          </div>
        }
        confirmLabel={t('common:actions.understood')}
        onConfirm={onClosePendingMigration}
        onCancel={onClosePendingMigration}
      />
      <ConfirmDialog
        open={showDeleteBlock}
        variant="danger"
        title={t('common:confirm.deleteBlock')}
        description={t('wizard.deleteBlockConfirm')}
        confirmLabel={t('common:actions.delete')}
        cancelLabel={t('common:actions.cancel')}
        onCancel={onCancelDeleteBlock}
        onConfirm={onConfirmDeleteBlock}
      />
      <ConfirmDialog
        open={validateConfirm === 'approve'}
        title={t('documents:approveTitle')}
        description={t('approve.description')}
        confirmLabel={t('common:actions.approve')}
        error={validationModalError}
        loading={validationActionLoading}
        onCancel={onCancelValidate}
        onConfirm={onApprove}
      />
      <ConfirmDialog
        open={validateConfirm === 'reject'}
        title={validatorHasCommented ? 'Confirmar rechazo' : 'Comentario requerido'}
        description={
          validatorHasCommented ? (
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              El documento volverá a borrador para que el titular pueda corregirlo. El resto de
              validadores dejarán de tener esta revisión asignada. Tus comentarios en los bloques
              quedarán registrados como motivo.
            </p>
          ) : (
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              Para rechazar la validación debes dejar al menos un comentario en un bloque del
              documento explicando el motivo del rechazo. El comentario queda registrado para el
              titular.
            </p>
          )
        }
        confirmLabel={validatorHasCommented ? 'Rechazar' : 'Entendido'}
        variant={validatorHasCommented ? 'danger' : 'primary'}
        error={validationModalError}
        loading={validationActionLoading}
        onCancel={onCancelValidate}
        onConfirm={validatorHasCommented ? onReject : onCancelValidate}
      />
      <ConfirmDialog
        open={summaryConfirmSave}
        title={t('wizard.confirmSave')}
        description={t('wizard.saveExitDescription')}
        confirmLabel={t('wizard.saveExitConfirm')}
        cancelLabel={t('common:actions.cancel')}
        variant="teal"
        onCancel={onCancelSummarySave}
        onConfirm={onConfirmSummarySave}
      />
      <VersionChangelogModal
        open={changelog.open}
        title={
          changelog.willSubmit
            ? t('wizard.changelogSubmitTitle')
            : t('wizard.changelogPublishTitle')
        }
        intro={changelog.intro}
        initialValue={changelog.initialValue}
        confirmLabel={
          changelog.submitting
            ? changelog.willSubmit
              ? t('wizard.sending')
              : t('wizard.publishing')
            : changelog.willSubmit
              ? t('wizard.submitConfirm')
              : t('wizard.publishConfirm')
        }
        loading={changelog.submitting}
        error={changelog.error}
        onCancel={changelog.onCancel}
        onConfirm={changelog.onConfirm}
      />
      <ConfirmDialog
        open={noValidatorsOpen}
        title={t('wizard.noValidators')}
        description={t('wizard.noValidatorsDescription')}
        confirmLabel={t('wizard.continueAnyway')}
        cancelLabel={t('common:actions.cancel')}
        onConfirm={onConfirmNoValidators}
        onCancel={onCancelNoValidators}
      />
    </>
  );
}
