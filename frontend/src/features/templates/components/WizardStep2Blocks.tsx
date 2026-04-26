import { useEffect, useState, useRef, lazy, Suspense, forwardRef, useImperativeHandle } from 'react';
import { useDarkMode } from '../../../hooks/useDarkMode';
import { ErrorBoundary } from '../../../components/ErrorBoundary';

const BlockNoteEditorPanel = lazy(() => import('./BlockNoteEditorPanel'));
import {
  DndContext,
  closestCenter,
  type DragEndEvent,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Button, ConfirmDialog, FieldLabel, TextInput } from '../../../ui';
import type { TemplateBlock } from '../../../types/blocks';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { BlockContentHtml } from './BlockContentHtml';
import type { Template } from '../../../types/templates';
import {
  type BlockUiState,
  BLOCK_UI_STATE_CONFIG,
  blockToUiState,
} from '../blockUiState';

// ── Types ────────────────────────────────────────────────────────────────────

type PanelMode = 'empty' | 'summary' | 'edit' | 'create' | 'multi';
type TabId = 'properties' | 'content' | 'description' | 'comments';
type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

type BlockItemState =
  | 'default'
  | 'selected'
  | 'multi-queued'
  | 'multi-current'
  | 'multi-saved';

// ── Sortable block item ───────────────────────────────────────────────────────

function SortableBlockItem({
  block,
  itemState,
  onClick,
  onDoubleClick,
  hasReviewComments,
}: {
  block: TemplateBlock;
  itemState: BlockItemState;
  onClick: (e: React.MouseEvent) => void;
  onDoubleClick: (e: React.MouseEvent) => void;
  hasReviewComments?: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: block.id,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 20 : 1,
    position: 'relative',
    opacity: isDragging ? 0.6 : itemState === 'multi-saved' ? 0.65 : 1,
  };

  const uiState = blockToUiState(block);
  const cfg = BLOCK_UI_STATE_CONFIG[uiState];

  const containerCls =
    itemState === 'selected' || itemState === 'multi-current'
      ? 'bg-odoo-purple/10 border-odoo-purple/30 shadow-sm'
      : itemState === 'multi-queued'
        ? 'bg-odoo-purple/5 border-odoo-purple/20'
        : itemState === 'multi-saved'
          ? 'bg-success/5 border-success/20'
          : 'bg-white dark:bg-ui-dark-card border-ui-border/50 hover:bg-ui-body hover:border-ui-border dark:hover:bg-ui-dark-bg dark:border-ui-dark-border/50';

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`group w-full rounded px-3 py-2 flex items-center gap-2 transition-all min-h-11 border ${containerCls}`}
    >
      {itemState === 'multi-saved' && (
        <span className="shrink-0 w-2 h-2 rounded-full bg-success" />
      )}
      {(itemState === 'multi-queued' || itemState === 'multi-current') && (
        <span
          className={`shrink-0 w-2 h-2 rounded-full ${itemState === 'multi-current' ? 'bg-odoo-purple' : 'bg-odoo-purple/40'}`}
        />
      )}

      <button
        type="button"
        className="shrink-0 w-6 h-6 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary transition-colors focus:outline-none"
        {...attributes}
        {...listeners}
      >
        ⠿
      </button>

      <button
        type="button"
        onClick={onClick}
        onDoubleClick={onDoubleClick}
        className="flex-1 text-left min-w-0 flex items-center gap-2 focus:outline-none select-none"
      >
        <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
          {block.title || 'Bloque sin nombre'}
        </span>
        {hasReviewComments && (
          <span className="shrink-0 w-5 h-5 bg-amber-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-sm animate-pulse">
            !
          </span>
        )}
        {itemState === 'multi-saved' ? (
          <span className="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-success/10 text-success border border-success/20">
            ✓
          </span>
        ) : (
          <span className={`shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-tight ${cfg.badgeCls}`}>
            {cfg.label}
          </span>
        )}
      </button>
    </div>
  );
}

// ── BlockUiStateToggle ────────────────────────────────────────────────────────

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
    <div className="flex flex-wrap gap-2">
      {(['optional', 'editable', 'modifiable', 'locked'] as BlockUiState[]).map((s) => (
        <button
          key={s}
          type="button"
          disabled={disabled}
          onClick={() => onChange(s)}
          className={[
            'px-3 py-1.5 rounded text-xs font-medium transition-all border min-h-9',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
            value === s
              ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
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

// ── Helpers ───────────────────────────────────────────────────────────────────

function descriptionToBlockNote(desc: string): unknown[] | undefined {
  if (!desc.trim()) return undefined;
  try {
    const parsed: unknown = JSON.parse(desc);
    if (Array.isArray(parsed) && parsed.length > 0) return parsed;
    if (parsed && typeof parsed === 'object') {
      const o = parsed as Record<string, unknown>;
      if (o.type === 'doc' && Array.isArray(o.content) && (o.content as unknown[]).length > 0) {
        return o.content as unknown[];
      }
    }
  } catch { /* fallthrough to plain text */ }
  return [{ type: 'paragraph', content: [{ type: 'text', text: desc.trim() }] }];
}

function normalizeDescriptionForDisplay(desc: unknown): unknown[] {
  if (!desc) return [];
  if (typeof desc === 'string') {
    if (!desc.trim()) return [];
    try {
      const parsed: unknown = JSON.parse(desc);
      if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      if (parsed && typeof parsed === 'object') {
        const o = parsed as Record<string, unknown>;
        if (o.type === 'doc' && Array.isArray(o.content)) return o.content as unknown[];
      }
    } catch { /* fallthrough */ }
    return [{ type: 'paragraph', content: [{ type: 'text', text: desc.trim() }] }];
  }
  if (Array.isArray(desc) && desc.length > 0) return desc;
  return [];
}

// ── Main component ────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  reviewComments?: any[];
  onResolveComment?: (commentId: string) => Promise<void>;
};

export type WizardStep2BlocksHandle = {
  saveIfPending: () => Promise<void>;
};

export const WizardStep2Blocks = forwardRef<WizardStep2BlocksHandle, Props>(
function WizardStep2Blocks({ template, reviewComments = [], onResolveComment }, ref) {
  const { isDark } = useDarkMode();
  const { blocks, createBlock, updateBlock, deleteBlock, reorderBlocks } =
    useTemplateBlocks(template.id);

  const sensors = useSensors(useSensor(PointerSensor));

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const newIndex = blocks.findIndex((b: TemplateBlock) => b.id === over.id);
    if (newIndex < 0) return;
    void reorderBlocks(active.id.toString(), newIndex);
  };

  const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [activeSingleId, setActiveSingleId] = useState<string | null>(null);

  const [multiIndex, setMultiIndex] = useState(0);
  const [multiSaved, setMultiSaved] = useState<Set<string>>(new Set());
  const clickTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    return () => {
      if (clickTimerRef.current) clearTimeout(clickTimerRef.current);
    };
  }, []);

  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteModal, setDeleteModal] = useState(false);
  const [activeTab, setActiveTab] = useState<TabId>('properties');
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const [tabIsDirty, setTabIsDirty] = useState(false);
  const autosaveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Autosave: 600ms after last form change in edit mode
  useEffect(() => {
    if (panelMode !== 'edit' || !activeSingleId || !tabIsDirty) return;
    if (autosaveTimerRef.current) clearTimeout(autosaveTimerRef.current);
    autosaveTimerRef.current = setTimeout(() => { void saveCurrentTab(); }, 600);
    return () => { if (autosaveTimerRef.current) clearTimeout(autosaveTimerRef.current); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [formName, formDesc, formContent, formUiState, tabIsDirty, panelMode, activeSingleId]);

  useEffect(() => {
    const tabs: TabId[] = ['properties', 'content', 'description'];
    const handleKey = (e: KeyboardEvent) => {
      if (!panelRef.current?.contains(document.activeElement)) return;
      if (panelMode !== 'edit' && panelMode !== 'create') return;
      const idx = tabs.indexOf(activeTab);
      if (e.key === 'Home') {
        e.preventDefault();
        if (idx > 0) void handleTabChange(tabs[idx - 1]!);
      }
      if (e.key === 'End') {
        e.preventDefault();
        if (idx < tabs.length - 1) void handleTabChange(tabs[idx + 1]!);
      }
    };
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, activeSingleId, panelMode, tabIsDirty]);

  // Derived
  const orderedSelection = blocks.filter((b: TemplateBlock) => selectedBlockIds.includes(b.id)).map((b: TemplateBlock) => b.id);
  const currentMultiId = orderedSelection[multiIndex] ?? null;
  const currentMultiBlock = currentMultiId ? (blocks.find((b) => b.id === currentMultiId) ?? null) : null;
  const selectedBlock = activeSingleId ? (blocks.find((b) => b.id === activeSingleId) ?? null) : null;
  const allBlocksSelected = blocks.length > 0 && selectedBlockIds.length === blocks.length;

  // ── Helpers ──────────────────────────────────────────────────────────────────

  const loadFormFromBlock = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setFormDesc(block.description ? JSON.stringify(block.description) : '');
    setFormContent(block.default_content ? JSON.stringify(block.default_content) : '');
    setFormUiState(blockToUiState(block));
    setActionError(null);
  };

  const resetForm = () => {
    setFormName('');
    setFormDesc('');
    setFormContent('');
    setFormUiState('optional');
    setActionError(null);
    setDeleteModal(false);
    setActiveTab('properties');
    setTabIsDirty(false);
    setSaveStatus('idle');
  };

  // ── Tab auto-save ─────────────────────────────────────────────────────────────

  const saveCurrentTab = async () => {
    if (!tabIsDirty || !activeSingleId) return;
    setSaveStatus('saving');
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState as BlockUiState].payload;
      const parsedContent = formContent ? (() => { try { return repairBlockNoteBlocks(JSON.parse(formContent)); } catch { return null; } })() : null;
      const parsedDesc = formDesc ? (() => { try { return repairBlockNoteBlocks(JSON.parse(formDesc)); } catch { return null; } })() : null;
      await updateBlock(activeSingleId, {
        title: formName.trim() || undefined,
        description: parsedDesc,
        default_content: parsedContent,
        block_state,
        mandatory,
      });
      setTabIsDirty(false);
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus('idle'), 3000);
    } catch {
      setSaveStatus('error');
    }
  };

  const handleTabChange = async (newTab: TabId) => {
    if (newTab === activeTab) return;
    await saveCurrentTab();
    setActiveTab(newTab);
  };

  // ── Click handlers ────────────────────────────────────────────────────────────

  const handleBlockClick = (blockId: string) => {
    if (clickTimerRef.current) clearTimeout(clickTimerRef.current);

    clickTimerRef.current = setTimeout(async () => {
      if (tabIsDirty && activeSingleId) await saveCurrentTab();
      const block = blocks.find((b: TemplateBlock) => b.id === blockId);
      if (!block) return;
      setSelectedBlockIds([blockId]);
      setMultiSaved(new Set());
      setActionError(null);
      setDeleteModal(false);
      openEdit(block);
    }, 200);
  };

  const handleBlockDoubleClick = (blockId: string) => {
    if (clickTimerRef.current) clearTimeout(clickTimerRef.current);

    setSelectedBlockIds((prev: string[]) => {
      const alreadySelected = prev.includes(blockId);
      const newIds = alreadySelected ? prev.filter((id: string) => id !== blockId) : [...prev, blockId];

      if (newIds.length === 0) {
        setPanelMode('empty');
        setActiveSingleId(null);
      } else if (newIds.length === 1) {
        const block = blocks.find((b: TemplateBlock) => b.id === newIds[0]);
        if (block) openEdit(block);
      } else {
        const ordered = blocks.filter((b: TemplateBlock) => newIds.includes(b.id)).map((b: TemplateBlock) => b.id);
        setMultiIndex(0);
        setMultiSaved(new Set());
        const first = blocks.find((b) => b.id === ordered[0]);
        if (first) loadFormFromBlock(first);
        setPanelMode('multi');
      }

      return newIds;
    });
    setActionError(null);
    setDeleteModal(false);
  };

  const handleToggleSelectAll = () => {
    if (allBlocksSelected) {
      setSelectedBlockIds([]);
      setPanelMode('empty');
      setActiveSingleId(null);
    } else {
      const allIds = blocks.map((b: TemplateBlock) => b.id);
      setSelectedBlockIds(allIds);
      if (allIds.length === 1) {
        setActiveSingleId(allIds[0]);
        setPanelMode('summary');
      } else if (allIds.length >= 2) {
        setMultiIndex(0);
        setMultiSaved(new Set());
        if (blocks[0]) loadFormFromBlock(blocks[0]);
        setPanelMode('multi');
      }
    }
  };

  // ── Single-block CRUD ────────────────────────────────────────────────────────

  const openCreate = async () => {
    if (busy) return;
    resetForm();
    setSelectedBlockIds([]);
    setActiveSingleId(null);
    setActiveTab('properties');
    setPanelMode('create');
  };

  const openEdit = (block: TemplateBlock) => {
    loadFormFromBlock(block);
    setActiveTab('properties');
    setTabIsDirty(false);
    setSaveStatus('idle');
    setDeleteModal(false);
    setPanelMode('edit');
    setActiveSingleId(block.id);
  };

  const handleAddBlock = async (): Promise<boolean> => {
    if (!formName.trim()) return false;
    setBusy(true);
    setActionError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState as BlockUiState].payload;
      const parsedContent = formContent ? (() => { try { return JSON.parse(formContent); } catch { return null; } })() : null;
      const parsedDesc = formDesc ? (() => { try { return repairBlockNoteBlocks(JSON.parse(formDesc)); } catch { return null; } })() : null;
      const newBlock = await createBlock({
        type: 'paragraph',
        title: formName.trim(),
        description: parsedDesc,
        default_content: parsedContent ? repairBlockNoteBlocks(parsedContent) : null,
        block_state,
        mandatory,
      });
      resetForm();
      openEdit(newBlock);
      return true;
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al crear el bloque');
      return false;
    } finally {
      setBusy(false);
    }
  };

  useImperativeHandle(ref, () => ({
    saveIfPending: async () => {
      if (panelMode === 'create' && formName.trim()) {
        const ok = await handleAddBlock();
        if (!ok) throw new Error('block-save-failed');
      }
    },
  }));

  const handleDelete = async () => {
    if (!activeSingleId) return;
    setBusy(true);
    try {
      const remaining = blocks.filter((b: TemplateBlock) => b.id !== activeSingleId);
      await deleteBlock(activeSingleId);
      setDeleteModal(false);
      setTabIsDirty(false);
      setSaveStatus('idle');
      if (remaining.length > 0) {
        const first = remaining[0]!;
        setActiveSingleId(first.id);
        setSelectedBlockIds([first.id]);
        loadFormFromBlock(first);
        setActiveTab('properties');
        setPanelMode('summary');
      } else {
        setActiveSingleId(null);
        setSelectedBlockIds([]);
        setPanelMode('empty');
      }
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al eliminar el bloque');
    } finally {
      setBusy(false);
    }
  };

  // ── Multi-edit handlers ──────────────────────────────────────────────────────

  const handleMultiSaveAndNext = async () => {
    if (!formName.trim() || !currentMultiId) return;
    setBusy(true);
    setActionError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState as BlockUiState].payload;
      await updateBlock(currentMultiId, {
        title: formName.trim(),
        block_state,
        mandatory,
      });
      setMultiSaved((prev: Set<string>) => new Set([...prev, currentMultiId]));

      const nextIdx = multiIndex + 1;
      if (nextIdx < orderedSelection.length) {
        setMultiIndex(nextIdx);
        const nextBlock = blocks.find((b: TemplateBlock) => b.id === orderedSelection[nextIdx]);
        if (nextBlock) loadFormFromBlock(nextBlock);
      } else {
        setSelectedBlockIds([]);
        setMultiSaved(new Set());
        setPanelMode('empty');
        setActiveSingleId(null);
      }
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al guardar el bloque');
    } finally {
      setBusy(false);
    }
  };

  const handleMultiNavigate = (newIdx: number) => {
    if (newIdx < 0 || newIdx >= orderedSelection.length) return;
    setMultiIndex(newIdx);
    const target = blocks.find((b: TemplateBlock) => b.id === orderedSelection[newIdx]);
    if (target) loadFormFromBlock(target);
    setActionError(null);
  };

  const handleMultiCancelAll = () => {
    setSelectedBlockIds([]);
    setMultiSaved(new Set());
    setPanelMode('empty');
    setActiveSingleId(null);
    resetForm();
  };

  // ── Render ───────────────────────────────────────────────────────────────────

  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Columna Izquierda — 25% */}
      <div className="md:w-1/4 min-w-0 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden bg-white dark:bg-ui-dark-card">
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 flex items-center justify-between shrink-0">
          <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
            BLOQUES ({blocks.length})
          </span>
          <Button type="button" variant="ghost" size="xs" onClick={handleToggleSelectAll}>
            {allBlocksSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 min-h-0">
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={blocks.map((b: TemplateBlock) => b.id)} strategy={verticalListSortingStrategy}>
              <div className="space-y-2">
                {blocks.map((block) => {
                  let itemState: BlockItemState = 'default';
                  if (panelMode === 'multi') {
                    if (multiSaved.has(block.id)) itemState = 'multi-saved';
                    else if (block.id === currentMultiId) itemState = 'multi-current';
                    else if (selectedBlockIds.includes(block.id)) itemState = 'multi-queued';
                  } else if (selectedBlockIds.includes(block.id)) {
                    itemState = 'selected';
                  }
                  return (
                    <SortableBlockItem
                      key={block.id}
                      block={block}
                      itemState={itemState}
                      onClick={(e: React.MouseEvent) => { e.stopPropagation(); handleBlockClick(block.id); }}
                      onDoubleClick={(e: React.MouseEvent) => { e.stopPropagation(); handleBlockDoubleClick(block.id); }}
                      hasReviewComments={reviewComments.some(c => c.template_block_id === block.id && !c.resolved)}
                    />
                  );
                })}
              </div>
            </SortableContext>
          </DndContext>
        </div>

        <div className="shrink-0 p-4 border-t border-ui-border dark:border-ui-dark-border">
          <button
            type="button"
            onClick={openCreate}
            className="w-full text-center rounded-lg px-3 py-3 flex items-center justify-center border-2 border-dashed border-ui-border hover:border-odoo-purple/50 hover:text-odoo-purple transition-all text-text-muted"
          >
            <span className="text-sm font-medium">+ Añadir bloque</span>
          </button>
        </div>
      </div>

      {/* Columna Derecha — 75% */}
      <div className="flex-1 min-w-0 flex flex-col overflow-hidden bg-ui-body/30 dark:bg-ui-dark-bg">

        {/* empty */}
        {panelMode === 'empty' && (
          <div className="flex-1 flex flex-col items-center justify-center p-6 text-center animate-in fade-in">
            <svg className="w-10 h-10 text-text-muted mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5" />
            </svg>
            <p className="text-sm font-bold text-text-primary">Selecciona un bloque</p>
            <p className="text-xs text-text-muted mt-1">
              Clic para ver el resumen. Ctrl/⌘ + clic para selección múltiple.
            </p>
          </div>
        )}

        {/* summary */}
        {panelMode === 'summary' && selectedBlock && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0">
              <h3 className="text-sm font-bold text-text-primary truncate pr-4">{selectedBlock.title}</h3>
              <div className="flex gap-2">
                <Button variant="outline" size="xs" onClick={() => openEdit(selectedBlock)}>Editar</Button>
                <Button variant="outline" size="xs" className="text-danger" onClick={() => setDeleteModal(true)}>Eliminar</Button>
              </div>
            </div>
            <div className="flex-1 overflow-y-auto p-6 space-y-6">
              <dl className="grid grid-cols-1 gap-6">
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Nombre</dt>
                  <dd className="mt-1 text-sm font-medium">{selectedBlock.title}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Descripción</dt>
                  <dd className="mt-1 text-sm text-text-secondary">
                    {(() => {
                      const desc = selectedBlock.description;
                      if (!desc) return '—';
                      const blocks = normalizeDescriptionForDisplay(desc);
                      return blocks.length > 0 ? <BlockContentHtml content={blocks} /> : '—';
                    })()}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Estado</dt>
                  <dd className="mt-2">
                    {(() => {
                      const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(selectedBlock)];
                      return (
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}>
                          {cfg.label}
                        </span>
                      );
                    })()}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Orden</dt>
                  <dd className="mt-1 text-sm">
                    {blocks.findIndex((b: TemplateBlock) => b.id === selectedBlock.id) + 1} de {blocks.length}
                  </dd>
                </div>
              </dl>
              <p className="text-xs text-text-muted italic pt-4 border-t border-ui-border dark:border-ui-dark-border">
                Pulsa «Editar» para modificar o «Eliminar» para borrar permanentemente.
              </p>
            </div>
          </div>
        )}

        {/* edit / create — tabbed panel */}
        {(panelMode === 'create' || panelMode === 'edit') && (
          <div ref={panelRef} className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            {/* Panel header */}
            <div className="px-5 pt-3 border-b border-ui-border dark:border-ui-dark-border shrink-0">
              <div className="flex items-center justify-between pb-1">
                <h3 className="text-sm font-bold text-text-primary truncate">
                  {panelMode === 'create' ? 'Nuevo bloque' : (selectedBlock?.title || 'Bloque sin nombre')}
                </h3>
                <div className="flex items-center gap-3 shrink-0">
                  {panelMode === 'edit' && (
                    <>
                      {saveStatus === 'saving' && (
                        <span className="text-[10px] text-text-muted">Guardando…</span>
                      )}
                      {saveStatus === 'saved' && (
                        <span className="text-[10px] text-success font-medium">✓ Guardado</span>
                      )}
                      {saveStatus === 'error' && (
                        <span className="text-[10px] text-danger-dark font-medium">Error al guardar</span>
                      )}
                      <Button
                        variant="outline"
                        size="xs"
                        className="text-danger"
                        onClick={() => setDeleteModal(true)}
                      >
                        Eliminar
                      </Button>
                    </>
                  )}
                </div>
              </div>
              {/* Tabs */}
              <div className="flex gap-0 -mb-px">
                {(['properties', 'content', 'description', 'comments'] as TabId[]).map((tab) => {
                  if (tab === 'comments' && reviewComments.length === 0) return null;
                  const labels: Record<TabId, string> = {
                    properties: 'Propiedades',
                    content: 'Contenido',
                    description: 'Descripción',
                    comments: 'Comentarios',
                  };
                  const isActive = activeTab === tab;
                  const blockComments = reviewComments.filter(c => c.template_block_id === activeSingleId);
                  const unresolvedCount = blockComments.filter(c => !c.resolved).length;

                  return (
                    <button
                      key={tab}
                      type="button"
                      disabled={isActive}
                      onClick={() => void handleTabChange(tab)}
                      className={[
                        'px-4 py-2 text-xs border-b-2 transition-all flex items-center gap-2',
                        isActive
                          ? 'border-odoo-purple text-odoo-purple font-medium cursor-default'
                          : 'border-transparent text-text-muted hover:text-text-primary cursor-pointer',
                      ].join(' ')}
                    >
                      {labels[tab]}
                      {tab === 'comments' && blockComments.length > 0 && (
                        <span className={[
                          'inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full text-[10px] font-black',
                          unresolvedCount > 0
                            ? 'bg-amber-500 text-white shadow-sm'
                            : 'bg-ui-border dark:bg-ui-dark-border text-text-muted'
                        ].join(' ')}>
                          {blockComments.length}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Tab content */}
            <div className={`flex-1 flex flex-col min-h-0 ${activeTab === 'content' || activeTab === 'description' ? 'overflow-hidden' : 'overflow-y-auto p-6'}`}>
              {activeTab === 'properties' && (
                <div className="space-y-4">
                  <div>
                    <FieldLabel required>Nombre del bloque</FieldLabel>
                    <TextInput
                      type="text"
                      fieldSize="comfortable"
                      value={formName}
                      onChange={(e: React.ChangeEvent<HTMLInputElement>) => { setFormName(e.target.value); setTabIsDirty(true); }}
                      placeholder="Ej. Introducción"
                    />
                  </div>
                  <div>
                    <FieldLabel required>Estado del bloque</FieldLabel>
                    <div className="mt-1">
                      <BlockUiStateToggle
                        value={formUiState}
                        onChange={(s) => { setFormUiState(s); setTabIsDirty(true); }}
                        disabled={busy}
                      />
                    </div>
                  </div>
                  {panelMode === 'edit' && (
                    <p className="text-[10px] text-text-muted italic">
                      Se guarda automáticamente tras 600 ms de inactividad o al cambiar de pestaña.
                    </p>
                  )}
                </div>
              )}

              {activeTab === 'content' && (
                <ErrorBoundary fallback={<div className="text-xs text-danger-dark dark:text-danger p-4">Error al cargar el editor de contenido.</div>}>
                  <Suspense fallback={<div className="text-xs text-text-muted p-4">Cargando editor…</div>}>
                    <BlockNoteEditorPanel
                      key={(activeSingleId ?? 'new') + '-content'}
                      initialContent={(() => { try { return JSON.parse(formContent); } catch { return undefined; } })()}
                      editable
                      isDark={isDark}
                      onChange={(content: unknown) => { setFormContent(JSON.stringify(content)); setTabIsDirty(true); }}
                    />
                  </Suspense>
                </ErrorBoundary>
              )}

              {activeTab === 'description' && (
                <ErrorBoundary fallback={<div className="text-xs text-danger-dark dark:text-danger p-4">Error al cargar el editor de descripción.</div>}>
                  <Suspense fallback={<div className="text-xs text-text-muted p-4">Cargando editor…</div>}>
                    <BlockNoteEditorPanel
                      key={(activeSingleId ?? 'new') + '-description'}
                      initialContent={(() => { try { return JSON.parse(formDesc); } catch { return undefined; } })()}
                      editable
                      isDark={isDark}
                      onChange={(content: unknown) => { setFormDesc(JSON.stringify(content)); setTabIsDirty(true); }}
                    />
                  </Suspense>
                </ErrorBoundary>
              )}

              {activeTab === 'comments' && (
                <div className="flex-1 overflow-y-auto p-6 space-y-4 bg-ui-body/30 dark:bg-ui-dark-bg/30">
                  {(() => {
                    const blockComments = reviewComments.filter(c => c.template_block_id === activeSingleId);
                    if (blockComments.length === 0) {
                      return (
                        <div className="flex flex-col items-center justify-center h-40 text-center opacity-40">
                          <span className="text-3xl mb-2">💬</span>
                          <p className="text-xs">No hay comentarios en este bloque.</p>
                        </div>
                      );
                    }
                    return blockComments.map(c => (
                      <div key={c.id} className={[
                        'bg-white dark:bg-ui-dark-card p-5 rounded-xl border shadow-sm transition-all duration-300',
                        c.resolved
                          ? 'opacity-60 border-ui-border dark:border-ui-dark-border grayscale-[0.5]'
                          : 'border-amber-200 dark:border-amber-800/50 hover:shadow-md hover:border-amber-300'
                      ].join(' ')}>
                        <div className="flex justify-between items-start gap-3 mb-3">
                          <div className="flex items-center gap-2">
                            <div className={[
                              'w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black',
                              c.resolved ? 'bg-success/20 text-success' : 'bg-odoo-purple/10 text-odoo-purple'
                            ].join(' ')}>
                              {c.author?.name ? c.author.name.charAt(0).toUpperCase() : '?'}
                            </div>
                            <div>
                              <p className="text-[10px] font-bold text-text-primary dark:text-text-dark-primary leading-none">
                                {c.author?.name || 'Validador'}
                              </p>
                              <p className="text-[9px] text-text-muted mt-0.5">
                                {c.resolved ? 'Resuelto' : 'Pendiente de corregir'}
                              </p>
                            </div>
                          </div>
                          <span className="text-[9px] text-text-muted font-medium bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5 rounded border border-ui-border dark:border-ui-dark-border">
                            {new Date(c.created_at).toLocaleDateString()}
                          </span>
                        </div>
                        <div className="pl-8">
                          <p className="text-xs text-text-primary dark:text-text-dark-primary leading-relaxed mb-4">
                            {c.body}
                          </p>
                          {!c.resolved && onResolveComment && (
                            <div className="flex justify-end">
                              <Button
                                variant="outline"
                                size="xs"
                                className="text-[9px] font-black uppercase tracking-widest text-success border-success/30 hover:bg-success hover:text-white transition-all shadow-sm"
                                onClick={() => void onResolveComment(c.id)}
                              >
                                ✓ Marcar como corregido
                              </Button>
                            </div>
                          )}
                        </div>
                      </div>
                    ));
                  })()}
                </div>
              )}

              {actionError && (
                <p className="text-xs text-danger-dark animate-in fade-in mt-4 px-6">{actionError}</p>
              )}
            </div>

            {/* Footer: only for create mode */}
            {panelMode === 'create' && (
              <div className="shrink-0 px-6 py-4 border-t border-ui-border dark:border-ui-dark-border flex gap-3">
                <Button
                  type="button"
                  variant="primary"
                  size="md"
                  className="flex-1"
                  loading={busy}
                  onClick={() => void handleAddBlock()}
                  disabled={!formName.trim()}
                >
                  Guardar bloque
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="md"
                  disabled={busy}
                  onClick={() => setPanelMode('empty')}
                >
                  Cancelar
                </Button>
              </div>
            )}
          </div>
        )}

        {/* multi */}
        {panelMode === 'multi' && currentMultiBlock && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in slide-in-from-right-4">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-odoo-purple/5 flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <span className="shrink-0 w-6 h-6 rounded-full bg-odoo-purple text-white text-[10px] font-bold flex items-center justify-center">
                  {multiIndex + 1}
                </span>
                <h3 className="text-sm font-bold text-odoo-purple truncate">
                  Edición múltiple ({multiIndex + 1} de {orderedSelection.length})
                </h3>
              </div>
              <button type="button" onClick={handleMultiCancelAll} className="text-text-muted hover:text-danger text-xs transition-colors">
                Cancelar todo
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6 space-y-4">
              {/* Navigation */}
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => handleMultiNavigate(multiIndex - 1)}
                  disabled={multiIndex === 0}
                  className="w-7 h-7 rounded-full border border-ui-border flex items-center justify-center text-xs text-text-secondary hover:border-odoo-purple/50 hover:text-odoo-purple disabled:opacity-30 disabled:pointer-events-none transition-all"
                >
                  ←
                </button>
                <span className="text-xs font-bold text-text-primary tabular-nums">
                  {multiIndex + 1} / {orderedSelection.length}
                </span>
                <button
                  type="button"
                  onClick={() => handleMultiNavigate(multiIndex + 1)}
                  disabled={multiIndex === orderedSelection.length - 1}
                  className="w-7 h-7 rounded-full border border-ui-border flex items-center justify-center text-xs text-text-secondary hover:border-odoo-purple/50 hover:text-odoo-purple disabled:opacity-30 disabled:pointer-events-none transition-all"
                >
                  →
                </button>
                <div className="flex-1 h-1.5 bg-ui-border rounded-full overflow-hidden ml-1">
                  <div
                    className="h-full bg-odoo-purple rounded-full transition-all duration-200"
                    style={{ width: `${((multiIndex + 1) / orderedSelection.length) * 100}%` }}
                  />
                </div>
              </div>

              {/* Form */}
              <div className="p-4 bg-white dark:bg-ui-dark-card border border-odoo-purple/20 rounded-lg shadow-sm space-y-4">
                <div>
                  <FieldLabel required>Nombre del bloque</FieldLabel>
                  <TextInput
                    type="text"
                    fieldSize="comfortable"
                    value={formName}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFormName(e.target.value)}
                    placeholder="Ej. Introducción"
                  />
                </div>
                <div>
                  <FieldLabel required>Estado del bloque</FieldLabel>
                  <div className="mt-1">
                    <BlockUiStateToggle value={formUiState} onChange={setFormUiState} disabled={busy} />
                  </div>
                </div>
                <div className="flex gap-2 pt-2">
                  <Button
                    type="button"
                    variant="primary"
                    size="md"
                    className="flex-1"
                    loading={busy}
                    onClick={() => void handleMultiSaveAndNext()}
                    disabled={!formName.trim()}
                  >
                    {multiIndex === orderedSelection.length - 1 ? 'Finalizar y guardar' : 'Guardar y siguiente bloque'}
                  </Button>
                  <Button type="button" variant="outline" size="md" disabled={busy} onClick={handleMultiCancelAll}>
                    Cancelar
                  </Button>
                </div>
                {actionError && <p className="text-xs text-danger-dark animate-in fade-in">{actionError}</p>}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Delete confirmation modal */}
      <ConfirmDialog
        open={deleteModal}
        title="¿Eliminar bloque?"
        description={
          <>
            Estás a punto de eliminar el bloque «<span className="font-bold text-text-primary dark:text-text-dark-primary">{selectedBlock?.title}</span>».
            Esta acción no se puede deshacer y el contenido se perderá permanentemente.
          </>
        }
        confirmLabel="Eliminar definitivamente"
        variant="danger"
        loading={busy}
        onCancel={() => setDeleteModal(false)}
        onConfirm={handleDelete}
      />
    </div>
  );
});
