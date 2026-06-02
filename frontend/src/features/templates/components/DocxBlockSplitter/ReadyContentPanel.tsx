import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import { ChunkListColumn } from './ChunkListColumn';
import { TargetBlockPanel } from './TargetBlockPanel';
import type { TargetBlock } from './types';

export interface ReadyContentPanelProps {
  chunks: BlockChunk[];
  selected: Set<number>;
  assignments: Map<number, string>;
  targets: TargetBlock[];
  warning: string | null;
  hasHeadings: boolean;
  onToggleSelection: (index: number, mode: 'single' | 'toggle' | 'range', e: React.MouseEvent) => void;
  onSelectAll: () => void;
  onAutoSplitByHeading: (level: number) => void;
  onAssignSelection: (target: string | 'new') => void;
  onUnassignSelection: () => void;
  onRenameBlock: (id: string, name: string) => void;
  onRemoveBlock: (id: string) => void;
  chunksByTarget: (targetId: string) => BlockChunk[];
}

export function ReadyContentPanel({
  chunks,
  selected,
  assignments,
  targets,
  warning,
  hasHeadings,
  onToggleSelection,
  onSelectAll,
  onAutoSplitByHeading,
  onAssignSelection,
  onUnassignSelection,
  onRenameBlock,
  onRemoveBlock,
  chunksByTarget,
}: ReadyContentPanelProps) {
  return (
    <div className="flex min-h-0 flex-1 flex-col">
      {warning && (
        <div className="border-b border-warning-dark/30 bg-warning-dark/10 px-4 py-2 text-xs text-warning-dark">
          ⚠ {warning}
        </div>
      )}
      <div className="flex min-h-0 flex-1">
        <ChunkListColumn
          chunks={chunks}
          selected={selected}
          assignments={assignments}
          targets={targets}
          hasHeadings={hasHeadings}
          onToggleSelection={onToggleSelection}
          onSelectAll={onSelectAll}
          onAutoSplitByHeading={onAutoSplitByHeading}
          onAssignSelection={onAssignSelection}
          onUnassignSelection={onUnassignSelection}
        />
        <TargetBlockPanel
          targets={targets}
          chunks={chunks}
          assignments={assignments}
          onRenameBlock={onRenameBlock}
          onRemoveBlock={onRemoveBlock}
          chunksByTarget={chunksByTarget}
        />
      </div>
    </div>
  );
}
