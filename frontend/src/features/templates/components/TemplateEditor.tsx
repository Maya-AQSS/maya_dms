import React, {
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react';
import { ErrorBoundary } from '@maya/shared-ui-react';
import { useNavigate } from 'react-router-dom';
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
import { Button, FieldLabel, TextInput } from '../../../ui';
import type { Template } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import {
  type BlockUiState,
  BLOCK_UI_STATE_CONFIG,
  blockToUiState,
} from '../blockUiState';

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
        aria-label="Reordenar bloque"
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
  const { blocks, loading, createBlock, updateBlock, reorderBlocks } =
    useTemplateBlocks(template.id);
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
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const savedTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Properties section visibility
  const [propertiesCollapsed, setPropertiesCollapsed] = useState(false);

  // New block form
  const [newBlockName, setNewBlockName] = useState('');
  const [newBlockUiState, setNewBlockUiState] = useState<BlockUiState>('editable');
  const [creatingBlock, setCreatingBlock] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

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
      setSaveStatus('idle');
    } else if (blocks.length === 0) {
      setRightMode('create');
    }
  }, [loading, blocks.length, activeBlockId]); // eslint-disable-line react-hooks/exhaustive-deps

  // ── saveCurrentBlock ─────────────────────────────────────────────────────────
  // Always use a ref so autosave interval always calls the latest closure
  const saveCurrentBlockFn = useCallback(async () => {
    if (!isDirty || !activeBlockId) return;
    setSaveStatus('saving');
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[localUiState].payload;
      await updateBlock(activeBlockId, {
        title: localTitle.trim() || null,
        block_state,
        mandatory,
        default_content: localContent,
      });
      setIsDirty(false);
      setSaveStatus('saved');
      if (savedTimerRef.current) clearTimeout(savedTimerRef.current);
      savedTimerRef.current = setTimeout(() => setSaveStatus('idle'), 3000);
    } catch {
      setSaveStatus('error');
    }
  }, [isDirty, activeBlockId, localTitle, localUiState, localContent, updateBlock]);

  const saveRef = useRef(saveCurrentBlockFn);
  useEffect(() => { saveRef.current = saveCurrentBlockFn; }, [saveCurrentBlockFn]);

  // ── Autosave every 30s when dirty ────────────────────────────────────────────
  useEffect(() => {
    if (!isDirty || rightMode !== 'block' || !activeBlockId) return;
    const interval = setInterval(() => void saveRef.current(), 30_000);
    return () => clearInterval(interval);
  }, [isDirty, rightMode, activeBlockId]);

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
      setSaveStatus('idle');
    },
    [activeBlockId, rightMode, isDirty, blocks],
  );

  // ── Create block ─────────────────────────────────────────────────────────────
  const handleCreateBlock = async () => {
    if (!newBlockName.trim()) return;
    setCreatingBlock(true);
    setCreateError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[newBlockUiState].payload;
      const block = await createBlock({
        type: 'paragraph',
        title: newBlockName.trim(),
        block_state,
        mandatory,
      });
      setNewBlockName('');
      setNewBlockUiState('editable');
      setActiveBlockId(block.id);
      setRightMode('block');
      setLocalTitle(block.title ?? '');
      setLocalUiState(blockToUiState(block));
      setLocalContent(block.default_content);
      setIsDirty(false);
      setSaveStatus('idle');
    } catch (e) {
      setCreateError(e instanceof Error ? e.message : 'Error al crear el bloque');
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
    setSaveStatus('idle');
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

    if (rightMode === 'create') {
      return (
        <div className="flex-1 overflow-y-auto p-6 flex flex-col items-center justify-center">
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ui-body dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border mb-4">
              <svg
                className="w-6 h-6 text-text-muted"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1.5}
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
            {blocks.length === 0 ? (
              <>
                <h3 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
                  Esta plantilla aún no tiene bloques
                </h3>
                <p className="text-xs text-text-muted mt-1 max-w-sm mx-auto">
                  Añade el primer bloque para definir la estructura del documento.
                </p>
              </>
            ) : (
              <>
                <h3 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
                  Nuevo bloque
                </h3>
                <p className="text-xs text-text-muted mt-1">
                  Completa el formulario para añadir un bloque a la plantilla.
                </p>
              </>
            )}
          </div>

          <div className="w-full max-w-sm bg-white dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6 space-y-4">
            <div>
              <FieldLabel required>Nombre</FieldLabel>
              <TextInput
                type="text"
                fieldSize="comfortable"
                value={newBlockName}
                onChange={(e) => setNewBlockName(e.target.value)}
                placeholder="Ej: Introducción"
              />
            </div>
            <div>
              <FieldLabel required>Estado</FieldLabel>
              <div className="mt-1">
                <BlockUiStateToggle
                  value={newBlockUiState}
                  onChange={setNewBlockUiState}
                />
              </div>
            </div>
            <div className="flex gap-2 pt-1">
              <Button
                type="button"
                variant="primary"
                size="md"
                className="flex-1"
                loading={creatingBlock}
                disabled={!newBlockName.trim()}
                onClick={() => void handleCreateBlock()}
              >
                Añadir bloque
              </Button>
              {blocks.length > 0 && (
                <Button
                  type="button"
                  variant="outline"
                  size="md"
                  onClick={() => {
                    const target = activeBlockId ?? blocks[0]?.id;
                    if (target) void navigateToBlock(target);
                  }}
                >
                  Cancelar
                </Button>
              )}
            </div>
            {createError && (
              <p className="text-xs text-danger-dark animate-in fade-in">{createError}</p>
            )}
          </div>
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
                  placeholder="Nombre del bloque"
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
          <div
            className="flex-1 flex items-center justify-center rounded-lg border-2 border-dashed border-ui-border dark:border-ui-dark-border bg-ui-body/50 dark:bg-ui-dark-bg/50 mx-4 my-4"
            style={{ minHeight: '200px' }}
          >
            <p className="text-sm text-text-muted text-center px-6">
              Editor de contenido — próximamente disponible.
            </p>
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
          aria-label="Volver a Plantillas"
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
              onClick={async () => {
                if (isDirty && activeBlockId) await saveRef.current();
                setRightMode('create');
                setActiveBlockId(null);
              }}
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
