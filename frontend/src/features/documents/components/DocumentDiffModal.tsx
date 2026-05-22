import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@maya/shared-ui-react';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import type { DocumentDisplayBlock } from '../../../types/documents';

function contentsDiffer(a: unknown, b: unknown): boolean {
  const norm = (v: unknown) => JSON.stringify(normalizeBlockContentForEditor(v));
  return norm(a) !== norm(b);
}

export function computeChangedBlocks(blocks: DocumentDisplayBlock[]): DocumentDisplayBlock[] {
  return blocks.filter((b) => {
    if (b.is_deleted) return true;
    if (b.block_state === 'locked') return false;
    return contentsDiffer(b.content, b.default_content);
  });
}

type Props = {
  blocks: DocumentDisplayBlock[];
  onClose: () => void;
};

export function DocumentDiffModal({ blocks, onClose }: Props) {
  const { t } = useTranslation('documents');
  const changedBlocks = useMemo(() => computeChangedBlocks(blocks), [blocks]);
  const [idx, setIdx] = useState(0);

  const current = changedBlocks[idx] ?? null;
  const total = changedBlocks.length;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />

      <div className="relative bg-ui-card dark:bg-ui-dark-card w-full max-w-3xl max-h-[90vh] flex flex-col rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border">
          <h2 className="text-sm font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary">
            {t('diff.modalTitle')}
          </h2>
          <button
            type="button"
            className="text-text-muted hover:text-text-primary dark:text-text-dark-muted dark:hover:text-text-dark-primary transition-colors p-1 rounded"
            onClick={onClose}
            aria-label={t('actions.close')}
          >
            ✕
          </button>
        </div>

        {total === 0 ? (
          <div className="flex-1 flex items-center justify-center p-8">
            <p className="text-sm text-text-muted dark:text-text-dark-muted text-center">
              {t('diff.noChangesVsTemplate')}
            </p>
          </div>
        ) : (
          <>
            {/* Navigation bar */}
            <div className="flex items-center gap-3 px-5 py-2.5 border-b border-ui-border dark:border-ui-dark-border bg-ui-body dark:bg-ui-dark-bg">
              <button
                type="button"
                disabled={idx === 0}
                onClick={() => setIdx((i) => i - 1)}
                className="text-sm font-medium px-2 py-1 rounded hover:bg-ui-border dark:hover:bg-ui-dark-border disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                aria-label={t('diff.previousBlock')}
              >
                {t('diff.previous')}
              </button>
              <span className="text-xs text-text-muted dark:text-text-dark-muted">
                {idx + 1} / {total}
              </span>
              <button
                type="button"
                disabled={idx === total - 1}
                onClick={() => setIdx((i) => i + 1)}
                className="text-sm font-medium px-2 py-1 rounded hover:bg-ui-border dark:hover:bg-ui-dark-border disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                aria-label={t('diff.nextBlock')}
              >
                {t('diff.next')}
              </button>
              <span className="ml-auto text-xs font-semibold text-text-secondary dark:text-text-dark-secondary truncate max-w-[200px]">
                {current?.title ?? t('diff.untitled')}
                {current?.is_deleted && (
                  <span className="ml-2 text-danger font-normal">{t('diff.deletedSuffix')}</span>
                )}
              </span>
            </div>

            {/* Diff content */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              {current && (
                <>
                  {/* Original (red) */}
                  <div>
                    <p className="text-xs font-black uppercase tracking-widest text-danger mb-2">
                      {t('diff.originalContent')}
                    </p>
                    <div className="bg-danger/5 border border-danger/20 dark:bg-danger/10 dark:border-danger/30 rounded-lg p-4 min-h-[60px]">
                      <BlockContentHtml
                        content={normalizeBlockContentForEditor(current.default_content)}
                      />
                    </div>
                  </div>

                  {/* Edited (green) — hidden for deleted optional blocks */}
                  {!current.is_deleted && (
                    <div>
                      <p className="text-xs font-black uppercase tracking-widest text-success-dark dark:text-success mb-2">
                        {t('diff.editedContent')}
                      </p>
                      <div className="bg-success/5 border border-success/20 dark:bg-success/10 dark:border-success/30 rounded-lg p-4 min-h-[60px]">
                        <BlockContentHtml
                          content={normalizeBlockContentForEditor(current.content)}
                        />
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          </>
        )}

        {/* Footer */}
        <div className="flex justify-end px-5 py-3 border-t border-ui-border dark:border-ui-dark-border">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            {t('actions.close')}
          </Button>
        </div>
      </div>
    </div>
  );
}
