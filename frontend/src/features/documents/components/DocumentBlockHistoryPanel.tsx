import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import type { DocumentReviewCycleSnapshot } from '../../../types/documents';

// ─── Diff utilities ───────────────────────────────────────────────────────────

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

function normalizeText(s: string): string {
  return s.trim().replace(/\s+/g, ' ');
}

function extractTextLines(content: unknown): string[] {
  const blocks = normalizeBlockContentForEditor(content);
  if (!Array.isArray(blocks)) return [];
  const lines: string[] = [];
  for (const block of blocks) {
    const text = normalizeText(extractBlockText(block));
    if (text) lines.push(text);
    const b = block as Record<string, unknown>;
    const children = Array.isArray(b.children) ? b.children : [];
    for (const child of children) {
      const ct = normalizeText(extractBlockText(child));
      if (ct) lines.push('  ' + ct);
    }
  }
  return lines;
}

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

// ─── Sub-components ───────────────────────────────────────────────────────────

function DiffLines({ lines }: { lines: DiffLine[] }) {
  return (
    <>
      {lines.map((line, li) => (
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
      ))}
    </>
  );
}

// ─── Types ────────────────────────────────────────────────────────────────────

type CycleEntry = {
  cycle: number;
  submitted_at: string;
  diffContent: DiffLine[];
};

type Props = {
  blockId: string;
  blockNumber: number | string;
  history: DocumentReviewCycleSnapshot[];
  onClose: () => void;
};

// ─── Component ────────────────────────────────────────────────────────────────

export function DocumentBlockHistoryPanel({ blockId, blockNumber, history, onClose }: Props) {
  const { t, i18n } = useTranslation('documents');
  const [ascending, setAscending] = useState(false);

  const entriesChron = useMemo((): CycleEntry[] => {
    return history
      .map((cycle, i) => {
        const block = cycle.blocks.find((b) => b.document_block_id === blockId) ?? null;
        if (!block) return null;
        const prevBlock =
          i > 0 ? (history[i - 1].blocks.find((b) => b.document_block_id === blockId) ?? null) : null;

        const cur = extractTextLines(block.content);
        const diff = prevBlock
          ? computeLineDiff(extractTextLines(prevBlock.content), cur).filter((l) => l.type !== 'unchanged')
          : cur.map((text) => ({ type: 'added' as const, text }));

        if (diff.length === 0) return null;
        return { cycle: cycle.cycle, submitted_at: cycle.submitted_at, diffContent: diff };
      })
      .filter((e): e is CycleEntry => e !== null);
  }, [history, blockId]);

  const entries = useMemo(
    () => (ascending ? entriesChron : [...entriesChron].reverse()),
    [entriesChron, ascending],
  );

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-4 py-3 gap-2">
        <span className="text-2xs font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary flex-1">
          {t('history.panelHeader', { n: blockNumber })}
        </span>
        <button
          type="button"
          onClick={() => setAscending((v) => !v)}
          className="flex items-center gap-1 text-2xs font-black uppercase tracking-widest text-text-muted hover:text-odoo-teal transition-colors cursor-pointer"
          title={ascending ? t('history.sortAscendingTitle') : t('history.sortDescendingTitle')}
        >
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            {ascending ? (
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
            ) : (
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
            )}
          </svg>
        </button>
        <button
          type="button"
          onClick={onClose}
          aria-label={t('history.closePanelAria')}
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto divide-y divide-ui-border dark:divide-ui-dark-border">
        {entries.length === 0 ? (
          <p className="py-8 text-center text-xs text-text-muted dark:text-text-dark-muted italic">
            {t('history.noChanges')}
          </p>
        ) : (
          entries.map((entry) => (
            <div key={entry.cycle} className="px-4 py-3">
              <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                  {t('history.revision', { n: entry.cycle })}
                </p>
                <span className="text-2xs text-text-muted dark:text-text-dark-muted tabular-nums">
                  {new Date(entry.submitted_at).toLocaleString(i18n.language, {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
              <div className="rounded overflow-hidden border border-ui-border dark:border-ui-dark-border text-2xs font-mono">
                <DiffLines lines={entry.diffContent} />
              </div>
            </div>
          ))
        )}
      </div>

      {/* Footer legend */}
      {entries.length > 0 && (
        <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border px-4 py-2 flex items-center gap-3 text-2xs text-text-muted dark:text-text-dark-muted bg-ui-body/30 dark:bg-ui-dark-bg/30">
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-danger/25 border border-danger/40" />
            {t('diff.legendRemoved')}
          </span>
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-success/25 border border-success/40" />
            {t('diff.legendAdded')}
          </span>
          <span className="ml-auto font-semibold">
            {t('history.revisions', { count: entries.length })}
          </span>
        </div>
      )}
    </div>
  );
}
