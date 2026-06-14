import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { ChunkListItem } from './ChunkListItem';
import type { TargetBlock } from './types';

export interface ChunkListColumnProps {
  chunks: BlockChunk[];
  selected: Set<number>;
  assignments: Map<number, string>;
  targets: TargetBlock[];
  hasHeadings: boolean;
  onToggleSelection: (index: number, mode: 'single' | 'toggle' | 'range', e: React.MouseEvent) => void;
  onSelectAll: () => void;
  onAutoSplitByHeading: (level: number) => void;
  onAssignSelection: (target: string | 'new') => void;
  onUnassignSelection: () => void;
}

export function ChunkListColumn({
  chunks,
  selected,
  assignments,
  targets,
  hasHeadings,
  onToggleSelection,
  onSelectAll,
  onAutoSplitByHeading,
  onAssignSelection,
  onUnassignSelection,
}: ChunkListColumnProps) {
  const { t } = useTranslation('templates');
  return (
    <div className="flex w-3/5 flex-col border-r border-ui-border dark:border-ui-dark-border">
      <div className="flex flex-wrap items-center gap-2 border-b border-ui-border px-4 py-2 dark:border-ui-dark-border">
        <span className="text-xs text-text-muted dark:text-text-dark-muted">
          {t('docx.chunksSummary', { count: chunks.length, selected: selected.size })}
        </span>
        <Button variant="outline" disabled={chunks.length === 0} onClick={onSelectAll}>
          {t('docx.selectAll')}
        </Button>
        {hasHeadings && (
          <>
            <Button variant="outline" onClick={() => onAutoSplitByHeading(1)} title={t('docx.splitH1')}>
              {t('docx.autoH1')}
            </Button>
            <Button variant="outline" onClick={() => onAutoSplitByHeading(2)} title={t('docx.splitH1H2')}>
              {t('docx.autoH2')}
            </Button>
          </>
        )}
        <div className="ml-auto flex gap-2">
          <Button variant="outline" disabled={selected.size === 0} onClick={() => onAssignSelection('new')}>
            {t('docx.newBlock')}
          </Button>
          {targets.length > 0 && (
            <select
              className="rounded border border-ui-border bg-white px-2 text-xs dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-primary"
              value=""
              disabled={selected.size === 0}
              onChange={(e) => {
                if (e.target.value) onAssignSelection(e.target.value);
                e.target.value = '';
              }}
            >
              <option value="">{t('docx.assignTo')}</option>
              {targets.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
          )}
          <Button variant="outline" disabled={selected.size === 0} onClick={onUnassignSelection}>
            {t('docx.unassign')}
          </Button>
        </div>
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto p-2">
        {chunks.map((c) => {
          const targetId = assignments.get(c.index);
          const target = targets.find((t) => t.id === targetId);
          const isSel = selected.has(c.index);
          return (
            <ChunkListItem
              key={c.index}
              chunk={c}
              isSelected={isSel}
              target={target}
              onClick={(e) =>
                onToggleSelection(
                  c.index,
                  e.shiftKey ? 'range' : e.ctrlKey || e.metaKey ? 'toggle' : 'single',
                  e,
                )
              }
            />
          );
        })}
      </div>
    </div>
  );
}
