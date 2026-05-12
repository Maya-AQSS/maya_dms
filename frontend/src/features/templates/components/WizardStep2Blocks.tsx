import React, { useCallback, useEffect, useImperativeHandle, useRef, useState, Suspense, lazy } from 'react';
import { useDarkMode } from '@maya/shared-layout-react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import { Button, ConfirmDialog, ErrorBoundary, FieldLabel, TextInput } from '@maya/shared-ui-react';
import { BlockListItem } from '../../blocks-ui/BlockListItem';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { type BlockUiState, BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';
import { useAutoSave } from '../../../hooks/useAutoSave';
import { useUserProfile } from '../../../features/user-profile';
import { apiFetchJson } from '../../../api/http';
import { BlockCommentsCard } from './BlockCommentsCard';

const BlockNoteEditorPanel = lazy(() => import('./BlockNoteEditorPanel').then(m => ({ default: m.BlockNoteEditorPanel })));

type PanelMode = 'empty' | 'create' | 'edit' | 'multi';
type TabId = 'properties' | 'content' | 'description' | 'comments';

// ── Icons ────────────────────────────────────────────────────────────────────

// ── Sub-components ───────────────────────────────────────────────────────────

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
            'px-2.5 py-1 rounded text-xs font-bold uppercase tracking-widest transition-all border',
            value === s
              ? 'border-odoo-purple bg-odoo-purple text-text-inverse'
              : 'border-ui-border dark:border-ui-dark-border text-text-secondary hover:border-odoo-purple/50',
            'disabled:opacity-50 disabled:pointer-events-none',
          ].join(' ')}
        >
          {BLOCK_UI_STATE_CONFIG[s].label}
        </button>
      ))}
    </div>
  );
}

function SortableBlockItem({
  block,
  itemState,
  onClick,
  hasReviewComments,
}: {
  block: TemplateBlock;
  itemState: 'default' | 'selected' | 'multi-queued' | 'multi-current' | 'multi-saved';
  onClick: () => void;
  hasReviewComments?: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block.id });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 20 : 1,
    position: 'relative',
    opacity: isDragging ? 0.5 : 1,
  };

  const isLocked = blockToUiState(block) === 'locked';

  return (
    <div ref={setNodeRef} style={style}>
      <BlockListItem
        title={block.title || ''}
        variant={itemState}
        locked={isLocked}
        hasReviewComments={hasReviewComments}
        onClick={onClick}
        dragHandle={
          <button
            type="button"
            {...attributes}
            {...listeners}
            className="shrink-0 w-5 h-5 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary focus:outline-none"
            onClick={(e) => e.stopPropagation()}
            aria-label="Reordenar bloque"
          >
            ⠿
          </button>
        }
      />
    </div>
  );
}

// ── Main Component ───────────────────────────────────────────────────────────

interface WizardStep2BlocksProps {
  template: Template;
  isDark?: boolean;
  reviewComments?: any[];
  onBlocksCountChange?: (count: number) => void;
  onBlocksLoadingChange?: (loading: boolean) => void;
  onContinue?: () => void;
  onInvalidBlocksChange?: (hasInvalid: boolean) => void;
  onCommentAdded?: (comment: any) => void;
}

export type WizardStep2BlocksHandle = {
  saveIfPending: () => Promise<void>;
  discardInvalidBlocks: () => Promise<void>;
};

export const WizardStep2Blocks = React.forwardRef<WizardStep2BlocksHandle, WizardStep2BlocksProps>(({
  template,
  isDark = false,
  reviewComments = [],
  onBlocksCountChange,
  onBlocksLoadingChange,
  onContinue,
  onInvalidBlocksChange,
  onCommentAdded,
}, ref) => {
  const {
    blocks,
    loading,
    createBlock,
    updateBlock,
    deleteBlock,
    reorderBlocks
  } = useTemplateBlocks(template.id);

  const { isDark: globalIsDark } = useDarkMode();
  const effectiveIsDark = isDark || globalIsDark;

  useEffect(() => {
    onBlocksLoadingChange?.(loading);
  }, [loading, onBlocksLoadingChange]);

  useEffect(() => {
    if (!loading) {
      onBlocksCountChange?.(blocks.length);
    }
  }, [blocks.length, loading, onBlocksCountChange]);

  const hasInvalidBlocks = !loading && blocks.some(b => !b.title?.trim());

  useEffect(() => {
    onInvalidBlocksChange?.(hasInvalidBlocks);
  }, [hasInvalidBlocks, onInvalidBlocksChange]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (hasInvalidBlocks) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [hasInvalidBlocks]);

  const [isEditorFullscreen, setIsEditorFullscreen] = useState(false);

  const handleEditorFullscreenChange = useCallback((v: boolean) => {
    setIsEditorFullscreen(v);
    document.documentElement.classList.toggle('editor-fullscreen', v);
  }, []);

  useEffect(() => {
    return () => document.documentElement.classList.remove('editor-fullscreen');
  }, []);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [activeSingleId, setActiveSingleId] = useState<string | null>(null);
  const [showCommentPanel, setShowCommentPanel] = useState(true);

  // const [multiIndex, setMultiIndex] = useState(0);
  const clickTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [nameError, setNameError] = useState('');
  const [busy, setBusy] = useState(false);
  const [deleteModal, setDeleteModal] = useState(false);
  const [activeTab, setActiveTab] = useState<TabId>('properties');
  const [tabIsDirty, setTabIsDirty] = useState(false);

  useEffect(() => {
    if (activeSingleId && !formName.trim()) {
      setShowCommentPanel(false);
    }
  }, [activeSingleId, formName]);
  // Reply state is managed inside BlockCommentsCard; we only keep the API handler here.
  // Ref to always have latest activeSingleId in the autosave closure
  const activeSingleIdRef = useRef<string | null>(null);
  activeSingleIdRef.current = activeSingleId;

  const { profile } = useUserProfile();

  const selectedBlock = activeSingleId ? (blocks.find((b) => b.id === activeSingleId) ?? null) : null;
  const selectedBlockIndex = selectedBlock ? blocks.findIndex((b) => b.id === selectedBlock.id) : -1;

  const blockComments: any[] = activeSingleId
    ? reviewComments.filter((c) => c.blockable_id === activeSingleId)
    : [];
  const activeBlockHasComments = blockComments.length > 0;

  useEffect(() => {
    if (activeTab === 'comments') setActiveTab('properties');
  }, [activeTab]);

  const loadFormFromBlock = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setNameError('');
    setFormDesc(block.description ? (typeof block.description === 'string' ? block.description : JSON.stringify(block.description)) : '');
    setFormContent(block.default_content ? (typeof block.default_content === 'string' ? block.default_content : JSON.stringify(block.default_content)) : '');
    setFormUiState(blockToUiState(block));
    setTabIsDirty(false);
  };

  // ── useAutoSave (debounce 1500ms) — compartido en edit y multi ───────────────
  const validateBlockName = (name: string): string => {
    if (!name.trim()) return 'El nombre del bloque es obligatorio';
    if (name.trim().toLowerCase() === 'bloque sin nombre') return '"Bloque sin nombre" no es un nombre válido';
    return '';
  };

  const doSave = useCallback(async () => {
    const blockId = activeSingleIdRef.current;
    if (!blockId) return;
    const nameErr = validateBlockName(formName);
    if (nameErr) {
      setNameError(nameErr);
      return;
    }
    setNameError('');
    const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
    let parsedContent: unknown = null;
    let parsedDesc: unknown = null;
    try { parsedContent = formContent ? JSON.parse(formContent) : null; } catch { parsedContent = null; }
    try { parsedDesc = formDesc ? JSON.parse(formDesc) : null; } catch { parsedDesc = null; }
    // Normalize whitespace-only BlockNote content to null so it is stored as empty
    // and the UI shows "Este bloque no tiene contenido." instead of blank text nodes.
    if (Array.isArray(parsedContent) && parsedContent.length > 0) {
      const isBlank = (parsedContent as any[]).every((b: any) =>
        !Array.isArray(b.content) ||
        b.content.length === 0 ||
        b.content.every((c: any) => typeof c.text !== 'string' || !c.text.trim()),
      );
      if (isBlank) parsedContent = null;
    }
    await updateBlock(blockId, {
      title: formName.trim(),
      description: parsedDesc,
      default_content: parsedContent,
      block_state,
      mandatory,
    });
    setTabIsDirty(false);
  }, [formName, formDesc, formContent, formUiState, updateBlock]);

  const { saveStatus, triggerSave, forceSave } = useAutoSave(doSave, 1500);

  // Trigger autosave whenever form changes and there are dirty changes in edit or multi
  useEffect(() => {
    if ((panelMode !== 'edit' && panelMode !== 'multi') || !activeSingleId || !tabIsDirty) return;
    triggerSave();
  }, [formName, formDesc, formContent, formUiState, tabIsDirty, panelMode, activeSingleId]); // eslint-disable-line react-hooks/exhaustive-deps

  // Convenience wrapper used by saveIfPending and manual saves
  const saveCurrentTab = useCallback(async () => {
    if (!tabIsDirty || !activeSingleId) return;
    await forceSave();
  }, [tabIsDirty, activeSingleId, forceSave]);

  const handleBlockClick = (blockId: string) => {
    if (clickTimerRef.current) clearTimeout(clickTimerRef.current);
    clickTimerRef.current = setTimeout(async () => {
      // Abort navigation if the current block has an invalid name.
      if (activeSingleId && blockId !== activeSingleId) {
        const nameErr = validateBlockName(formName);
        if (nameErr) {
          setNameError(nameErr);
          setActiveTab('properties');
          return;
        }
      }
      if (tabIsDirty && activeSingleId) await saveCurrentTab();
      const block = blocks.find((b) => b.id === blockId);
      if (!block) return;
      setSelectedBlockIds([blockId]);
      setActiveSingleId(blockId);
      setPanelMode('edit');
      setShowCommentPanel(true);
      loadFormFromBlock(block);
      setActiveTab('properties');
    }, 200);
  };

  const handleToggleSelectAll = () => {
    if (selectedBlockIds.length === blocks.length && blocks.length > 0) {
      setSelectedBlockIds([]);
      setActiveSingleId(null);
      setPanelMode('empty');
    } else {
      setSelectedBlockIds(blocks.map(b => b.id));
      // setMultiIndex(0);
      setPanelMode('multi');
      if (blocks[0]) {
        setActiveSingleId(blocks[0].id);
        loadFormFromBlock(blocks[0]);
      }
    }
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const newIndex = blocks.findIndex((i) => i.id === over.id);
      void reorderBlocks(active.id as string, newIndex);
    }
  };

  useImperativeHandle(ref, () => ({
    saveIfPending: async () => {
      if (tabIsDirty) await forceSave();
    },
    discardInvalidBlocks: async () => {
      const invalidIds = blocks.filter(b => !b.title?.trim()).map(b => b.id);
      for (const id of invalidIds) await deleteBlock(id);
      if (activeSingleId && invalidIds.includes(activeSingleId)) {
        setActiveSingleId(null);
        setSelectedBlockIds([]);
        setPanelMode('empty');
      }
    },
  }));

  const handleAddBlock = async () => {
    // Block creation if the current block still has an invalid name.
    if (activeSingleId) {
      const nameErr = validateBlockName(formName);
      if (nameErr) {
        setNameError(nameErr);
        setActiveTab('properties');
        return;
      }
    }
    setBusy(true);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG['editable'].payload;
      const newBlock = await createBlock({
        title: null,
        type: 'paragraph',
        block_state,
        mandatory,
      });
      setSelectedBlockIds([newBlock.id]);
      setActiveSingleId(newBlock.id);
      setPanelMode('edit');
      loadFormFromBlock(newBlock);
      setActiveTab('properties');
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async () => {
    setBusy(true);
    try {
      if (panelMode === 'multi' && selectedBlockIds.length > 0) {
        // Delete all selected blocks
        for (const id of selectedBlockIds) {
          await deleteBlock(id);
        }
        setSelectedBlockIds([]);
        setActiveSingleId(null);
        // setMultiIndex(0);
      } else if (activeSingleId) {
        await deleteBlock(activeSingleId);
        setActiveSingleId(null);
        setSelectedBlockIds([]);
      }
      setPanelMode('empty');
      setDeleteModal(false);
    } finally {
      setBusy(false);
    }
  };

  const handleDuplicate = async () => {
    setBusy(true);
    try {
      const ids = panelMode === 'multi' ? selectedBlockIds : (activeSingleId ? [activeSingleId] : []);
      for (const id of ids) {
        const source = blocks.find((b) => b.id === id);
        if (!source) continue;
        const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[blockToUiState(source)].payload;
        await createBlock({
          title: `${source.title ?? 'Bloque'} (copia)`,
          type: source.type,
          block_state,
          mandatory,
          default_content: source.default_content,
          description: source.description,
        });
      }
    } finally {
      setBusy(false);
    }
  };

  const handleCancel = async () => {
    if (tabIsDirty && activeSingleId) {
      const original = blocks.find((b) => b.id === activeSingleId);
      if (original) loadFormFromBlock(original);
    }
    setPanelMode('empty');
    setActiveSingleId(null);
    setSelectedBlockIds([]);
    // setMultiIndex(0);
  };

  const handleSendMessage = useCallback(async (parentId: string | null, body: string) => {
    if (!activeSingleId) return;
    const res = await apiFetchJson<{ data: any }>(`templates/${template.id}/comments`, {
      method: 'POST',
      body: { body, parent_id: parentId, blockable_id: activeSingleId },
    });
    onCommentAdded?.(res.data);
  }, [activeSingleId, template.id, onCommentAdded]);

  const renderSaveStatus = () => {
    if (saveStatus === 'saving') return <span className="text-xs text-text-muted italic">Guardando…</span>;
    if (saveStatus === 'saved') return <span className="text-xs text-success-dark flex items-center gap-1">✓ Guardado</span>;
    if (saveStatus === 'error') return <span className="text-xs text-danger-dark">Error al guardar</span>;
    return null;
  };

  return (
    <div className={isEditorFullscreen
      ? 'fixed inset-0 z-[100] bg-white dark:bg-ui-dark-card flex flex-col'
      : 'flex-1 overflow-hidden flex flex-col md:flex-row'
    }>
      {/* Sidebar — hidden when editor is in fullscreen */}
      {!isEditorFullscreen && <div className="md:w-1/4 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card overflow-hidden">
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
          <span className="text-xs font-bold uppercase text-text-secondary tracking-widest">Bloques ({blocks.length})</span>
          <Button variant="ghost" size="xs" onClick={handleToggleSelectAll}>
            {selectedBlockIds.length === blocks.length && blocks.length > 0 ? 'Deseleccionar todos' : 'Seleccionar todos'}
          </Button>
        </div>
        <div className="flex-1 overflow-y-auto p-4 space-y-2">
          {loading ? (
            <div className="text-xs text-text-muted p-4">Cargando bloques...</div>
          ) : (
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
              <SortableContext items={blocks.map(b => b.id)} strategy={verticalListSortingStrategy}>
                {blocks.map((block) => (
                  <SortableBlockItem
                    key={block.id}
                    block={block}
                    itemState={activeSingleId === block.id ? 'selected' : (selectedBlockIds.includes(block.id) ? 'multi-queued' : 'default')}
                    onClick={() => handleBlockClick(block.id)}
                    hasReviewComments={reviewComments.some(c => c.blockable_id === block.id)}
                  />
                ))}
              </SortableContext>
            </DndContext>
          )}
        </div>
        <div className="p-4 border-t border-ui-border dark:border-ui-dark-border">
          <Button variant="outline" className="w-full border-dashed" onClick={handleAddBlock} loading={busy}>+ Añadir bloque</Button>
        </div>
      </div>}

      {/* Main Panel */}
      <div className="flex-1 min-w-0 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-hidden">
        {panelMode === 'empty' && (
          <div className="flex-1 flex flex-col items-center justify-center p-6 text-center opacity-40">
            <p className="text-sm font-bold uppercase tracking-widest">Selecciona un bloque para editar</p>
          </div>
        )}

        {(panelMode === 'edit' || panelMode === 'multi') && selectedBlock && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            {/* Compact fullscreen header — replaces regular header + tabs when fullscreen */}
            {isEditorFullscreen && (
              <div className="shrink-0 h-11 px-4 flex items-center gap-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
                <button
                  type="button"
                  aria-label="Salir de pantalla completa"
                  title="Salir de pantalla completa (Esc)"
                  onClick={() => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))}
                  className="shrink-0 p-1.5 rounded text-text-muted hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus-visible:ring-2 focus-visible:ring-odoo-purple/50"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3" /><path d="M21 8h-3a2 2 0 0 1-2-2V3" />
                    <path d="M3 16h3a2 2 0 0 1 2 2v3" /><path d="M16 21v-3a2 2 0 0 1 2-2h3" />
                  </svg>
                </button>
                <h3 className="flex-1 text-sm font-bold truncate uppercase tracking-widest">{selectedBlock.title}</h3>
                {renderSaveStatus()}
                {onContinue && (
                  <Button variant="primary" size="xs" onClick={onContinue} className="shrink-0">
                    Guardar y continuar →
                  </Button>
                )}
              </div>
            )}

            {/* Regular header — hidden in fullscreen */}
            {!isEditorFullscreen && (
              <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
                <div className="flex items-center gap-3 min-w-0">
                  <h3 className="text-sm font-bold truncate uppercase tracking-widest">{selectedBlock.title}</h3>
                  {renderSaveStatus()}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  {!showCommentPanel && formName.trim() && (
                    <Button
                      variant="outline"
                      size="xs"
                      onClick={() => setShowCommentPanel(true)}
                      className="text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5"
                    >
                      Comentarios
                    </Button>
                  )}
                  <Button variant="outline" size="xs" onClick={handleDuplicate} disabled={busy}>Duplicar</Button>
                  <Button variant="outline" size="xs" className="text-danger hover:bg-danger/5 hover:border-danger/40" onClick={() => setDeleteModal(true)}>Eliminar</Button>
                  <Button variant="ghost" size="xs" className="hover:text-text-primary" onClick={() => void handleCancel()}>Cancelar</Button>
                </div>
              </div>
            )}

            {/* Tabs — hidden in fullscreen */}
            {!isEditorFullscreen && (
              <div className="flex border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card">
                {(['properties', 'content', 'description'] as TabId[]).map(tab => {
                  const isTabDisabled = (tab === 'content' || tab === 'description') && validateBlockName(formName) !== '';

                  return (
                    <button
                      key={tab}
                      onClick={() => {
                      if (!isTabDisabled) {
                        setActiveTab(tab);
                      }
                    }}
                      disabled={isTabDisabled}
                      title={isTabDisabled ? (validateBlockName(formName) || 'Asigna un nombre válido al bloque para habilitar esta pestaña') : ''}
                      className={`px-4 py-2 text-xs font-bold uppercase tracking-widest border-b-2 transition-colors flex items-center gap-1.5 ${
                        activeTab === tab ? 'border-odoo-purple text-odoo-purple' : 'border-transparent text-text-muted hover:text-text-primary'
                      } ${isTabDisabled ? 'opacity-30 cursor-not-allowed' : ''}`}
                    >
                      {tab === 'properties' ? 'Propiedades' : tab === 'content' ? 'Contenido' : 'Descripción'}
                    </button>
                  );
                })}
              </div>
            )}

            <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
              {activeTab === 'properties' && !isEditorFullscreen && (
                <div className="flex-1 overflow-y-auto p-6">
                  <div className="w-full bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                    <div className="p-6 space-y-4">
                      <div>
                        <FieldLabel required>Nombre del bloque</FieldLabel>
                        <TextInput
                          value={formName}
                          placeholder="Nuevo bloque"
                          error={!!nameError}
                          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                            setFormName(e.target.value);
                            setNameError(validateBlockName(e.target.value));
                            setTabIsDirty(true);
                          }}
                          onBlur={() => setNameError(validateBlockName(formName))}
                        />
                        {nameError && <p className="mt-1 text-xs text-danger">{nameError}</p>}
                      </div>
                      <div>
                        <FieldLabel>Estado</FieldLabel>
                        <BlockUiStateToggle value={formUiState} onChange={s => { setFormUiState(s); setTabIsDirty(true); }} />
                      </div>
                      <p className="text-xs text-text-muted italic">
                        Se guarda automáticamente tras 1500 ms de inactividad o al cambiar de pestaña.
                      </p>
                    </div>
                  </div>
                </div>
              )}
              {activeTab === 'content' && (
                <ErrorBoundary fallback={<div className="p-4 text-danger">Error al cargar el editor de contenido.</div>}>
                  <div className="flex-1 min-h-0 p-6 flex flex-col">
                    {!formName.trim() ? (
                      <div className="flex-1 flex flex-col items-center justify-center p-12 text-center bg-white dark:bg-ui-dark-card rounded-xl border border-dashed border-ui-border dark:border-ui-dark-border opacity-60">
                        <div className="text-4xl mb-4">📝</div>
                        <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                          Asigna un nombre al bloque en "Propiedades" para habilitar el editor de contenido.
                        </p>
                      </div>
                    ) : (
                      <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                        <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
                          <BlockNoteEditorPanel
                            key={`content-${activeSingleId ?? 'none'}`}
                            initialContent={(() => { try { return JSON.parse(formContent); } catch { return undefined; } })()}
                            onChange={json => {
                              setFormContent(JSON.stringify(json));
                              setTabIsDirty(true);
                            }}
                            editable={true}
                            isDark={effectiveIsDark}
                            onFullscreenChange={handleEditorFullscreenChange}
                          />
                        </Suspense>
                      </div>
                    )}
                  </div>
                </ErrorBoundary>
              )}
              {activeTab === 'description' && (
                <ErrorBoundary fallback={<div className="p-4 text-danger">Error al cargar el editor de descripción.</div>}>
                  <div className="flex-1 min-h-0 p-6 flex flex-col">
                    {!formName.trim() ? (
                      <div className="flex-1 flex flex-col items-center justify-center p-12 text-center bg-white dark:bg-ui-dark-card rounded-xl border border-dashed border-ui-border dark:border-ui-dark-border opacity-60">
                        <div className="text-4xl mb-4">📝</div>
                        <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                          Asigna un nombre al bloque en "Propiedades" para habilitar el editor de descripción.
                        </p>
                      </div>
                    ) : (
                      <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                        <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
                          <BlockNoteEditorPanel
                            key={`description-${activeSingleId ?? 'none'}`}
                            initialContent={(() => { try { return JSON.parse(formDesc); } catch { return undefined; } })()}
                            onChange={json => { setFormDesc(JSON.stringify(json)); setTabIsDirty(true); }}
                            editable={true}
                            isDark={effectiveIsDark}
                            onFullscreenChange={handleEditorFullscreenChange}
                          />
                        </Suspense>
                      </div>
                    )}
                  </div>
                </ErrorBoundary>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Right: comment panel — creator-edit mode, available even if no comments */}
      {showCommentPanel && !isEditorFullscreen && panelMode === 'edit' && selectedBlock && formName.trim() && (
        <div className="hidden md:flex md:w-[35%] shrink-0 border-l border-ui-border dark:border-ui-dark-border flex-col p-4 h-full">
          <BlockCommentsCard
            mode="creator-edit"
            blockSortOrder={selectedBlockIndex >= 0 ? selectedBlockIndex + 1 : '?'}
            blockComments={blockComments}
            allComments={reviewComments}
            onSendMessage={handleSendMessage}
            onClose={() => setShowCommentPanel(false)}
          />
        </div>
      )}

      <ConfirmDialog
        open={deleteModal}
        title="¿Eliminar bloque?"
        description={panelMode === 'multi' ? `¿Seguro que quieres eliminar ${selectedBlockIds.length} bloques?` : 'Esta acción no se puede deshacer.'}
        confirmLabel="Eliminar"
        variant="danger"
        onConfirm={handleDelete}
        onCancel={() => setDeleteModal(false)}
        loading={busy}
      />
    </div>
  );
});

WizardStep2Blocks.displayName = 'WizardStep2Blocks';
