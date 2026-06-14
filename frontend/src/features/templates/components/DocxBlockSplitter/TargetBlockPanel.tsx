import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import { EditorContentHtml } from '@ceedcv-maya/shared-editor-react';
import { useTranslation } from 'react-i18next';
import type { TargetBlock } from './types';

export interface TargetBlockPanelProps {
  targets: TargetBlock[];
  chunks: BlockChunk[];
  assignments: Map<number, string>;
  onRenameBlock: (id: string, name: string) => void;
  onRemoveBlock: (id: string) => void;
  chunksByTarget: (targetId: string) => BlockChunk[];
}

export function TargetBlockPanel({
  targets,
  chunks,
  assignments,
  onRenameBlock,
  onRemoveBlock,
  chunksByTarget,
}: TargetBlockPanelProps) {
  const { t: tr } = useTranslation(['templates', 'common']);
  const assignedCount = assignments.size;

  return (
    <div className="flex w-2/5 flex-col">
      <div className="border-b border-ui-border px-4 py-2 text-xs text-text-muted dark:border-ui-dark-border dark:text-text-dark-muted">
        {tr('templates:docx.targetsSummary', {
          targets: targets.length,
          assigned: assignedCount,
          total: chunks.length,
        })}
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto p-3">
        {targets.length === 0 ? (
          <p className="p-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
            {tr('templates:docx.selectAndCreateHint')}
          </p>
        ) : (
          targets.map((target) => {
            const tc = chunksByTarget(target.id);
            return (
              <div
                key={target.id}
                className="mb-3 rounded border border-ui-border p-2 dark:border-ui-dark-border"
              >
                <div className="mb-2 flex items-center gap-2">
                  <input
                    value={target.name}
                    onChange={(e) => onRenameBlock(target.id, e.target.value)}
                    className="min-w-0 flex-1 rounded border border-ui-border px-2 py-1 text-sm dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary"
                  />
                  <span className="shrink-0 text-xs text-text-muted dark:text-text-dark-muted">
                    {tr('templates:docx.elementsAbbr', { count: tc.length })}
                  </span>
                  <button
                    type="button"
                    onClick={() => onRemoveBlock(target.id)}
                    className="shrink-0 text-danger-dark hover:opacity-70"
                    aria-label={tr('common:actions.deleteBlock')}
                  >
                    🗑
                  </button>
                </div>
                {tc.length === 0 ? (
                  <p className="text-xs text-warning-dark">{tr('templates:docx.noElements')}</p>
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
  );
}
