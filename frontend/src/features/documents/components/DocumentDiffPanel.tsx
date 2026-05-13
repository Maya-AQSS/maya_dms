import { useMemo, useState, useRef, useEffect } from 'react';
import { computeChangedBlocks } from './DocumentDiffModal';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import type { DocumentDisplayBlock } from '../../../types/documents';

// ── Text extraction ───────────────────────────────────────────────────────────

function extractBlockText(block: unknown): string {
  if (!block || typeof block !== 'object') return '';
  const b = block as Record<string, unknown>;
  const content = Array.isArray(b.content) ? b.content : [];
  const inline = content
    .map((c: unknown) => {
      if (!c || typeof c !== 'object') return '';
      const item = c as Record<string, unknown>;
      if (item.type === 'text') return String(item.text ?? '');
      if (item.type === 'link') {
        const lc = Array.isArray(item.content) ? item.content : [];
        return lc.map((x: unknown) => String((x as Record<string, unknown>).text ?? '')).join('');
      }
      return '';
    })
    .join('');
  const type = String(b.type ?? '');
  const props = (b.props ?? {}) as Record<string, unknown>;
  let prefix = '';
  if (type === 'heading') prefix = '#'.repeat(Number(props.level ?? 1)) + ' ';
  else if (type === 'bulletListItem') prefix = '• ';
  else if (type === 'numberedListItem') prefix = '1. ';
  return prefix + inline;
}

function extractTextLines(content: unknown): string[] {
  const blocks = normalizeBlockContentForEditor(content);
  if (!Array.isArray(blocks)) return [];
  const lines: string[] = [];
  for (const block of blocks) {
    const text = extractBlockText(block);
    if (text.trim()) lines.push(text);
    const b = block as Record<string, unknown>;
    const children = Array.isArray(b.children) ? b.children : [];
    for (const child of children) {
      const ct = extractBlockText(child);
      if (ct.trim()) lines.push('  ' + ct);
    }
  }
  return lines;
}

// ── LCS diff ─────────────────────────────────────────────────────────────────

type DiffLine = { type: 'removed' | 'added' | 'unchanged'; text: string };

function computeLineDiff(original: string[], modified: string[]): DiffLine[] {
  const m = original.length;
  const n = modified.length;
  const dp = Array.from({ length: m + 1 }, () => new Array<number>(n + 1).fill(0));
  for (let i = 1; i <= m; i++)
    for (let j = 1; j <= n; j++)
      dp[i][j] =
        original[i - 1] === modified[j - 1]
          ? dp[i - 1][j - 1] + 1
          : Math.max(dp[i - 1][j], dp[i][j - 1]);
  const result: DiffLine[] = [];
  let i = m;
  let j = n;
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && original[i - 1] === modified[j - 1]) {
      result.unshift({ type: 'unchanged', text: original[i - 1] });
      i--;
      j--;
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      result.unshift({ type: 'added', text: modified[j - 1] });
      j--;
    } else {
      result.unshift({ type: 'removed', text: original[i - 1] });
      i--;
    }
  }
  return result;
}

// ── Block label ───────────────────────────────────────────────────────────────

function blockStateLabel(block: DocumentDisplayBlock): string {
  if (block.is_deleted) return 'Bloque opcional · eliminado';
  if (block.block_state === 'modifiable') return 'Bloque modificable · editado';
  if (block.block_state === 'editable') return 'Bloque editable · editado';
  if (block.block_state === 'optional') return 'Bloque opcional · editado';
  return 'Bloque · editado';
}

// ── Component ─────────────────────────────────────────────────────────────────

type Props = { blocks: DocumentDisplayBlock[]; onClose: () => void };

export function DocumentDiffPanel({ blocks, onClose }: Props) {
  const changedBlocks = useMemo(() => computeChangedBlocks(blocks), [blocks]);
  const [focusedIdx, setFocusedIdx] = useState(0);
  const blockRefs = useRef<(HTMLDivElement | null)[]>([]);

  const total = changedBlocks.length;

  useEffect(() => {
    blockRefs.current[focusedIdx]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, [focusedIdx]);

  const diffLines = useMemo(
    () =>
      changedBlocks.map((b): DiffLine[] => {
        if (b.is_deleted) {
          const lines = extractTextLines(b.default_content);
          return lines.length > 0
            ? lines.map(text => ({ type: 'removed' as const, text }))
            : [{ type: 'removed' as const, text: '(bloque sin contenido)' }];
        }
        const original = extractTextLines(b.default_content);
        const modified = extractTextLines(b.content);
        return computeLineDiff(original, modified).filter(l => l.type !== 'unchanged');
      }),
    [changedBlocks],
  );

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-4 py-3 gap-2">
        <span className="text-[10px] font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary flex-1">
          ⎇ Cambios
        </span>
        {total > 1 && (
          <>
            <button
              type="button"
              disabled={focusedIdx === 0}
              onClick={() => setFocusedIdx(i => i - 1)}
              className="text-xs w-7 h-7 flex items-center justify-center rounded hover:bg-ui-body dark:hover:bg-ui-dark-bg disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              aria-label="Bloque anterior"
            >
              ↑
            </button>
            <span className="text-xs text-text-muted dark:text-text-dark-muted tabular-nums">
              {focusedIdx + 1} / {total}
            </span>
            <button
              type="button"
              disabled={focusedIdx === total - 1}
              onClick={() => setFocusedIdx(i => i + 1)}
              className="text-xs w-7 h-7 flex items-center justify-center rounded hover:bg-ui-body dark:hover:bg-ui-dark-bg disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              aria-label="Bloque siguiente"
            >
              ↓
            </button>
          </>
        )}
        <button
          type="button"
          onClick={onClose}
          aria-label="Cerrar panel"
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto">
        {total === 0 ? (
          <p className="text-xs text-text-muted dark:text-text-dark-muted text-center p-6 italic">
            No hay cambios respecto a la plantilla original.
          </p>
        ) : (
          <div className="divide-y divide-ui-border dark:divide-ui-dark-border">
            {changedBlocks.map((block, idx) => {
              const lines = diffLines[idx];
              const isFocused = idx === focusedIdx;
              return (
                <div
                  key={block.template_block_id}
                  ref={el => {
                    blockRefs.current[idx] = el;
                  }}
                  onClick={() => setFocusedIdx(idx)}
                  className={`px-4 py-3 cursor-pointer transition-colors ${
                    isFocused
                      ? 'bg-odoo-purple/5 dark:bg-odoo-purple/10'
                      : 'hover:bg-ui-body/50 dark:hover:bg-ui-dark-bg/50'
                  }`}
                >
                  {/* Block header */}
                  <div className="mb-2">
                    <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">
                      {block.title ?? 'Sin título'}
                    </p>
                    <p className="text-[10px] text-text-muted dark:text-text-dark-muted uppercase tracking-wider mt-0.5">
                      {blockStateLabel(block)}
                    </p>
                  </div>
                  {/* Diff lines */}
                  <div className="rounded overflow-hidden border border-ui-border dark:border-ui-dark-border text-[11px] font-mono">
                    {lines.length === 0 ? (
                      <p className="px-2 py-1 text-text-muted italic text-[11px]">
                        Sin cambios de texto detectados.
                      </p>
                    ) : (
                      lines.map((line, li) => (
                        <div
                          key={li}
                          className={`px-2 py-0.5 whitespace-pre-wrap break-all leading-relaxed ${
                            line.type === 'removed'
                              ? 'bg-danger/10 text-danger-dark dark:bg-danger/15 dark:text-danger'
                              : 'bg-success/10 text-success-dark dark:bg-success/15 dark:text-success'
                          }`}
                        >
                          <span className="mr-2 select-none font-bold opacity-70">
                            {line.type === 'removed' ? '−' : '+'}
                          </span>
                          {line.text}
                        </div>
                      ))
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Footer */}
      {total > 0 && (
        <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border px-4 py-2 flex items-center gap-3 text-[10px] text-text-muted dark:text-text-dark-muted bg-ui-body/30 dark:bg-ui-dark-bg/30">
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-danger/25 border border-danger/40" />
            Eliminado
          </span>
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-success/25 border border-success/40" />
            Añadido
          </span>
          <span className="ml-auto font-semibold">
            {total} bloque{total !== 1 ? 's' : ''} con cambios
          </span>
        </div>
      )}
    </div>
  );
}
