import { useCallback, useState } from 'react';
import type { TFunction } from 'i18next';
import { NewVersionConflictError } from '../../../api/newVersion';
import type { NewVersionEntityMeta } from '../../../utils/versionableEntityActions';
import {
  buildNewVersionBlockedDescription,
  type WorkingRevisionBlockInfo,
} from '../../../utils/workingRevisionMessages';

type Params = {
  t: TFunction;
  entity: NewVersionEntityMeta | null | undefined;
  entityId: string | undefined;
  gatesOpen: boolean;
  startNewVersion: (id: string) => Promise<unknown>;
  onSuccess: (result: unknown) => void | Promise<void>;
};

export function useNewVersionFlow({
  t,
  entity,
  entityId,
  gatesOpen,
  startNewVersion,
  onSuccess,
}: Params) {
  const [draftBlockedBy, setDraftBlockedBy] = useState<string | null>(null);
  const [showConfirm, setShowConfirm] = useState(false);
  const [confirmLoading, setConfirmLoading] = useState(false);
  const [confirmError, setConfirmError] = useState<string | null>(null);

  const showNewVersionButton = gatesOpen && entity?.can_create_new_version === true;

  const openBlockedModal = useCallback((info: WorkingRevisionBlockInfo) => {
    setDraftBlockedBy(buildNewVersionBlockedDescription(info, t));
  }, [t]);

  const handleRequestNewVersion = useCallback(() => {
    if (entity?.working_revision_in_progress) {
      openBlockedModal({
        editorName: entity.working_revision_editor_name,
        startedAt: entity.working_revision_started_at,
      });
      return;
    }
    setConfirmError(null);
    setShowConfirm(true);
  }, [entity, openBlockedModal]);

  const handleConfirmNewVersion = useCallback(async () => {
    if (!entityId) return;
    setConfirmLoading(true);
    setConfirmError(null);
    try {
      const result = await startNewVersion(entityId);
      setShowConfirm(false);
      await onSuccess(result);
    } catch (error) {
      if (error instanceof NewVersionConflictError) {
        setShowConfirm(false);
        setConfirmError(null);
        openBlockedModal({
          editorName: error.conflict.draftAuthor,
          startedAt: error.conflict.startedAt,
        });
        return;
      }
      setConfirmError(error instanceof Error ? error.message : 'No se pudo abrir una nueva versión.');
    } finally {
      setConfirmLoading(false);
    }
  }, [entityId, onSuccess, openBlockedModal, startNewVersion]);

  return {
    showNewVersionButton,
    draftBlockedBy,
    showConfirm,
    confirmLoading,
    confirmError,
    handleRequestNewVersion,
    handleConfirmNewVersion,
    dismissBlockedModal: () => setDraftBlockedBy(null),
    dismissConfirm: () => {
      setShowConfirm(false);
      setConfirmError(null);
    },
  };
}
