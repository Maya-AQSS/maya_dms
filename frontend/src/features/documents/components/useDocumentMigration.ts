import { useCallback, useEffect, useMemo, useState } from 'react';
import { fetchDocumentMigrationPayload } from '../../../api/documents';
import { pendingMigrationBlockLabels } from '../lib/migrationGate';
import { concatTiptapContent } from '../lib/tiptapContentConcat';
import type {
  DocumentMigrationPayload,
  MigrationChoice,
  RemovedBlockChoice,
} from '../schemas/migrationPayload';

interface UseDocumentMigrationArgs {
  documentId?: string | null;
  sourceDocumentId?: string | null;
  isUpgradeMigration: boolean;
}

/**
 * Migration/upgrade state for the document wizard: fetches the migration payload
 * (clone vs in-situ upgrade), tracks per-block choices and removed-block actions,
 * derives whether the migration step is shown, and builds the request payloads.
 */
export function useDocumentMigration({
  documentId,
  sourceDocumentId,
  isUpgradeMigration,
}: UseDocumentMigrationArgs) {
  const [migrationPayload, setMigrationPayload] = useState<DocumentMigrationPayload | null>(null);
  const [migrationChoices, setMigrationChoices] = useState<Record<string, MigrationChoice>>({});
  const [removedBlockChoices, setRemovedBlockChoices] = useState<
    Record<string, RemovedBlockChoice>
  >({});
  const [pendingMigrationBlocks, setPendingMigrationBlocks] = useState<string[] | null>(null);

  useEffect(() => {
    const fetchFor = isUpgradeMigration ? documentId : !documentId ? sourceDocumentId : null;
    if (!fetchFor) {
      setMigrationPayload(null);
      return;
    }
    let cancelled = false;
    void fetchDocumentMigrationPayload(fetchFor)
      .then((payload) => {
        if (!cancelled) setMigrationPayload(payload);
      })
      .catch(() => {
        // Sin versión nueva (422) u otro error → no se muestra el paso de migración.
        if (!cancelled) setMigrationPayload(null);
      });
    return () => {
      cancelled = true;
    };
  }, [documentId, sourceDocumentId, isUpgradeMigration]);

  const migratableBlocks = useMemo(
    () => migrationPayload?.blocks.filter((b) => b.old_content != null) ?? [],
    [migrationPayload],
  );
  // Decisiones reales del usuario en upgrade: bloques accionables (replace/append) o eliminados (delete/keep).
  const upgradeHasDecisions = useMemo(() => {
    if (!migrationPayload) return false;
    return migrationPayload.blocks.some(
      (b) =>
        (!b.locked && !b.new_block && !b.removed_block && b.old_content != null) || b.removed_block,
    );
  }, [migrationPayload]);
  // Upgrade pendiente: hay versión nueva a la que actualizar (aunque no haya nada que elegir).
  const upgradePending = isUpgradeMigration && !!migrationPayload;
  // El paso de migración se muestra solo si hay algo que decidir; si no, se aplica directo (mejora UX).
  const showMigrationStep = isUpgradeMigration
    ? upgradePending && upgradeHasDecisions
    : !documentId && migratableBlocks.length > 0;

  const setMigrationChoice = useCallback(
    (templateBlockId: string, choice: MigrationChoice | undefined) => {
      setMigrationChoices((prev) => {
        if (!choice) {
          const { [templateBlockId]: _removed, ...rest } = prev;
          return rest;
        }
        return { ...prev, [templateBlockId]: choice };
      });
    },
    [],
  );

  const setRemovedBlockChoice = useCallback(
    (templateBlockId: string, choice: RemovedBlockChoice | undefined) => {
      setRemovedBlockChoices((prev) => {
        if (!choice) {
          const { [templateBlockId]: _removed, ...rest } = prev;
          return rest;
        }
        return { ...prev, [templateBlockId]: choice };
      });
    },
    [],
  );

  const buildMigratedBlocks = useCallback((): Record<string, unknown> => {
    if (!migrationPayload) return {};
    const out: Record<string, unknown> = {};
    for (const block of migrationPayload.blocks) {
      if (block.locked || block.new_block || block.removed_block) continue;
      const choice = migrationChoices[block.template_block_id];
      if (!choice || block.old_content == null) continue;
      out[block.template_block_id] =
        choice === 'append'
          ? concatTiptapContent(block.new_default_content, block.old_content)
          : block.old_content;
    }
    return out;
  }, [migrationPayload, migrationChoices]);

  const buildRemovedBlockActions = useCallback((): Record<string, RemovedBlockChoice> => {
    if (!migrationPayload) return {};
    const out: Record<string, RemovedBlockChoice> = {};
    for (const block of migrationPayload.blocks) {
      if (!block.removed_block) continue;
      const choice = removedBlockChoices[block.template_block_id];
      if (choice) out[block.template_block_id] = choice;
    }
    return out;
  }, [migrationPayload, removedBlockChoices]);

  // Bloques con elección pendiente: accionables (replace/append) y, en upgrade, eliminados (delete/keep).
  const computePendingMigrationBlocks = useCallback(
    (): string[] =>
      pendingMigrationBlockLabels(
        migrationPayload,
        migrationChoices,
        removedBlockChoices,
        isUpgradeMigration,
      ),
    [migrationPayload, migrationChoices, removedBlockChoices, isUpgradeMigration],
  );

  return {
    migrationPayload,
    migrationChoices,
    removedBlockChoices,
    pendingMigrationBlocks,
    setPendingMigrationBlocks,
    migratableBlocks,
    upgradePending,
    showMigrationStep,
    setMigrationChoice,
    setRemovedBlockChoice,
    buildMigratedBlocks,
    buildRemovedBlockActions,
    computePendingMigrationBlocks,
  };
}
