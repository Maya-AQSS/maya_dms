import React, {
  useCallback,
  useEffect,
  useRef,
  useState,
  Suspense,
  lazy,
} from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAutoSave, useFlushOnPageLeave } from '@ceedcv-maya/shared-hooks-react';
import { useDarkMode } from '@ceedcv-maya/shared-layout-react';
import {
  DndContext,
  closestCenter,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Button, FieldLabel, TextInput } from '@ceedcv-maya/shared-ui-react';
import { ErrorBoundaryWrapper as ErrorBoundary } from '../../../components/ErrorBoundaryWrapper';
import type { Template } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import {
  type BlockUiState,
  BLOCK_UI_STATE_CONFIG,
  blockToUiState,
} from '../blockUiState';
import { uploadMedia } from '../../../api/media';
const BlockNoteEditorPanel = lazy(() =>
  import('./BlockNoteEditorPanel').then((m) => ({ default: m.BlockNoteEditorPanel })),
);

// ── Icons ────────────────────────────────────────────────────────────────────

function LockIcon() {
  return (
    <svg
      width="13"
      height="13"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  );
}

// ── Block state toggle ────────────────────────────────────────────────────────

function BlockUiStateToggle({
  value,
  onChange,
  disabled,
}: {
  value: BlockUiState;
  onChange: (s: BlockUiState) => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {(['editable', 'modifiable', 'locked', 'optional'] as BlockUiState[]).map((s) => (
        <button
          key={s}
          type="button"
          disabled={disabled}
          onClick={() => onChange(s)}
          className={[
            'px-2.5 py-1 rounded text-xs font-medium transition-all border',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
            value === s
              ? 'border-odoo-purple bg-odoo-purple text-text-inverse dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
              : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50',
            'disabled:opacity-50 disabled:pointer-events-none',
          ].join(' ')}
        >
          {BLOCK_UI_STATE_CONFIG[s].label}
        </button>
      ))}
    </div>
  );
}

// ── Sortable outline item (left panel) ────────────────────────────────────────

function SortableOutlineItem({
  block,
  isActive,
  onClick,
}: {
  block: TemplateBlock;
  isActive: boolean;
  onClick: () => void;
}) {
  const { t } = useTranslation('documents');
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id: block.id });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 20 : 1,
    position: 'relative',
    opacity: isDragging ? 0.5 : 1,
  };

  const uiState = blockToUiState(block);
  const cfg = BLOCK_UI_STATE_CONFIG[uiState];
  const isLocked = uiState === 'locked';

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={[
        'flex items-center gap-2 rounded px-2.5 py-2 min-h-10 border transition-all',
        isActive
          ? 'bg-odoo-purple/10 border-l-[3px] border-l-odoo-purple border-t-odoo-purple/20 border-r-odoo-purple/20 border-b-odoo-purple/20 dark:bg-odoo-dark-purple/15'
          : 'bg-white dark:bg-ui-dark-card border-ui-border/60 dark:border-ui-dark-border/60 hover:bg-ui-body dark:hover:bg-ui-dark-bg',
      ].join(' ')}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        className="shrink-0 w-5 h-5 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-secondary focus:outline-none"
        aria-label={t('blocks.reorderAria')}
      >
        ⠿
      </button>
      <button
        type="button"
        onClick={onClick}
        className="flex-1 min-w-0 flex items-center gap-1.5 text-left focus:outline-none"
      >
        {isLocked && (
          <span className="shrink-0 text-danger-dark dark:text-danger-light">
            <LockIcon />
          </span>
        )}
        <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
          {block.title || 'Bloque sin nombre'}
        </span>
        <span
          className={`shrink-0 px-1.5 py-0.5 rounded text-xs font-bold uppercase tracking-tight ${cfg.badgeCls}`}
        >
          {cfg.label}
        </span>
      </button>
    </div>
  );
}

// ── TemplateEditor ────────────────────────────────────────────────────────────

type Props = { template: Template };

export function TemplateEditor({ template }: Props) {
  const navigate = useNavigate();
  const { t } = useTranslation('documents');
  const { blocks, loading, createBlock, updateBlock, reorderBlocks } =
    useTemplateBlocks(template.id, {
      created_by: template.created_by,
      status: template.status,
    });
  const { isDark } = useDarkMode();
  const sensors = useSensors(useSensor(PointerSensor));

  // Right panel mode
  const [activeBlockId, setActiveBlockId] = useState<string | null>(null);
  const [rightMode, setRightMode] = useState<'block' | 'create'>('create');

  // Local edits for active block
  const [localTitle, setLocalTitle] = useState('');
  const [localUiState, setLocalUiState] = useState<BlockUiState>('editable');
  const [localContent, setLocalContent] = useState<unknown>(null);

  // Autosave state
  const [isDirty, setIsDirty] = useState(false);
  // saveStatus and savedTimerRef are now managed by useAutoSave hook

  // Properties section visibility
  const [propertiesCollapsed, setPropertiesCollapsed] = useState(false);

  const [creatingBlock, setCreatingBlock] = useState(false);

  // ── Auto-select first block after load ───────────────────────────────────────
  useEffect(() => {
    if (loading) return;
    if (blocks.length > 0 && activeBlockId === null) {
      const first = blocks[0];
      setActiveBlockId(first.id);
      setRightMode('block');
      setLocalTitle(first.title ?? '');
      setLocalUiState(blockToUiState(first));
      setLocalContent(first.default_content);
      setIsDirty(false);
    } else if (blocks.length === 0) {
      setRightMode('create');
    }
  }, [loading, blocks.length, activeBlockId]); // eslint-disable-line react-hooks/exhaustive-deps

  // ── Autosave: migrate to useAutoSave hook (1500ms debounce) ─────────────────
  const doSave = useCallback(async () => {
    if (!isDirty || !activeBlockId) return;
    const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[localUiState].payload;
    await updateBlock(activeBlockId, {
      title: localTitle.trim() || null,
      block_state,
      mandatory,
      default_content: localContent,
    });
    setIsDirty(false);
  }, [isDirty, activeBlockId, localTitle, localUiState, localContent, updateBlock]);

  const { saveStatus, triggerSave, forceSave } = useAutoSave(doSave, 1500);

  // Trigger debounced save whenever local state is dirty
  useEffect(() => {
    if (!isDirty || rightMode !== 'block' || !activeBlockId) return;
    triggerSave();
  }, [localTitle, localUiState, localContent, isDirty, rightMode, activeBlockId]); // eslint-disable-line react-hooks/exhaustive-deps

  // Keep saveRef for navigateToBlock (force-save before switching)
  const saveRef = useRef(forceSave);
  useEffect(() => { saveRef.current = forceSave; }, [forceSave]);

  const flushTemplateEditor = useCallback(() => {
    if (isDirty && activeBlockId) void forceSave();
  }, [isDirty, activeBlockId, forceSave]);

  useFlushOnPageLeave(flushTemplateEditor, rightMode === 'block' && !!activeBlockId);

  // ── Navigate to a block (saves current if dirty) ─────────────────────────────
  const navigateToBlock = useCallback(
    async (blockId: string) => {
      if (activeBlockId === blockId && rightMode === 'block') return;
      if (isDirty && activeBlockId) {
        try {
          await saveRef.current();
        } catch {
          // Navigate anyway; error is already shown in saveStatus
        }
      }
      const block = blocks.find((b) => b.id === blockId);
      if (!block) return;
      setActiveBlockId(blockId);
      setRightMode('block');
      setLocalTitle(block.title ?? '');
      setLocalUiState(blockToUiState(block));
      setLocalContent(block.default_content);
      setIsDirty(false);
    },
    [activeBlockId, rightMode, isDirty, blocks],
  );

  // ── Create block ─────────────────────────────────────────────────────────────
  const handleCreateBlock = async () => {
    setCreatingBlock(true);
    try {
      if (isDirty && activeBlockId) await saveRef.current();
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG['editable'].payload;
      const block = await createBlock({
        type: 'paragraph',
        title: 'Nuevo bloque',
        block_state,
        mandatory,
      });
      setActiveBlockId(block.id);
      setRightMode('block');
      setLocalTitle(block.title ?? '');
      setLocalUiState(blockToUiState(block));
      setLocalContent(block.default_content);
      setIsDirty(false);
    } catch {
      // TODO: send to error tracker
    } finally {
      setCreatingBlock(false);
    }
  };

  // ── Drag & drop reorder ───────────────────────────────────────────────────────
  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const newIndex = blocks.findIndex((b) => b.id === over.id);
    if (newIndex < 0) return;
    void reorderBlocks(active.id.toString(), newIndex);
  };

  // ── Dirty helpers (called from UI handlers) ───────────────────────────────────
  const markDirty = () => {
    setIsDirty(true);
  };

  // ── Derived ───────────────────────────────────────────────────────────────────
  const activeBlock = activeBlockId ? blocks.find((b) => b.id === activeBlockId) : null;
  const isBlockLocked = activeBlock ? blockToUiState(activeBlock) === 'locked' : false;
  const activeUiCfg = activeBlock ? BLOCK_UI_STATE_CONFIG[blockToUiState(activeBlock)] : null;

  // ── Render: save status indicator ────────────────────────────────────────────
  const renderSaveStatus = () => {
    switch (saveStatus) {
      case 'saving':
        return (
          <span className="text-xs text-text-muted dark:text-text-dark-muted italic">
            Guardando…
          </span>
        );
      case 'saved':
        return (
          <span className="text-xs text-success-dark dark:text-success-light flex items-center gap-1">
            ✓ Guardado
          </span>
        );
      case 'error':
        return (
          <span className="text-xs text-danger-dark dark:text-danger-light">
            Error al guardar. Inténtalo de nuevo.
          </span>
        );
      default:
        return null;
    }
  };

  // ── Render: right panel ───────────────────────────────────────────────────────
  const renderRightPanel = () => {
    if (loading) {
      return (
        <div className="flex-1 flex items-center justify-center text-sm text-text-muted dark:text-text-dark-muted">
          Cargando bloques…
        </div>
      );
    }

    if (rightMode === 'create' || !activeBlock) {
      return (
        <div className="flex-1 flex flex-col items-center justify-center p-8 text-center">
          <div className="w-16 h-16 rounded-2xl bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border flex items-center justify-center mb-4 text-text-muted">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 4v16m8-8H4" />
            </svg>
          </div>
          <h3 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
            No hay ningún bloque seleccionado
          </h3>
          <p className="text-xs text-text-muted mt-1 max-w-xs">
            Selecciona un bloque de la lista de la izquierda para editarlo o añade uno nuevo.
          </p>
          <Button
            variant="primary"
            className="mt-6"
            onClick={handleCreateBlock}
            loading={creatingBlock}
          >
            Añadir primer bloque
          </Button>
        </div>
      );
    }

    // ── Block editor mode ──────────────────────────────────────────────────────
    if (!activeBlock) return null;

    return (
      <div className="flex-1 flex flex-col min-h-0 overflow-hidden animate-in fade-in">
        {/* right-header */}
        <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex items-center gap-3">
          {isBlockLocked && (
            <span className="shrink-0 text-danger-dark dark:text-danger-light">
              <LockIcon />
            </span>
          )}
          <span className="flex-1 min-w-0 text-sm font-bold text-text-primary dark:text-text-dark-primary truncate">
            {activeBlock.title || 'Bloque sin nombre'}
          </span>
          {activeUiCfg && (
            <span
              className={`shrink-0 px-2 py-0.5 rounded text-xs font-bold uppercase tracking-tight ${activeUiCfg.badgeCls}`}
            >
              {activeUiCfg.label}
            </span>
          )}
          <Button
            variant="ghost"
            size="xs"
            onClick={() => setPropertiesCollapsed((v) => !v)}
            aria-label={propertiesCollapsed ? 'Expandir propiedades' : 'Colapsar propiedades'}
            className="shrink-0 !w-6 !h-6 !p-0 !rounded"
          >
            {propertiesCollapsed ? '▾' : '▴'}
          </Button>
        </div>

        {/* right-properties (colapsable) */}
        {!propertiesCollapsed && (
          <div className="shrink-0 px-5 py-4 border-b border-ui-border dark:border-ui-dark-border bg-ui-body/30 dark:bg-ui-dark-bg/50 space-y-3 animate-in slide-in-from-top-1">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <FieldLabel required>Nombre del bloque</FieldLabel>
                <TextInput
                  type="text"
                  fieldSize="sm"
                  value={localTitle}
                  disabled={isBlockLocked}
                  placeholder={t('blocks.blockNamePlaceholder')}
                  onChange={(e) => {
                    setLocalTitle(e.target.value);
                    markDirty();
                  }}
                />
              </div>
              <div>
                <FieldLabel required>Estado</FieldLabel>
                <div className="mt-1">
                  <BlockUiStateToggle
                    value={localUiState}
                    onChange={(s) => {
                      setLocalUiState(s);
                      markDirty();
                    }}
                    disabled={isBlockLocked}
                  />
                </div>
              </div>
            </div>
          </div>
        )}

        {/* right-editor (placeholder hasta activar @blocknote/mantine) */}
        <ErrorBoundary
          fallback={
            <div className="flex-1 flex items-center justify-center p-6 text-sm text-danger-dark">
              Error al cargar el editor. Recarga la página.
            </div>
          }
        >
          <div className="flex-1 overflow-y-auto px-4 py-4 min-h-0">
            <Suspense
              fallback={
                <div className="flex-1 flex items-center justify-center p-6 text-sm text-text-muted">
                  Cargando editor...
                </div>
              }
            >
              <BlockNoteEditorPanel
                key={activeBlockId}
                initialContent={localContent as any}
                onChange={(content) => {
                  setLocalContent(content);
                  markDirty();
                }}
                onFlush={flushTemplateEditor}
                editable={true} // Siempre editable en la plantilla
                isDark={isDark}
                uploadFile={uploadMedia}
              />
            </Suspense>
          </div>
        </ErrorBoundary>
      </div>
    );
  };

  // ── Full render ───────────────────────────────────────────────────────────────

  return (
    <div className="flex flex-col h-full bg-ui-body dark:bg-ui-dark-bg">
      {/* Wizard topbar */}
      <div className="shrink-0 flex items-center gap-3 px-4 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shadow-sm z-10">
        <Button
          variant="ghost"
          size="xs"
          onClick={() => navigate('/procesos')}
          aria-label={t('blocks.backToTemplates')}
          className="!w-9 !h-9 !p-0 !rounded-full active:scale-95"
        >
          ←
        </Button>
        <span className="text-sm text-text-secondary">
          Plantillas /{' '}
          <span className="font-bold text-text-primary dark:text-text-dark-primary">
            Editando «{template.name}»
          </span>
        </span>
      </div>

      {/* Editor body: left panel + right panel */}
      <div className="flex-1 overflow-hidden flex min-h-0">
        {/* ── Left panel ────────────────────────────────────────────── */}
        <div className="w-72 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card overflow-hidden">
          {/* left-header */}
          <div className="shrink-0 px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
            <span className="text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
              Bloques ({blocks.length})
            </span>
            <Button
              type="button"
              variant="primary"
              size="xs"
              onClick={handleCreateBlock}
            >
              + Nuevo bloque
            </Button>
          </div>

          {/* left-scroll: block outline */}
          <div className="flex-1 overflow-y-auto p-3 min-h-0">
            {loading ? (
              <div className="text-xs text-text-muted dark:text-text-dark-muted p-2">
                Cargando…
              </div>
            ) : blocks.length === 0 ? (
              <div className="text-xs text-text-muted dark:text-text-dark-muted p-2 text-center italic">
                Sin bloques todavía
              </div>
            ) : (
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <SortableContext
                  items={blocks.map((b) => b.id)}
                  strategy={verticalListSortingStrategy}
                >
                  <div className="space-y-1.5">
                    {blocks.map((block) => (
                      <SortableOutlineItem
                        key={block.id}
                        block={block}
                        isActive={activeBlockId === block.id && rightMode === 'block'}
                        onClick={() => void navigateToBlock(block.id)}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>
            )}
          </div>
        </div>

        {/* ── Right panel ───────────────────────────────────────────── */}
        <div className="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden bg-white dark:bg-ui-dark-bg">
          {renderRightPanel()}
        </div>
      </div>

      {/* Footer */}
      <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-6 py-3 flex items-center justify-between gap-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div className="flex-1 min-w-0">{renderSaveStatus()}</div>
        {rightMode === 'block' && activeBlockId && (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            loading={saveStatus === 'saving'}
            disabled={!isDirty}
            onClick={() => void saveRef.current()}
            className="text-xs font-black uppercase tracking-widest rounded-full"
          >
            Guardar ahora
          </Button>
        )}
      </div>
    </div>
  );
}
