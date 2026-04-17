import { useCallback, useEffect, useState } from 'react';
import {
  bulkUpdateBlocks,
  createBlock as createBlockRequest,
  deleteBlock as deleteBlockRequest,
  fetchBlocks,
  reorderBlocksForTemplate,
  updateBlock as updateBlockRequest,
} from '../../../api/blocks';
import { ApiHttpError } from '../../../api/http';
import type {
  BlockState,
  BulkUpdateBlockPayload,
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

export function useTemplateBlocks(templateId: string) {
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // IDs seleccionados en el outline (selección múltiple)
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchBlocks(templateId);
      setBlocks(res.data);
    } catch (e) {
      setError(formatError(e));
      setBlocks([]);
    } finally {
      setLoading(false);
    }
  }, [templateId]);

  useEffect(() => {
    void load();
  }, [load]);

  const createBlock = useCallback(
    async (payload: CreateBlockPayload) => {
      const res = await createBlockRequest(templateId, payload);
      setBlocks((prev) => [...prev, res.data]);
      return res.data;
    },
    [templateId],
  );

  const updateBlock = useCallback(
    async (blockId: string, payload: UpdateBlockPayload) => {
      const res = await updateBlockRequest(blockId, payload);
      setBlocks((prev) => prev.map((b) => (b.id === blockId ? res.data : b)));
      return res.data;
    },
    [],
  );

  const deleteBlock = useCallback(async (blockId: string) => {
    await deleteBlockRequest(blockId);
    setBlocks((prev) => prev.filter((b) => b.id !== blockId));
    setSelectedIds((prev) => {
      const next = new Set(prev);
      next.delete(blockId);
      return next;
    });
  }, []);

  /**
   * Cambia el block_state de los bloques seleccionados.
   * Si hay múltiples seleccionados usa la ruta bulk; si hay uno solo usa la ruta individual.
   */
  const applyStateToSelected = useCallback(
    async (state: BlockState) => {
      const ids = Array.from(selectedIds);
      if (ids.length === 0) return;

      if (ids.length === 1) {
        const res = await updateBlockRequest(ids[0], { block_state: state });
        setBlocks((prev) => prev.map((b) => (b.id === ids[0] ? res.data : b)));
      } else {
        const payload: BulkUpdateBlockPayload = { ids, block_state: state };
        const res = await bulkUpdateBlocks(payload);
        const updated = new Map(res.data.map((b) => [b.id, b]));
        setBlocks((prev) => prev.map((b) => updated.get(b.id) ?? b));
      }
    },
    [selectedIds],
  );

  const applyMandatoryToSelected = useCallback(
    async (mandatory: boolean) => {
      const ids = Array.from(selectedIds);
      if (ids.length === 0) return;

      if (ids.length === 1) {
        const res = await updateBlockRequest(ids[0], { mandatory });
        setBlocks((prev) => prev.map((b) => (b.id === ids[0] ? res.data : b)));
      } else {
        const payload: BulkUpdateBlockPayload = { ids, mandatory };
        const res = await bulkUpdateBlocks(payload);
        const updated = new Map(res.data.map((b) => [b.id, b]));
        setBlocks((prev) => prev.map((b) => updated.get(b.id) ?? b));
      }
    },
    [selectedIds],
  );

  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => {
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
    applyStateToSelected,
    applyMandatoryToSelected,
    toggleSelect,
    selectOnly,
    clearSelection,
    reorderBlocks: async (draggedId: string, targetIndex: number) => {
      const sourceIndex = blocks.findIndex(b => b.id === draggedId);
      if (sourceIndex === -1) return;
      const snapshot = blocks;
      const newBlocks = [...blocks];
      const [movedBlock] = newBlocks.splice(sourceIndex, 1);
      newBlocks.splice(targetIndex, 0, movedBlock);
      setBlocks(newBlocks); // optimistic update
      try {
        await reorderBlocksForTemplate(templateId, newBlocks.map(b => b.id));
      } catch (e) {
        console.error('Failed to persist reorder:', e);
        setBlocks(snapshot); // revert on failure
        setError(formatError(e));
      }
    },
  };
}
