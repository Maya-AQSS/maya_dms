import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import { useTranslation } from 'react-i18next';
import { TYPE_BADGE } from './constants';
import type { TargetBlock } from './types';

export interface ChunkListItemProps {
  chunk: BlockChunk;
  isSelected: boolean;
  target?: TargetBlock;
  onClick: (e: React.MouseEvent<HTMLButtonElement>) => void;
}

export function ChunkListItem({
  chunk,
  isSelected,
  target,
  onClick,
}: ChunkListItemProps) {
  const { t } = useTranslation('templates');
  const typeLabel = t(`docx.kind.${chunk.type}`);
  return (
    <button
      key={chunk.index}
      type="button"
      onClick={onClick}
      className={[
        'mb-1 flex w-full items-start gap-2 rounded border px-2 py-1.5 text-left text-sm transition-colors',
        isSelected
          ? 'border-odoo-purple bg-odoo-purple/10'
          : 'border-ui-border hover:bg-ui-bg dark:border-ui-dark-border dark:hover:bg-ui-dark-bg',
      ].join(' ')}
    >
      <span
        className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-ui-bg text-xs text-text-muted dark:bg-ui-dark-bg dark:text-text-dark-muted"
        title={typeLabel}
      >
        {chunk.type === 'heading' && chunk.level ? `H${chunk.level}` : TYPE_BADGE[chunk.type]}
      </span>
      <span className="min-w-0 flex-1 truncate text-text-primary dark:text-text-dark-primary">
        {chunk.text || <em className="text-text-muted">({typeLabel})</em>}
      </span>
      {target && (
        <span className="shrink-0 rounded bg-odoo-purple/15 px-1.5 text-xs text-odoo-purple">
          {target.name}
        </span>
      )}
    </button>
  );
}
