import { useCallback, useEffect, useState } from 'react';
import {
  createBlock as createBlockRequest,
  deleteBlock as deleteBlockRequest,
  fetchBlocks,
  reorderBlocksForTemplate,
  updateBlock as updateBlockRequest,
} from '../../../api/blocks';
import { ApiHttpError } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import {
  canCreateTemplateBlock,
  canDeleteTemplateBlock,
  canListTemplateBlocks,
  canUpdateTemplateBlock,
  type TemplateBlockPermissionContext,
} from '../../../permissions';
import type {
  CreateBlockPayload,
  TemplateBlock,
  UpdateBlockPayload,
} from '../../../types/blocks';

function formatError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 422) return err.message || 'Datos no válidos.';
    if (err.status === 404) return 'Bloque no encontrado.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

export function useTemplateBlocks(
  templateId: string,
  templateContext?: TemplateBlockPermissionContext,
) {
  const { hasPermission, profile } = useUserProfile();
  const mayListBlocks = canListTemplateBlocks(hasPermission, profile?.id, templateContext);
  const mayCreateBlock = canCreateTemplateBlock(hasPermission, profile?.id, templateContext);
  const mayUpdateBlock = canUpdateTemplateBlock(hasPermission, profile?.id, templateContext);
  const mayDeleteBlock = canDeleteTemplateBlock(hasPermission, profile?.id, templateContext);
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // IDs seleccionados en el outline (selección múltiple)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    if (!mayListBlocks) {
      setBlocks([]);
      setError('No tienes permiso para listar bloques (block.index).');
      setLoading(false);
      return;
    }
    try {
      const res = await fetchBlocks(templateId);
      setBlocks(res.data);
    } catch (e) {
      setError(formatError(e));
      setBlocks([]);
    } finally {
      setLoading(false);
    }
  }, [templateId, mayListBlocks]);

  useEffect(() => {
    void load();
  }, [load]);

  const createBlock = useCallback(
    async (payload: CreateBlockPayload) => {
      if (!mayCreateBlock) {
        throw new Error('No tienes permiso para crear bloques (block.create).');
      }
      const res = await createBlockRequest(templateId, payload);
      setBlocks((prev: TemplateBlock[]) => [...prev, res.data]);
      return res.data;
    },
    [templateId, mayCreateBlock],
  );

  const updateBlock = useCallback(
    async (blockId: string, payload: UpdateBlockPayload) => {
      if (!mayUpdateBlock) {
        throw new Error('No tienes permiso para actualizar bloques (block.update).');
      }
      const res = await updateBlockRequest(blockId, payload);
      setBlocks((prev: TemplateBlock[]) => prev.map((b: TemplateBlock) => (b.id === blockId ? res.data : b)));
      return res.data;
    },
    [mayUpdateBlock],
  );

  const deleteBlock = useCallback(async (blockId: string) => {
    if (!mayDeleteBlock) {
      throw new Error('No tienes permiso para eliminar bloques (block.delete).');
    }
    await deleteBlockRequest(blockId);
    setBlocks((prev: TemplateBlock[]) => prev.filter((b: TemplateBlock) => b.id !== blockId));
    setSelectedIds((prev: Set<string>) => {
      const next = new Set(prev);
      next.delete(blockId);
      return next;
    });
  }, [mayDeleteBlock]);

  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev: Set<string>) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const selectOnly = useCallback((id: string) => {
    setSelectedIds(new Set([id]));
  }, []);

  const clearSelection = useCallback(() => setSelectedIds(new Set()), []);

  return {
    blocks,
    loading,
    error,
    selectedIds,
    refetch: load,
    createBlock,
    updateBlock,
    deleteBlock,

    toggleSelect,
    selectOnly,
    clearSelection,
    mayCreateBlock,
    mayUpdateBlock,
    mayDeleteBlock,
    reorderBlocks: async (draggedId: string, targetIndex: number) => {
      if (!mayUpdateBlock) {
        setError('No tienes permiso para reordenar bloques (block.update).');
        return;
      }
      const snapshot = blocks;
      const sourceIndex = snapshot.findIndex((b: TemplateBlock) => b.id === draggedId);
      if (sourceIndex === -1) return;

      const boundedTargetIndex = Math.max(0, Math.min(targetIndex, snapshot.length - 1));

      const newBlocks = [...snapshot];
      const [movedBlock] = newBlocks.splice(sourceIndex, 1);
      newBlocks.splice(boundedTargetIndex, 0, movedBlock);
      setBlocks(newBlocks); // optimistic update

      const desiredIds = newBlocks.map((b: TemplateBlock) => b.id);

      try {
        await reorderBlocksForTemplate(templateId, desiredIds);
      } catch (e) {
        // If backend rejects due strict set validation, refresh and retry once with canonical server list.
        try {
          const fresh = await fetchBlocks(templateId);
          const freshBlocks = fresh.data ?? [];
          const fromIdx = freshBlocks.findIndex((b: TemplateBlock) => b.id === draggedId);
          if (fromIdx !== -1) {
            const toIdx = Math.max(0, Math.min(boundedTargetIndex, freshBlocks.length - 1));
            const retryBlocks = [...freshBlocks];
            const [retryMoved] = retryBlocks.splice(fromIdx, 1);
            retryBlocks.splice(toIdx, 0, retryMoved);
            const retryIds = retryBlocks.map((b: TemplateBlock) => b.id);
            await reorderBlocksForTemplate(templateId, retryIds);
            setBlocks(retryBlocks);
            return;
          }
        } catch {
          // fallback below
        }

        // TODO: send to error tracker
        setBlocks(snapshot); // revert on failure
        setError(formatError(e));
      }
    },
  };
}
