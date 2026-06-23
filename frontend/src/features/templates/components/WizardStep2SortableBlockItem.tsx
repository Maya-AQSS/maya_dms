import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type React from 'react';
import { useTranslation } from 'react-i18next';
import type { TemplateBlock } from '../../../types/blocks';
import { BlockListItem } from '../../blocks-ui/BlockListItem';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';

export function SortableBlockItem({
  block,
  itemState,
  onClick,
  hasUnreadComments,
  isCompleted,
}: {
  block: TemplateBlock;
  itemState: 'default' | 'selected' | 'multi-queued' | 'multi-current' | 'multi-saved';
  onClick: () => void;
  hasUnreadComments?: boolean;
  isCompleted?: boolean;
}) {
  const { t } = useTranslation('documents');
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: block.id,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 20 : 1,
    position: 'relative',
    opacity: isDragging ? 0.5 : 1,
  };

  const isLocked = blockToUiState(block) === 'locked';
  const ui = blockToUiState(block);

  return (
    <div ref={setNodeRef} style={style}>
      <BlockListItem
        title={block.title || ''}
        variant={itemState}
        locked={isLocked}
        hasUnreadComments={hasUnreadComments}
        isCompleted={isCompleted}
        stateLabel={BLOCK_UI_STATE_CONFIG[ui].label}
        onClick={onClick}
        dragHandle={
          <button
            type="button"
            {...attributes}
            {...listeners}
            className="shrink-0 w-5 h-5 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary focus:outline-none"
            onClick={(e) => e.stopPropagation()}
            aria-label={t('documents:blocks.reorderAria')}
          >
            ⠿
          </button>
        }
      />
    </div>
  );
}
