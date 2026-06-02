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
  EditorContentHtml,
  docxToHtmlResult,
  splitHtmlIntoBlocks,
  type BlockChunk,
  type BlockChunkType,
} from '@ceedcv-maya/shared-editor-react';
import { Button } from '@ceedcv-maya/shared-ui-react';

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

interface TargetBlock {
  id: string;
  name: string;
}

type Status = 'idle' | 'parsing' | 'ready' | 'creating' | 'error';

const TYPE_BADGE: Record<BlockChunkType, string> = {
  heading: 'H',
  paragraph: '¶',
  list: '•',
  table: '▦',
  figure: '🖼',
  blockquote: '❝',
  codeBlock: '</>',
  horizontalRule: '―',
  other: '?',
};

const TYPE_LABEL: Record<BlockChunkType, string> = {
  heading: 'Encabezado',
  paragraph: 'Párrafo',
  list: 'Lista',
  table: 'Tabla',
  figure: 'Figura',
  blockquote: 'Cita',
  codeBlock: 'Código',
  horizontalRule: 'Separador',
  other: 'Elemento',
};

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
  const fileInputRef = useRef<HTMLInputElement | null>(null);

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
      const parsed = splitHtmlIntoBlocks(html).filter((c) => !c.isEmpty);
      setChunks(parsed);
      setSelected(new Set());
      setAssignments(new Map());
      setTargets([]);
      blockCounter.current = 0;
      setStatus('ready');
      if (parsed.length === 0) setError('El documento no contiene contenido importable.');
      const warns = messages.filter((m) => m.type === 'warning' || m.type === 'error');
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

  const toggleSelection = useCallback(
    (index: number, mode: 'single' | 'toggle' | 'range') => {
      setSelected((prev) => {
        const next = new Set(prev);
        if (mode === 'single') {
          next.clear();
          next.add(index);
        } else if (mode === 'toggle') {
          next.has(index) ? next.delete(index) : next.add(index);
        } else {
          const from = lastClicked.current ?? index;
          const [lo, hi] = from <= index ? [from, index] : [index, from];
          for (let i = lo; i <= hi; i++) next.add(i);
        }
        return next;
      });
      lastClicked.current = index;
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

  /**
   * Agrupa chunks consecutivos usando los encabezados de nivel `level` (o
   * superior) como separadores. Cada bloque toma el texto del encabezado como
   * nombre. Los chunks anteriores al primer encabezado quedan sin asignar.
   */
  const autoSplitByHeading = useCallback(
    (level: number) => {
      const newTargets: TargetBlock[] = [];
      const newAssignments = new Map<number, string>();
      let currentId: string | null = null;
      let n = 0;
      for (const c of chunks) {
        if (c.type === 'heading' && (c.level ?? 99) <= level) {
          currentId = `tb-${++blockCounter.current}`;
          newTargets.push({ id: currentId, name: c.text.slice(0, 60) || `Bloque ${++n}` });
        }
        if (currentId) newAssignments.set(c.index, currentId);
      }
      setTargets(newTargets);
      setAssignments(newAssignments);
      setSelected(new Set());
    },
    [chunks],
  );

  const chunksByTarget = useCallback(
    (targetId: string): BlockChunk[] =>
      chunks
        .filter((c) => assignments.get(c.index) === targetId)
        .sort((a, b) => a.index - b.index),
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
    // payload sigue el orden de `targets` (filter solo descarta vacíos, que
    // canConfirm ya impide), así los primeros `createdCount` mapean 1:1.
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
      // Fallo parcial: quita los bloques ya creados (los primeros), deja el
      // resto para reintentar.
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
      const typing =
        target && ['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName);
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

  const assignedCount = assignments.size;

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
        {/* Cabecera */}
        <div className="flex items-center justify-between border-b border-ui-border px-5 py-3 dark:border-ui-dark-border">
          <div>
            <h2 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
              Importar bloques desde Word
            </h2>
            {filename && <p className="text-xs text-text-muted dark:text-text-dark-muted">{filename}</p>}
          </div>
          <button
            type="button"
            onClick={handleCancel}
            className="text-text-muted hover:text-text-primary dark:text-text-dark-muted dark:hover:text-text-dark-primary"
            aria-label="Cerrar"
          >
            ✕
          </button>
        </div>

        {/* Cuerpo */}
        {status === 'idle' || status === 'parsing' || status === 'error' ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 p-10">
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              Selecciona un archivo <strong>.docx</strong> para trocearlo en bloques de plantilla.
            </p>
            <Button
              variant="primary"
              disabled={status === 'parsing'}
              onClick={() => fileInputRef.current?.click()}
            >
              {status === 'parsing' ? 'Procesando…' : 'Elegir archivo .docx'}
            </Button>
            {error && <p className="text-sm text-danger-dark">{error}</p>}
            <input
              ref={fileInputRef}
              type="file"
              accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0];
                e.target.value = '';
                if (file) void handlePickFile(file);
              }}
            />
          </div>
        ) : (
          <div className="flex min-h-0 flex-1 flex-col">
            {warning && (
              <div className="border-b border-warning-dark/30 bg-warning-dark/10 px-4 py-2 text-xs text-warning-dark">
                ⚠ {warning}
              </div>
            )}
            <div className="flex min-h-0 flex-1">
            {/* Columna izquierda — elementos */}
            <div className="flex w-3/5 flex-col border-r border-ui-border dark:border-ui-dark-border">
              <div className="flex flex-wrap items-center gap-2 border-b border-ui-border px-4 py-2 dark:border-ui-dark-border">
                <span className="text-xs text-text-muted dark:text-text-dark-muted">
                  {chunks.length} elementos · {selected.size} seleccionados
                </span>
                <Button variant="outline" disabled={chunks.length === 0} onClick={selectAll}>
                  Todos
                </Button>
                {hasHeadings && (
                  <>
                    <Button variant="outline" onClick={() => autoSplitByHeading(1)} title="Un bloque por cada H1">
                      Auto H1
                    </Button>
                    <Button variant="outline" onClick={() => autoSplitByHeading(2)} title="Un bloque por cada H1/H2">
                      Auto H2
                    </Button>
                  </>
                )}
                <div className="ml-auto flex gap-2">
                  <Button variant="outline" disabled={selected.size === 0} onClick={() => assignSelection('new')}>
                    + Nuevo bloque
                  </Button>
                  {targets.length > 0 && (
                    <select
                      className="rounded border border-ui-border bg-white px-2 text-xs dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-primary"
                      value=""
                      disabled={selected.size === 0}
                      onChange={(e) => {
                        if (e.target.value) assignSelection(e.target.value);
                        e.target.value = '';
                      }}
                    >
                      <option value="">Asignar a…</option>
                      {targets.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.name}
                        </option>
                      ))}
                    </select>
                  )}
                  <Button variant="outline" disabled={selected.size === 0} onClick={unassignSelection}>
                    Desasignar
                  </Button>
                </div>
              </div>
              <div className="min-h-0 flex-1 overflow-y-auto p-2">
                {chunks.map((c) => {
                  const targetId = assignments.get(c.index);
                  const target = targets.find((t) => t.id === targetId);
                  const isSel = selected.has(c.index);
                  return (
                    <button
                      key={c.index}
                      type="button"
                      onClick={(e) =>
                        toggleSelection(
                          c.index,
                          e.shiftKey ? 'range' : e.ctrlKey || e.metaKey ? 'toggle' : 'single',
                        )
                      }
                      className={[
                        'mb-1 flex w-full items-start gap-2 rounded border px-2 py-1.5 text-left text-sm transition-colors',
                        isSel
                          ? 'border-odoo-purple bg-odoo-purple/10'
                          : 'border-ui-border hover:bg-ui-bg dark:border-ui-dark-border dark:hover:bg-ui-dark-bg',
                      ].join(' ')}
                    >
                      <span
                        className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-ui-bg text-xs text-text-muted dark:bg-ui-dark-bg dark:text-text-dark-muted"
                        title={TYPE_LABEL[c.type]}
                      >
                        {c.type === 'heading' && c.level ? `H${c.level}` : TYPE_BADGE[c.type]}
                      </span>
                      <span className="min-w-0 flex-1 truncate text-text-primary dark:text-text-dark-primary">
                        {c.text || <em className="text-text-muted">({TYPE_LABEL[c.type]})</em>}
                      </span>
                      {target && (
                        <span className="shrink-0 rounded bg-odoo-purple/15 px-1.5 text-xs text-odoo-purple">
                          {target.name}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Columna derecha — bloques destino */}
            <div className="flex w-2/5 flex-col">
              <div className="border-b border-ui-border px-4 py-2 text-xs text-text-muted dark:border-ui-dark-border dark:text-text-dark-muted">
                {targets.length} bloques destino · {assignedCount}/{chunks.length} elementos asignados
              </div>
              <div className="min-h-0 flex-1 overflow-y-auto p-3">
                {targets.length === 0 ? (
                  <p className="p-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
                    Selecciona elementos a la izquierda y pulsa <strong>+ Nuevo bloque</strong>.
                  </p>
                ) : (
                  targets.map((t) => {
                    const tc = chunksByTarget(t.id);
                    return (
                      <div
                        key={t.id}
                        className="mb-3 rounded border border-ui-border p-2 dark:border-ui-dark-border"
                      >
                        <div className="mb-2 flex items-center gap-2">
                          <input
                            value={t.name}
                            onChange={(e) => renameBlock(t.id, e.target.value)}
                            className="min-w-0 flex-1 rounded border border-ui-border px-2 py-1 text-sm dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary"
                          />
                          <span className="shrink-0 text-xs text-text-muted dark:text-text-dark-muted">
                            {tc.length} el.
                          </span>
                          <button
                            type="button"
                            onClick={() => removeBlock(t.id)}
                            className="shrink-0 text-danger-dark hover:opacity-70"
                            aria-label="Eliminar bloque"
                          >
                            🗑
                          </button>
                        </div>
                        {tc.length === 0 ? (
                          <p className="text-xs text-warning-dark">Sin elementos — asígnale alguno.</p>
                        ) : (
                          <div className="max-h-40 overflow-y-auto rounded bg-ui-bg p-2 text-xs dark:bg-ui-dark-bg">
                            <EditorContentHtml html={tc.map((c) => c.html).join('\n')} />
                          </div>
                        )}
                      </div>
                    );
                  })
                )}
              </div>
            </div>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="flex items-center justify-between border-t border-ui-border px-5 py-3 dark:border-ui-dark-border">
          <div className="text-xs text-text-muted dark:text-text-dark-muted">
            {status === 'creating' && progress
              ? `Creando ${progress.total} bloque(s)…`
              : status === 'ready' && !canConfirm && targets.length > 0
                ? 'Cada bloque debe tener al menos un elemento.'
                : ''}
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleCancel} disabled={status === 'creating'}>
              Cancelar
            </Button>
            <Button variant="primary" onClick={() => void handleConfirm()} disabled={!canConfirm}>
              {status === 'creating' ? 'Creando…' : `Crear ${targets.length || ''} bloque${targets.length === 1 ? '' : 's'}`}
            </Button>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
}
