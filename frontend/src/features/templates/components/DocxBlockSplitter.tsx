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
import { useCallback, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  EditorContentHtml,
  docxToHtml,
  splitHtmlIntoBlocks,
  type BlockChunk,
  type BlockChunkType,
} from '@ceedcv-maya/shared-editor-react';
import { Button } from '@ceedcv-maya/shared-ui-react';

export interface DocxBlockSplitterProps {
  open: boolean;
  onCancel: () => void;
  /** Llamado con la lista final de bloques al pulsar "Crear". */
  onConfirm: (blocks: Array<{ name: string; html: string }>) => Promise<void>;
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
    setFilename(file.name);
    try {
      const html = await docxToHtml(file);
      const parsed = splitHtmlIntoBlocks(html).filter((c) => !c.isEmpty);
      setChunks(parsed);
      setSelected(new Set());
      setAssignments(new Map());
      setTargets([]);
      blockCounter.current = 0;
      setStatus('ready');
      if (parsed.length === 0) setError('El documento no contiene contenido importable.');
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
    const payload = targets
      .map((t) => ({
        name: t.name.trim() || 'Bloque',
        html: chunksByTarget(t.id)
          .map((c) => c.html)
          .join('\n'),
      }))
      .filter((b) => b.html.length > 0);
    if (payload.length === 0) return;
    setStatus('creating');
    setProgress({ current: 0, total: payload.length });
    try {
      // onConfirm crea secuencialmente; el progreso real lo refleja el wizard,
      // aquí mostramos el total mientras corre.
      await onConfirm(payload);
      reset();
      onCancel();
    } catch (e) {
      console.error('[DocxBlockSplitter] create failed', e);
      setStatus('ready');
      setProgress(null);
      setError('Falló la creación de bloques. Revisa los que se hayan creado.');
    }
  }, [targets, chunksByTarget, onConfirm, reset, onCancel]);

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
          <div className="flex min-h-0 flex-1">
            {/* Columna izquierda — elementos */}
            <div className="flex w-3/5 flex-col border-r border-ui-border dark:border-ui-dark-border">
              <div className="flex flex-wrap items-center gap-2 border-b border-ui-border px-4 py-2 dark:border-ui-dark-border">
                <span className="text-xs text-text-muted dark:text-text-dark-muted">
                  {chunks.length} elementos · {selected.size} seleccionados
                </span>
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
