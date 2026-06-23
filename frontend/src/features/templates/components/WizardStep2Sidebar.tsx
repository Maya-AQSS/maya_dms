import { Button } from '@ceedcv-maya/shared-ui-react';
import { closestCenter, DndContext, type DragEndEvent, type useSensors } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import type { TemplateBlock } from '../../../types/blocks';
import { countUnreadCommentsForBlock } from '../../../utils/blockComments';
import type { BlockSourceContext } from '../blockSources';
import { AddBlockMenu } from './AddBlockMenu';
import type { BlockComment } from './BlockCommentsCard';
import { SortableBlockItem } from './WizardStep2SortableBlockItem';

interface WizardStep2SidebarProps {
  isSidebarCollapsed: boolean;
  onToggleCollapse: () => void;
  blocks: TemplateBlock[];
  loading: boolean;
  selectedBlockIds: string[];
  activeSingleId: string | null;
  reviewComments: BlockComment[];
  completedBlocks: { isCompleted: (id: string) => boolean };
  sensors: ReturnType<typeof useSensors>;
  busy: boolean;
  templateId: string;
  hasPermission: (perm: string) => boolean;
  onToggleSelectAll: () => void;
  onDragEnd: (event: DragEndEvent) => void;
  onBlockClick: (blockId: string) => void;
  onAddBlock: BlockSourceContext['createBlock'];
  onSetDocxSplitter: (open: boolean) => void;
}

/** Left rail: collapsible block list with drag-reorder + the "add block" menu. */
export function WizardStep2Sidebar({
  isSidebarCollapsed,
  onToggleCollapse,
  blocks,
  loading,
  selectedBlockIds,
  activeSingleId,
  reviewComments,
  completedBlocks,
  sensors,
  busy,
  templateId,
  hasPermission,
  onToggleSelectAll,
  onDragEnd,
  onBlockClick,
  onAddBlock,
  onSetDocxSplitter,
}: WizardStep2SidebarProps) {
  return (
    <div className="relative shrink-0 z-30 flex flex-col overflow-visible">
      <div
        className={[
          'h-full flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card transition-all duration-300 overflow-hidden',
          isSidebarCollapsed ? 'w-0' : 'w-64 md:w-72',
        ].join(' ')}
      >
        {!isSidebarCollapsed && (
          <div className="flex flex-col h-full overflow-hidden animate-in fade-in duration-300">
            <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0">
              <span className="text-xs font-black uppercase text-text-secondary tracking-widest truncate">
                Bloques ({blocks.length})
              </span>
              <Button
                variant="ghost"
                size="xs"
                onClick={onToggleSelectAll}
                className="shrink-0 ml-2"
              >
                {selectedBlockIds.length === blocks.length && blocks.length > 0
                  ? 'Deseleccionar'
                  : 'Todos'}
              </Button>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-2">
              {loading ? (
                <div className="text-xs text-text-muted p-4">Cargando bloques...</div>
              ) : (
                <DndContext
                  sensors={sensors}
                  collisionDetection={closestCenter}
                  onDragEnd={onDragEnd}
                >
                  <SortableContext
                    items={blocks.map((b) => b.id)}
                    strategy={verticalListSortingStrategy}
                  >
                    {blocks.map((block) => (
                      <SortableBlockItem
                        key={block.id}
                        block={block}
                        itemState={
                          activeSingleId === block.id
                            ? 'selected'
                            : selectedBlockIds.includes(block.id)
                              ? 'multi-queued'
                              : 'default'
                        }
                        onClick={() => onBlockClick(block.id)}
                        hasUnreadComments={
                          countUnreadCommentsForBlock(block.id, reviewComments) > 0
                        }
                        isCompleted={completedBlocks.isCompleted(block.id)}
                      />
                    ))}
                  </SortableContext>
                </DndContext>
              )}
            </div>
            <div className="p-4 border-t border-ui-border dark:border-ui-dark-border shrink-0">
              <AddBlockMenu
                disabled={busy}
                ctx={{
                  templateId,
                  createBlock: (block) => void onAddBlock(block),
                  openDocxSplitter: () => onSetDocxSplitter(true),
                  setActiveDialog: (id) => {
                    if (id === 'docx-splitter') onSetDocxSplitter(true);
                    else if (id === null) onSetDocxSplitter(false);
                  },
                  hasPermission,
                }}
              />
            </div>
          </div>
        )}
      </div>
      <button
        type="button"
        onClick={onToggleCollapse}
        className={[
          'absolute top-4 -right-3 z-50 w-6 h-6 rounded-full border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex items-center justify-center text-text-muted hover:text-odoo-purple transition-all shadow-sm',
          isSidebarCollapsed ? 'rotate-180' : '',
        ].join(' ')}
        title={isSidebarCollapsed ? 'Expandir' : 'Colapsar'}
      >
        <svg
          className="w-3.5 h-3.5 transition-transform duration-200"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth={2.5}
          aria-hidden="true"
        >
          <title>{isSidebarCollapsed ? 'Expandir' : 'Colapsar'}</title>
          <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
    </div>
  );
}
