import { useCallback, useEffect, useState } from 'react';
import { createBlock as createBlockRequest, deleteBlock as deleteBlockRequest, fetchBlocks, reorderBlocksForTemplate, updateBlock as updateBlockRequest, } from '../../../api/blocks';
import { ApiHttpError } from '../../../api/http';
function formatError(err) {
    if (err instanceof ApiHttpError) {
        if (err.status === 422)
            return err.message || 'Datos no válidos.';
        if (err.status === 404)
            return 'Bloque no encontrado.';
        return err.message || `Error HTTP ${err.status}`;
    }
    return err instanceof Error ? err.message : 'Error desconocido';
}
export function useTemplateBlocks(templateId) {
    const [blocks, setBlocks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    // IDs seleccionados en el outline (selección múltiple)
    const [selectedIds, setSelectedIds] = useState(new Set());
    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetchBlocks(templateId);
            setBlocks(res.data);
        }
        catch (e) {
            setError(formatError(e));
            setBlocks([]);
        }
        finally {
            setLoading(false);
        }
    }, [templateId]);
    useEffect(() => {
        void load();
    }, [load]);
    const createBlock = useCallback(async (payload) => {
        const res = await createBlockRequest(templateId, payload);
        setBlocks((prev) => [...prev, res.data]);
        return res.data;
    }, [templateId]);
    const updateBlock = useCallback(async (blockId, payload) => {
        const res = await updateBlockRequest(blockId, payload);
        setBlocks((prev) => prev.map((b) => (b.id === blockId ? res.data : b)));
        return res.data;
    }, []);
    const deleteBlock = useCallback(async (blockId) => {
        await deleteBlockRequest(blockId);
        setBlocks((prev) => prev.filter((b) => b.id !== blockId));
        setSelectedIds((prev) => {
            const next = new Set(prev);
            next.delete(blockId);
            return next;
        });
    }, []);
    const toggleSelect = useCallback((id) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id))
                next.delete(id);
            else
                next.add(id);
            return next;
        });
    }, []);
    const selectOnly = useCallback((id) => {
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
        reorderBlocks: async (draggedId, targetIndex) => {
            const snapshot = blocks;
            const sourceIndex = snapshot.findIndex((b) => b.id === draggedId);
            if (sourceIndex === -1)
                return;
            const boundedTargetIndex = Math.max(0, Math.min(targetIndex, snapshot.length - 1));
            const newBlocks = [...snapshot];
            const [movedBlock] = newBlocks.splice(sourceIndex, 1);
            newBlocks.splice(boundedTargetIndex, 0, movedBlock);
            setBlocks(newBlocks); // optimistic update
            const desiredIds = newBlocks.map((b) => b.id);
            try {
                await reorderBlocksForTemplate(templateId, desiredIds);
            }
            catch (e) {
                // If backend rejects due strict set validation, refresh and retry once with canonical server list.
                try {
                    const fresh = await fetchBlocks(templateId);
                    const freshBlocks = fresh.data ?? [];
                    const fromIdx = freshBlocks.findIndex((b) => b.id === draggedId);
                    if (fromIdx !== -1) {
                        const toIdx = Math.max(0, Math.min(boundedTargetIndex, freshBlocks.length - 1));
                        const retryBlocks = [...freshBlocks];
                        const [retryMoved] = retryBlocks.splice(fromIdx, 1);
                        retryBlocks.splice(toIdx, 0, retryMoved);
                        const retryIds = retryBlocks.map((b) => b.id);
                        await reorderBlocksForTemplate(templateId, retryIds);
                        setBlocks(retryBlocks);
                        return;
                    }
                }
                catch {
                    // fallback below
                }
                console.error('Failed to persist reorder:', e);
                setBlocks(snapshot); // revert on failure
                setError(formatError(e));
            }
        },
    };
}
