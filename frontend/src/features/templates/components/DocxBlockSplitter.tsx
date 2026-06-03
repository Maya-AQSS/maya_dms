/**
 * DocxBlockSplitter — modal que importa un `.docx`, lo trocea en elementos
 * top-level y deja al usuario agruparlos en N bloques de plantilla.
 *
 * Capas: la conversión (`docxToHtml`) y el troceo (`splitHtmlIntoBlocks`)
 * viven en `@ceedcv-maya/shared-editor-react` (dominio-agnóstico). Este modal
 * solo orquesta selección/asignación y delega la creación vía `onConfirm`,
 * que recibe `{ name, html }[]` — el wizard convierte cada `html` a doc TipTap
 * y llama `createBlock`.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  docxToHtmlResult,
  splitHtmlIntoBlocks,
  type BlockChunk,
} from '@ceedcv-maya/shared-editor-react';
import { Header } from './DocxBlockSplitter/Header';
import { FileUploadSection } from './DocxBlockSplitter/FileUploadSection';
import { ReadyContentPanel } from './DocxBlockSplitter/ReadyContentPanel';
import { Footer } from './DocxBlockSplitter/Footer';
import { toggleSelection } from './DocxBlockSplitter/selectionUtils';
import { groupChunksByTarget, autoSplitByHeadingLevel } from './DocxBlockSplitter/groupingUtils';
import type { TargetBlock, Status } from './DocxBlockSplitter/types';

export interface DocxBlockSplitterProps {
  open: boolean;
  onCancel: () => void;
  /**
   * Crea los bloques (secuencialmente, en orden) y devuelve cuántos se
   * crearon. Un `createdCount < blocks.length` indica fallo parcial: el modal
   * deja los bloques no creados para reintentar.
   */
  onConfirm: (blocks: Array<{ name: string; html: string }>) => Promise<{ createdCount: number }>;
  isDark?: boolean;
}

export function DocxBlockSplitter({ open, onCancel, onConfirm, isDark = false }: DocxBlockSplitterProps) {
  const [filename, setFilename] = useState<string | null>(null);
  const [chunks, setChunks] = useState<BlockChunk[]>([]);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [assignments, setAssignments] = useState<Map<number, string>>(new Map());
  const [targets, setTargets] = useState<TargetBlock[]>([]);
  const [status, setStatus] = useState<Status>('idle');
  const [progress, setProgress] = useState<{ current: number; total: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [warning, setWarning] = useState<string | null>(null);

  const blockCounter = useRef(0);
  const lastClicked = useRef<number | null>(null);

  const reset = useCallback(() => {
    setFilename(null);
    setChunks([]);
    setSelected(new Set());
    setAssignments(new Map());
    setTargets([]);
    setStatus('idle');
    setProgress(null);
    setError(null);
    setWarning(null);
    blockCounter.current = 0;
    lastClicked.current = null;
  }, []);

  const handleCancel = useCallback(() => {
    reset();
    onCancel();
  }, [reset, onCancel]);

  const handlePickFile = useCallback(async (file: File) => {
    setStatus('parsing');
    setError(null);
    setWarning(null);
    setFilename(file.name);
    try {
      const { html, messages } = await docxToHtmlResult(file);
      const parsed = splitHtmlIntoBlocks(html).filter((c: BlockChunk) => !c.isEmpty);
      setChunks(parsed);
      setSelected(new Set());
      setAssignments(new Map());
      setTargets([]);
      blockCounter.current = 0;
      setStatus('ready');
      if (parsed.length === 0) setError('El documento no contiene contenido importable.');
      const warns = messages.filter((m: { type: string }) => m.type === 'warning' || m.type === 'error');
      if (warns.length > 0) {
        setWarning(
          `Word generó ${warns.length} aviso(s) de conversión. Algún formato (control de cambios, estilos no estándar) puede no haberse importado.`,
        );
      }
    } catch (e) {
      console.error('[DocxBlockSplitter] parse failed', e);
      setStatus('error');
      setError('No se pudo leer el archivo. ¿Es un .docx válido?');
    }
  }, []);

  const handleToggleSelection = useCallback(
    (index: number, mode: 'single' | 'toggle' | 'range') => {
      setSelected((prev) => {
        const next = toggleSelection(prev, index, mode, lastClicked.current);
        lastClicked.current = index;
        return next;
      });
    },
    [],
  );

  const newTargetId = useCallback(() => `tb-${++blockCounter.current}`, []);

  const assignSelection = useCallback(
    (target: string | 'new') => {
      if (selected.size === 0) return;
      let targetId = target;
      if (target === 'new') {
        targetId = newTargetId();
        setTargets((prev) => [...prev, { id: targetId, name: `Bloque ${prev.length + 1}` }]);
      }
      setAssignments((prev) => {
        const next = new Map(prev);
        for (const idx of selected) next.set(idx, targetId);
        return next;
      });
      setSelected(new Set());
    },
    [selected, newTargetId],
  );

  const unassignSelection = useCallback(() => {
    if (selected.size === 0) return;
    setAssignments((prev) => {
      const next = new Map(prev);
      for (const idx of selected) next.delete(idx);
      return next;
    });
    setSelected(new Set());
  }, [selected]);

  const renameBlock = useCallback((id: string, name: string) => {
    setTargets((prev) => prev.map((t) => (t.id === id ? { ...t, name } : t)));
  }, []);

  const removeBlock = useCallback((id: string) => {
    setTargets((prev) => prev.filter((t) => t.id !== id));
    setAssignments((prev) => {
      const next = new Map(prev);
      for (const [idx, tid] of prev) if (tid === id) next.delete(idx);
      return next;
    });
  }, []);

  const selectAll = useCallback(() => {
    setSelected(new Set(chunks.map((c) => c.index)));
  }, [chunks]);

  const hasHeadings = useMemo(() => chunks.some((c) => c.type === 'heading'), [chunks]);

  const autoSplitByHeading = useCallback(
    (level: number) => {
      const { targets: newTargets, assignments: newAssignments, nextCounter } = autoSplitByHeadingLevel(
        chunks,
        level,
        blockCounter.current,
      );
      blockCounter.current = nextCounter;
      setTargets(newTargets);
      setAssignments(newAssignments);
      setSelected(new Set());
    },
    [chunks],
  );

  const chunksByTarget = useCallback(
    (targetId: string): BlockChunk[] => groupChunksByTarget(chunks, assignments, targetId),
    [chunks, assignments],
  );

  const canConfirm = useMemo(
    () =>
      targets.length > 0 &&
      targets.every((t) => chunks.some((c) => assignments.get(c.index) === t.id)) &&
      status === 'ready',
    [targets, chunks, assignments, status],
  );

  const handleConfirm = useCallback(async () => {
    const ordered = targets.filter((t) => chunksByTarget(t.id).length > 0);
    const payload = ordered.map((t) => ({
      name: t.name.trim() || 'Bloque',
      html: chunksByTarget(t.id)
        .map((c) => c.html)
        .join('\n'),
    }));
    if (payload.length === 0) return;
    setStatus('creating');
    setError(null);
    setProgress({ current: 0, total: payload.length });
    try {
      const { createdCount } = await onConfirm(payload);
      if (createdCount >= payload.length) {
        reset();
        onCancel();
        return;
      }
      const createdTargets = ordered.slice(0, createdCount);
      const createdIds = new Set(createdTargets.map((t) => t.id));
      setTargets((prev) => prev.filter((t) => !createdIds.has(t.id)));
      setAssignments((prev) => {
        const next = new Map(prev);
        for (const [idx, tid] of prev) if (createdIds.has(tid)) next.delete(idx);
        return next;
      });
      setStatus('ready');
      setProgress(null);
      setError(
        `${createdCount} bloque(s) creados. ${payload.length - createdCount} fallaron — revisa y pulsa "Crear" para reintentar.`,
      );
    } catch (e) {
      console.error('[DocxBlockSplitter] create failed', e);
      setStatus('ready');
      setProgress(null);
      setError('Falló la creación de bloques. Revisa los que se hayan creado y reintenta.');
    }
  }, [targets, chunksByTarget, onConfirm, reset, onCancel]);

  // Atajos de teclado: Esc cierra, Ctrl/Cmd+A selecciona todo, Enter crea.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const typing = target && ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName);
      if (e.key === 'Escape') {
        if (status !== 'creating') handleCancel();
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a' && !typing && status === 'ready') {
        e.preventDefault();
        selectAll();
      } else if (e.key === 'Enter' && !typing && canConfirm) {
        e.preventDefault();
        void handleConfirm();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, status, canConfirm, handleCancel, selectAll, handleConfirm]);

  if (!open) return null;

  return createPortal(
    <div
      className={['fixed inset-0 z-[9998] flex items-center justify-center bg-black/50 p-4', isDark ? 'dark' : ''].join(' ')}
      role="dialog"
      aria-modal="true"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) handleCancel();
      }}
    >
      <div className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-xl dark:bg-ui-dark-card">
        <Header filename={filename} onCancel={handleCancel} />

        {status === 'idle' || status === 'parsing' || status === 'error' ? (
          <FileUploadSection status={status} error={error} onPickFile={handlePickFile} />
        ) : (
          <ReadyContentPanel
            chunks={chunks}
            selected={selected}
            assignments={assignments}
            targets={targets}
            warning={warning}
            hasHeadings={hasHeadings}
            onToggleSelection={(idx, mode) => handleToggleSelection(idx, mode)}
            onSelectAll={selectAll}
            onAutoSplitByHeading={autoSplitByHeading}
            onAssignSelection={assignSelection}
            onUnassignSelection={unassignSelection}
            onRenameBlock={renameBlock}
            onRemoveBlock={removeBlock}
            chunksByTarget={chunksByTarget}
          />
        )}

        <Footer
          status={status}
          progress={progress}
          targetCount={targets.length}
          canConfirm={canConfirm}
          onCancel={handleCancel}
          onConfirm={() => void handleConfirm()}
        />
      </div>
    </div>,
    document.body,
  );
}
