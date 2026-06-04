import { useMemo, useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { computeChangedBlocks } from './DocumentDiffModal';
import { documentBlockContentDiffersFromTemplateDefault } from '../lib/blockContentEquals';
import {
  diffTiptapAgainstTemplateDefault,
  diffTiptapRemovedContent,
  type TiptapDiffLine,
} from '../lib/tiptapLineDiff';
import type { DocumentDisplayBlock } from '../../../types/documents';

type DiffLine = TiptapDiffLine;

// ── Sub-components ────────────────────────────────────────────────────────────

function DiffLines({ lines }: { lines: DiffLine[] }) {
  const { t } = useTranslation('documents');
  if (lines.length === 0) {
    return (
      <p className="px-2 py-1 text-text-muted italic text-2xs">
        {t('diff.noChangesInSubmission')}
      </p>
    );
  }
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

function blockStateLabel(
  block: DocumentDisplayBlock,
  t: (key: string) => string,
): string {
  if (block.is_deleted) return t('diff.states.deleted');
  if (block.block_state === 'modifiable') return t('diff.states.modifiableEdited');
  if (block.block_state === 'editable') return t('diff.states.editableEdited');
  if (block.block_state === 'optional') return t('diff.states.optionalEdited');
  return t('diff.states.edited');
}

// ── Component ─────────────────────────────────────────────────────────────────

type Props = {
  /** Bloques a mostrar en el diff (p. ej. solo el bloque seleccionado). */
  blocks: DocumentDisplayBlock[];
  /** Lista completa del documento para numerar bloques; si no se pasa, se usa `blocks`. */
  allBlocks?: DocumentDisplayBlock[];
  onClose: () => void;
};

export function DocumentDiffPanel({ blocks, allBlocks, onClose }: Props) {
  const { t } = useTranslation('documents');
  const [ascending, setAscending] = useState(true);
  const [focusedIdx, setFocusedIdx] = useState(0);
  const blockRefs = useRef<(HTMLDivElement | null)[]>([]);

  const numberingBlocks = allBlocks ?? blocks;
  const changedBlocks = useMemo(() => computeChangedBlocks(blocks), [blocks]);

  const diffLines = useMemo(
    () =>
      changedBlocks.map((b): DiffLine[] => {
        if (b.is_deleted) {
          return diffTiptapRemovedContent(b.default_content, t('diff.emptyBlock'));
        }
        return diffTiptapAgainstTemplateDefault(b.default_content, b.content, {
          fallbackLine: t('diff.richContentChanged'),
          hasNonTextDiff: documentBlockContentDiffersFromTemplateDefault(
            b.content,
            b.default_content,
          ),
        });
      }),
    [changedBlocks, t],
  );

  const pairsChron = useMemo(
    (): { block: DocumentDisplayBlock; lines: DiffLine[]; blockNumber: number }[] =>
      changedBlocks
        .map((block, idx) => ({
          block,
          lines: diffLines[idx] ?? [],
          blockNumber:
            numberingBlocks.findIndex(b => b.template_block_id === block.template_block_id) + 1,
        }))
        .filter(({ lines }) => lines.length > 0),
    [changedBlocks, diffLines, numberingBlocks],
  );

  const pairs = useMemo(
    () => (ascending ? pairsChron : [...pairsChron].reverse()),
    [pairsChron, ascending],
  );

  useEffect(() => {
    setFocusedIdx(0);
  }, [ascending]);

  useEffect(() => {
    blockRefs.current[focusedIdx]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }, [focusedIdx]);

  const total = pairs.length;

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-4 py-3 gap-2">
        <span className="text-2xs font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary flex-1">
          {t('diff.panelHeader')}
        </span>
        <button
          type="button"
          onClick={() => setAscending((v) => !v)}
          className="flex items-center gap-1 text-2xs font-black uppercase tracking-widest text-text-muted hover:text-odoo-teal transition-colors cursor-pointer"
          title={ascending ? t('diff.sortAscendingTitle') : t('diff.sortDescendingTitle')}
        >
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            {ascending
              ? <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
              : <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
            }
          </svg>
        </button>
        <button
          type="button"
          onClick={onClose}
          aria-label={t('diff.closePanel')}
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto divide-y divide-ui-border dark:divide-ui-dark-border">
        {total === 0 ? (
          <p className="py-8 text-center text-xs text-text-muted dark:text-text-dark-muted italic">
            {t('diff.noChangesVsTemplate')}
          </p>
        ) : (
          pairs.map(({ block, lines, blockNumber }, idx) => {
            const isFocused = idx === focusedIdx;
            return (
              <div
                key={block.template_block_id}
                ref={el => { blockRefs.current[idx] = el; }}
                onClick={() => setFocusedIdx(idx)}
                className={`px-4 py-3 cursor-pointer transition-colors ${
                  isFocused
                    ? 'bg-odoo-purple/5 dark:bg-odoo-purple/10'
                    : 'hover:bg-ui-body/50 dark:hover:bg-ui-dark-bg/50'
                }`}
              >
                <div className="flex items-center justify-between mb-2">
                  <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                    {t('diff.blockLabel', { n: blockNumber, title: block.title ?? t('diff.untitled') })}
                  </p>
                  <span className="text-2xs text-text-muted dark:text-text-dark-muted uppercase tracking-wider">
                    {blockStateLabel(block, t)}
                  </span>
                </div>
                <div className="rounded overflow-hidden border border-ui-border dark:border-ui-dark-border text-2xs font-mono">
                  <DiffLines lines={lines} />
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Footer legend */}
      {total > 0 && (
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
            {t('diff.blocksWithChanges', { count: total })}
          </span>
        </div>
      )}
    </div>
  );
}
