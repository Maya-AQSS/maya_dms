import { useCallback, useEffect, useState } from 'react';

/**
 * Persistencia local (por documento) de bloques marcados como "finalizados".
 * Ayuda visual no vinculante: no afecta validaciones ni envío a revisión.
 * Indexado por `template_block_id` para que los bloques locked (sin
 * document_block_id) también puedan marcarse como revisados.
 */
function storageKey(documentId: string | null | undefined): string | null {
  if (!documentId) return null;
  return `dms.document.${documentId}.completed-blocks`;
}

function readStored(documentId: string | null | undefined): Set<string> {
  const key = storageKey(documentId);
  if (!key || typeof window === 'undefined') return new Set();
  try {
    const raw = window.localStorage.getItem(key);
    if (!raw) return new Set();
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return new Set();
    return new Set(parsed.filter((v): v is string => typeof v === 'string'));
  } catch {
    return new Set();
  }
}

export interface UseCompletedBlocksResult {
  completedIds: Set<string>;
  isCompleted: (blockKey: string | null | undefined) => boolean;
  toggle: (blockKey: string) => void;
  count: number;
}

export function useCompletedBlocks(documentId: string | null | undefined): UseCompletedBlocksResult {
  const [completedIds, setCompletedIds] = useState<Set<string>>(() => readStored(documentId));

  // Re-cargar al cambiar de documento.
  useEffect(() => {
    setCompletedIds(readStored(documentId));
  }, [documentId]);

  // Persistir cambios.
  useEffect(() => {
    const key = storageKey(documentId);
    if (!key || typeof window === 'undefined') return;
    if (completedIds.size === 0) {
      window.localStorage.removeItem(key);
      return;
    }
    window.localStorage.setItem(key, JSON.stringify(Array.from(completedIds)));
  }, [documentId, completedIds]);

  const isCompleted = useCallback(
    (blockKey: string | null | undefined): boolean => !!blockKey && completedIds.has(blockKey),
    [completedIds],
  );

  const toggle = useCallback((blockKey: string) => {
    if (!blockKey) return;
    setCompletedIds((prev) => {
      const next = new Set(prev);
      if (next.has(blockKey)) next.delete(blockKey);
      else next.add(blockKey);
      return next;
    });
  }, []);

  return { completedIds, isCompleted, toggle, count: completedIds.size };
}
