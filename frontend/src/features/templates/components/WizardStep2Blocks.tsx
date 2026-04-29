import React, { useCallback, useEffect, useImperativeHandle, useRef, useState, Suspense } from 'react';
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

import { Button, ConfirmDialog, TextInput, FieldLabel } from '../../../ui';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { BlockNoteEditorPanel } from './BlockNoteEditorPanel';
import { type BlockUiState, BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';
import { useAutoSave } from '../../../hooks/useAutoSave';

type PanelMode = 'empty' | 'create' | 'edit' | 'multi';
type TabId = 'properties' | 'content' | 'description' | 'comments';

// ── Icons ────────────────────────────────────────────────────────────────────

function LockIcon() {
  return (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  );
}

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
            'px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-widest transition-all border',
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

  const uiState = blockToUiState(block);
  const isLocked = uiState === 'locked';

  const containerCls = [
    'flex items-center gap-2 rounded-lg px-3 py-2.5 border transition-all cursor-pointer group',
    itemState === 'selected'
      ? 'bg-odoo-purple/5 border-odoo-purple shadow-sm'
      : itemState === 'multi-current'
      ? 'bg-amber-50 border-amber-400 ring-2 ring-amber-200'
      : itemState === 'multi-saved'
      ? 'bg-success/5 border-success/30'
      : 'bg-white dark:bg-ui-dark-card border-ui-border dark:border-ui-dark-border hover:border-odoo-purple/40 hover:bg-ui-body/50',
  ].join(' ');

  return (
    <div ref={setNodeRef} style={style} className={containerCls} onClick={onClick}>
      <button
        type="button"
        {...attributes}
        {...listeners}
        className="shrink-0 w-5 h-5 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary focus:outline-none"
        onClick={(e) => e.stopPropagation()}
      >
        ⠿
      </button>
      <button
        type="button"
        onClick={onClick}
        className="flex-1 min-w-0 flex items-center gap-1.5 text-left focus:outline-none"
      >
        {isLocked && <span className="shrink-0 text-danger-dark"><LockIcon /></span>}
        <span className={`flex-1 truncate text-xs font-bold ${itemState === 'selected' ? 'text-odoo-purple' : 'text-text-primary dark:text-text-dark-primary'}`}>
          {block.title || 'Bloque sin nombre'}
        </span>
        {hasReviewComments && (
          <span className="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]" title="Comentarios pendientes" />
        )}
      </button>
    </div>
  );
}

// ── Main Component ───────────────────────────────────────────────────────────

interface WizardStep2BlocksProps {
  template: Template;
  isDark?: boolean;
  reviewComments?: any[];
  onResolveComment?: (commentId: string) => Promise<void>;
  onBlocksCountChange?: (count: number) => void;
}

export type WizardStep2BlocksHandle = {
  saveIfPending: () => Promise<void>;
};

export const WizardStep2Blocks = React.forwardRef<WizardStep2BlocksHandle, WizardStep2BlocksProps>(({
  template,
  isDark = false,
  reviewComments = [],
  onResolveComment,
  onBlocksCountChange
}, ref) => {
  const {
    blocks,
    loading,
    createBlock,
    updateBlock,
    deleteBlock,
    reorderBlocks
  } = useTemplateBlocks(template.id);

  useEffect(() => {
    onBlocksCountChange?.(blocks.length);
  }, [blocks.length, onBlocksCountChange]);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [activeSingleId, setActiveSingleId] = useState<string | null>(null);

  const [multiIndex, setMultiIndex] = useState(0);
  const clickTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [busy, setBusy] = useState(false);
  const [deleteModal, setDeleteModal] = useState(false);
  const [activeTab, setActiveTab] = useState<TabId>('properties');
  const [tabIsDirty, setTabIsDirty] = useState(false);
  // Ref to always have latest activeSingleId in the autosave closure
  const activeSingleIdRef = useRef<string | null>(null);
  activeSingleIdRef.current = activeSingleId;

  const selectedBlock = activeSingleId ? (blocks.find((b) => b.id === activeSingleId) ?? null) : null;
  const orderedSelection = blocks.filter((b) => selectedBlockIds.includes(b.id)).map((b) => b.id);
  const currentMultiId = orderedSelection[multiIndex] ?? null;

  const loadFormFromBlock = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setFormDesc(block.description ? (typeof block.description === 'string' ? block.description : JSON.stringify(block.description)) : '');
    setFormContent(block.default_content ? (typeof block.default_content === 'string' ? block.default_content : JSON.stringify(block.default_content)) : '');
    setFormUiState(blockToUiState(block));
    setTabIsDirty(false);
  };

  // ── useAutoSave (debounce 1500ms) — compartido en edit y multi ───────────────
  const doSave = useCallback(async () => {
    const blockId = activeSingleIdRef.current;
    if (!blockId) return;
    const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
    let parsedContent: unknown = null;
    let parsedDesc: unknown = null;
    try { parsedContent = formContent ? JSON.parse(formContent) : null; } catch { parsedContent = null; }
    try { parsedDesc = formDesc ? JSON.parse(formDesc) : null; } catch { parsedDesc = null; }
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
      if (tabIsDirty && activeSingleId) await saveCurrentTab();
      const block = blocks.find((b) => b.id === blockId);
      if (!block) return;
      setSelectedBlockIds([blockId]);
      setActiveSingleId(blockId);
      setPanelMode('edit');
      loadFormFromBlock(block);
    }, 200);
  };

  const handleToggleSelectAll = () => {
    if (selectedBlockIds.length === blocks.length && blocks.length > 0) {
      setSelectedBlockIds([]);
      setPanelMode('empty');
    } else {
      setSelectedBlockIds(blocks.map(b => b.id));
      setMultiIndex(0);
      setPanelMode('multi');
      if (blocks[0]) loadFormFromBlock(blocks[0]);
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
    }
  }));

  const handleAddBlock = async () => {
    if (!formName.trim()) return;
    setBusy(true);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
      const newBlock = await createBlock({
        title: formName.trim(),
        type: 'paragraph',
        block_state,
        mandatory
      });
      setSelectedBlockIds([newBlock.id]);
      setActiveSingleId(newBlock.id);
      setPanelMode('edit');
      loadFormFromBlock(newBlock);
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
        setMultiIndex(0);
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
          type: 'paragraph',
          block_state,
          mandatory,
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
    setMultiIndex(0);
  };

  const renderSaveStatus = () => {
    if (saveStatus === 'saving') return <span className="text-[10px] text-text-muted italic">Guardando…</span>;
    if (saveStatus === 'saved') return <span className="text-[10px] text-success-dark flex items-center gap-1">✓ Guardado</span>;
    if (saveStatus === 'error') return <span className="text-[10px] text-danger-dark">Error al guardar</span>;
    return null;
  };

  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Sidebar */}
      <div className="md:w-1/4 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card overflow-hidden">
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
          <span className="text-[10px] font-bold uppercase text-text-secondary tracking-widest">Bloques ({blocks.length})</span>
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
                    hasReviewComments={reviewComments.some(c => c.template_block_id === block.id && !c.resolved)}
                  />
                ))}
              </SortableContext>
            </DndContext>
          )}
        </div>
        {/* Añadir bloque: oculto en edit y multi */}
        {panelMode !== 'edit' && panelMode !== 'multi' && (
          <div className="p-4 border-t border-ui-border dark:border-ui-dark-border">
            <Button variant="outline" className="w-full border-dashed" onClick={() => { setPanelMode('create'); setFormName(''); setFormUiState('editable'); setTabIsDirty(false); }}>+ Añadir bloque</Button>
          </div>
        )}
      </div>

      {/* Main Panel */}
      <div className="flex-1 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-hidden">
        {panelMode === 'empty' && (
          <div className="flex-1 flex flex-col items-center justify-center p-6 text-center opacity-40">
            <p className="text-sm font-bold uppercase tracking-widest">Selecciona un bloque para editar</p>
          </div>
        )}

        {panelMode === 'create' && (
          <div className="flex-1 flex flex-col p-8 space-y-6 animate-in fade-in">
            <h3 className="text-sm font-bold uppercase tracking-widest">Nuevo bloque</h3>
            <div className="space-y-4 max-w-lg bg-white dark:bg-ui-dark-card p-6 rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm">
              <div>
                <FieldLabel required>Nombre del bloque</FieldLabel>
                <TextInput value={formName} onChange={e => setFormName(e.target.value)} placeholder="Ej. Introducción" />
              </div>
              <div>
                <FieldLabel required>Estado inicial</FieldLabel>
                <BlockUiStateToggle value={formUiState} onChange={setFormUiState} />
              </div>
              <div className="flex gap-3 pt-2">
                <Button variant="primary" onClick={handleAddBlock} disabled={!formName.trim()} loading={busy} className="flex-1">Crear bloque</Button>
                <Button variant="ghost" onClick={() => setPanelMode('empty')}>Cancelar</Button>
              </div>
            </div>
          </div>
        )}

        {panelMode === 'edit' && selectedBlock && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
              <div className="flex items-center gap-3 min-w-0">
                <h3 className="text-sm font-bold truncate uppercase tracking-widest">{selectedBlock.title}</h3>
                {renderSaveStatus()}
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <Button variant="outline" size="xs" onClick={handleDuplicate} disabled={busy}>Duplicar</Button>
                <Button variant="outline" size="xs" className="text-danger hover:bg-danger/5 hover:border-danger/40" onClick={() => setDeleteModal(true)}>Eliminar</Button>
                <Button variant="ghost" size="xs" className="hover:text-text-primary" onClick={() => void handleCancel()}>Cancelar</Button>
              </div>
            </div>

            <div className="flex border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card">
              {(['properties', 'content', 'description', 'comments'] as TabId[]).map(tab => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-4 py-2 text-[10px] font-bold uppercase tracking-widest border-b-2 transition-colors ${
                    activeTab === tab ? 'border-odoo-purple text-odoo-purple' : 'border-transparent text-text-muted hover:text-text-primary'
                  }`}
                >
                  {tab === 'properties' ? 'Propiedades' : tab === 'content' ? 'Contenido' : tab === 'description' ? 'Notas' : 'Comentarios'}
                </button>
              ))}
            </div>

            <div className="flex-1 overflow-y-auto p-6">
              {activeTab === 'properties' && (
                <div className="space-y-4 max-w-lg bg-white dark:bg-ui-dark-card p-6 rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm">
                  <div>
                    <FieldLabel required>Nombre del bloque</FieldLabel>
                    <TextInput value={formName} onChange={e => { setFormName(e.target.value); setTabIsDirty(true); }} />
                  </div>
                  <div>
                    <FieldLabel>Estado</FieldLabel>
                    <BlockUiStateToggle value={formUiState} onChange={s => { setFormUiState(s); setTabIsDirty(true); }} />
                  </div>
                </div>
              )}
              {activeTab === 'content' && (
                <div className="flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                  <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
                    <BlockNoteEditorPanel
                      initialContent={(() => { try { return JSON.parse(formContent); } catch { return undefined; } })()}
                      onChange={json => { setFormContent(JSON.stringify(json)); setTabIsDirty(true); }}
                      editable={formUiState !== 'locked'}
                      isDark={isDark}
                    />
                  </Suspense>
                </div>
              )}
              {activeTab === 'description' && (
                <div className="flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                  <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
                    <BlockNoteEditorPanel
                      initialContent={(() => { try { return JSON.parse(formDesc); } catch { return undefined; } })()}
                      onChange={json => { setFormDesc(JSON.stringify(json)); setTabIsDirty(true); }}
                      editable
                      isDark={isDark}
                    />
                  </Suspense>
                </div>
              )}
              {activeTab === 'comments' && (
                <div className="space-y-4">
                  {reviewComments.filter(c => c.template_block_id === activeSingleId).map(c => (
                    <div key={c.id} className="p-4 rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card shadow-sm">
                      <p className="text-xs font-bold">{c.author?.name || 'Validador'}</p>
                      <p className="text-xs mt-1 text-text-secondary">{c.body}</p>
                      {!c.resolved && onResolveComment && (
                        <Button variant="outline" size="xs" className="mt-2 text-success border-success/30" onClick={() => void onResolveComment(c.id)}>✓ Corregido</Button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {panelMode === 'multi' && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            {/* Header multi */}
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
              <div className="flex items-center gap-3 min-w-0">
                <h3 className="text-sm font-bold text-odoo-purple uppercase tracking-widest truncate">
                  Edición múltiple ({multiIndex + 1}/{selectedBlockIds.length})
                </h3>
                {renderSaveStatus()}
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <Button variant="outline" size="xs" onClick={handleDuplicate} disabled={busy}>Duplicar</Button>
                <Button variant="outline" size="xs" className="text-danger hover:bg-danger/5 hover:border-danger/40" onClick={() => setDeleteModal(true)}>Eliminar</Button>
                <Button variant="ghost" size="xs" className="hover:text-text-primary" onClick={() => void handleCancel()}>Cancelar</Button>
              </div>
            </div>

            {/* Formulario de edición del bloque actual en multi */}
            <div className="flex-1 overflow-y-auto p-8">
              <div className="bg-white dark:bg-ui-dark-card p-8 rounded-2xl border border-odoo-purple/20 shadow-xl space-y-6 max-w-lg">
                <div className="space-y-2">
                  <FieldLabel required>Nombre del bloque</FieldLabel>
                  <TextInput value={formName} onChange={e => { setFormName(e.target.value); setTabIsDirty(true); }} />
                </div>
                <div className="flex gap-3">
                  <Button variant="primary" className="flex-1" loading={busy} onClick={async () => {
                    if (tabIsDirty) await forceSave();
                    if (multiIndex < selectedBlockIds.length - 1) {
                      const nextIdx = multiIndex + 1;
                      setMultiIndex(nextIdx);
                      setActiveSingleId(selectedBlockIds[nextIdx] ?? null);
                      const nextBlock = blocks.find(b => b.id === selectedBlockIds[nextIdx]);
                      if (nextBlock) loadFormFromBlock(nextBlock);
                    } else {
                      setPanelMode('empty');
                      setActiveSingleId(null);
                      setSelectedBlockIds([]);
                    }
                  }}>
                    {multiIndex === selectedBlockIds.length - 1 ? 'Finalizar' : 'Guardar y siguiente'}
                  </Button>
                  {multiIndex < selectedBlockIds.length - 1 && (
                    <Button variant="outline" onClick={async () => {
                      if (tabIsDirty) await forceSave();
                      const nextIdx = multiIndex + 1;
                      setMultiIndex(nextIdx);
                      setActiveSingleId(selectedBlockIds[nextIdx] ?? null);
                      const nextBlock = blocks.find(b => b.id === selectedBlockIds[nextIdx]);
                      if (nextBlock) loadFormFromBlock(nextBlock);
                    }} aria-label="Siguiente">→</Button>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      <ConfirmDialog
        open={deleteModal}
        title="¿Eliminar bloque?"
        description="Esta acción eliminará permanentemente el bloque y su contenido."
        variant="danger"
        confirmLabel="Eliminar"
        onCancel={() => setDeleteModal(false)}
        onConfirm={handleDelete}
        loading={busy}
      />
    </div>
  );
});
