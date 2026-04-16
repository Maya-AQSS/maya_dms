import { useEffect, useState } from 'react';
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
import { Button, FieldLabel, TextArea, TextInput } from '../../../ui';
import type { TemplateBlock } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import type { Template } from '../../../types/templates';
import {
  type BlockUiState,
  BLOCK_UI_STATE_CONFIG,
  blockToUiState,
} from '../blockUiState';

// ── Types ────────────────────────────────────────────────────────────────────

type PanelMode = 'empty' | 'summary' | 'edit' | 'create' | 'multi';

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
}: {
  block: TemplateBlock;
  itemState: BlockItemState;
  onClick: (e: React.MouseEvent) => void;
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
    itemState === 'selected'
      ? 'bg-odoo-purple/10 dark:bg-odoo-dark-purple/15 border-odoo-purple/30 shadow-sm'
      : itemState === 'multi-current'
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
      {/* Multi-mode dot */}
      {itemState === 'multi-saved' && (
        <span className="shrink-0 w-2 h-2 rounded-full bg-success" />
      )}
      {(itemState === 'multi-queued' || itemState === 'multi-current') && (
        <span
          className={`shrink-0 w-2 h-2 rounded-full ${
            itemState === 'multi-current' ? 'bg-odoo-purple' : 'bg-odoo-purple/40'
          }`}
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
        className="flex-1 text-left min-w-0 flex items-center gap-2 focus:outline-none"
      >
        <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
          {block.title || 'Bloque sin nombre'}
        </span>
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
      {(['editable', 'modifiable', 'locked', 'optional'] as BlockUiState[]).map((s) => (
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

// ── Main component ────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  onBlocksCountChange: (count: number) => void;
};

export function WizardStep2Blocks({ template, onBlocksCountChange }: Props) {
  const { blocks, loading, createBlock, updateBlock, deleteBlock, reorderBlocks } =
    useTemplateBlocks(template.id);

  const sensors = useSensors(useSensor(PointerSensor));

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const newIndex = blocks.findIndex((b) => b.id === over.id);
    void reorderBlocks(active.id.toString(), newIndex);
  };

  // Selection state
  const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [activeSingleId, setActiveSingleId] = useState<string | null>(null);

  // Multi-edit state
  const [multiIndex, setMultiIndex] = useState(0);
  const [multiSaved, setMultiSaved] = useState<Set<string>>(new Set());

  // Form state (shared: create / edit / multi)
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState(false);

  useEffect(() => {
    if (!loading) onBlocksCountChange(blocks.length);
  }, [blocks.length, loading, onBlocksCountChange]);

  // Derived
  const orderedSelection = blocks.filter((b) => selectedBlockIds.includes(b.id)).map((b) => b.id);
  const currentMultiId = orderedSelection[multiIndex] ?? null;
  const currentMultiBlock = currentMultiId ? (blocks.find((b) => b.id === currentMultiId) ?? null) : null;
  const selectedBlock = activeSingleId ? (blocks.find((b) => b.id === activeSingleId) ?? null) : null;
  const allBlocksSelected = blocks.length > 0 && selectedBlockIds.length === blocks.length;

  // ── Helpers ──────────────────────────────────────────────────────────────────

  const loadFormFromBlock = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setFormDesc(
      Array.isArray(block.default_content) ? (block.default_content as string[]).join('\n') : '',
    );
    setFormUiState(blockToUiState(block));
    setActionError(null);
  };

  const resetForm = () => {
    setFormName('');
    setFormDesc('');
    setFormUiState('editable');
    setActionError(null);
    setDeleteConfirm(false);
  };

  // ── Click handlers ────────────────────────────────────────────────────────────

  const handleBlockClick = (blockId: string, ctrlKey: boolean) => {
    if (ctrlKey) {
      const isAlready = selectedBlockIds.includes(blockId);
      const newIds = isAlready
        ? selectedBlockIds.filter((id) => id !== blockId)
        : [...selectedBlockIds, blockId];

      setSelectedBlockIds(newIds);
      setActionError(null);
      setDeleteConfirm(false);

      if (newIds.length === 0) {
        setPanelMode('empty');
        setActiveSingleId(null);
      } else if (newIds.length === 1) {
        setActiveSingleId(newIds[0]);
        setPanelMode('summary');
      } else {
        const ordered = blocks.filter((b) => newIds.includes(b.id)).map((b) => b.id);
        setMultiIndex(0);
        setMultiSaved(new Set());
        const first = blocks.find((b) => b.id === ordered[0]);
        if (first) loadFormFromBlock(first);
        setPanelMode('multi');
      }
    } else {
      setSelectedBlockIds([blockId]);
      setActiveSingleId(blockId);
      setPanelMode('summary');
      setMultiSaved(new Set());
      setActionError(null);
      setDeleteConfirm(false);
    }
  };

  const handleToggleSelectAll = () => {
    if (allBlocksSelected) {
      setSelectedBlockIds([]);
      setPanelMode('empty');
      setActiveSingleId(null);
    } else {
      const allIds = blocks.map((b) => b.id);
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

  const openCreate = () => {
    resetForm();
    setSelectedBlockIds([]);
    setActiveSingleId(null);
    setPanelMode('create');
  };

  const openSummary = (blockId: string) => {
    setActiveSingleId(blockId);
    setSelectedBlockIds([blockId]);
    setPanelMode('summary');
    setDeleteConfirm(false);
    setActionError(null);
  };

  const openEdit = (block: TemplateBlock) => {
    loadFormFromBlock(block);
    setPanelMode('edit');
  };

  const handleAddBlock = async () => {
    if (!formName.trim()) return;
    setBusy(true);
    setActionError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
      const newBlock = await createBlock({
        type: 'paragraph',
        title: formName.trim(),
        default_content: formDesc.trim() ? formDesc.split('\n').filter(Boolean) : null,
        block_state,
        mandatory,
      });
      resetForm();
      openSummary(newBlock.id);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al crear el bloque');
    } finally {
      setBusy(false);
    }
  };

  const handleSaveEdit = async () => {
    if (!formName.trim() || !activeSingleId) return;
    setBusy(true);
    setActionError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
      await updateBlock(activeSingleId, {
        title: formName.trim(),
        default_content: formDesc.trim() ? formDesc.split('\n').filter(Boolean) : null,
        block_state,
        mandatory,
      });
      setPanelMode('summary');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al guardar el bloque');
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async () => {
    if (!activeSingleId) return;
    setBusy(true);
    try {
      await deleteBlock(activeSingleId);
      setActiveSingleId(null);
      setSelectedBlockIds([]);
      setDeleteConfirm(false);
      setPanelMode('empty');
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
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
      await updateBlock(currentMultiId, {
        title: formName.trim(),
        default_content: formDesc.trim() ? formDesc.split('\n').filter(Boolean) : null,
        block_state,
        mandatory,
      });
      setMultiSaved((prev) => new Set([...prev, currentMultiId]));

      const nextIdx = multiIndex + 1;
      if (nextIdx < orderedSelection.length) {
        setMultiIndex(nextIdx);
        const nextBlock = blocks.find((b) => b.id === orderedSelection[nextIdx]);
        if (nextBlock) loadFormFromBlock(nextBlock);
      } else {
        // All done
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
    const target = blocks.find((b) => b.id === orderedSelection[newIdx]);
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

  // ── Block form (create / edit) ───────────────────────────────────────────────

  const renderBlockForm = (
    submitLabel: string,
    onSubmit: () => Promise<void>,
    onCancel: () => void,
  ) => (
    <div className="space-y-4">
      {formUiState === 'locked' && (
        <div className="px-3 py-2 bg-warning-light/20 border border-warning/30 rounded text-[11px] text-warning-dark font-bold">
          Bloque bloqueado: su obligatoriedad es siempre Obligatorio.
        </div>
      )}
      <div>
        <FieldLabel required>Nombre</FieldLabel>
        <TextInput
          type="text"
          fieldSize="comfortable"
          value={formName}
          onChange={(e) => setFormName(e.target.value)}
          placeholder="Ej: Introducción"
        />
      </div>
      <div>
        <FieldLabel>Descripción</FieldLabel>
        <TextArea
          fieldSize="comfortable"
          rows={2}
          value={formDesc}
          onChange={(e) => setFormDesc(e.target.value)}
          placeholder="Descripción del bloque…"
          style={{ minHeight: '52px' }}
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
          onClick={() => void onSubmit()}
          disabled={!formName.trim()}
        >
          {submitLabel}
        </Button>
        <Button type="button" variant="outline" size="md" disabled={busy} onClick={onCancel}>
          Cancelar
        </Button>
      </div>
      {actionError && <p className="text-xs text-danger-dark animate-in fade-in">{actionError}</p>}
    </div>
  );

  // ── Variant A — empty state ──────────────────────────────────────────────────

  if (!loading && blocks.length === 0) {
    return (
      <div className="flex-1 overflow-y-auto p-6 flex flex-col items-center justify-center">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ui-body dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border mb-4">
            <svg className="w-6 h-6 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
            Esta plantilla aún no tiene bloques
          </h3>
          <p className="text-xs text-text-muted mt-1 max-w-sm mx-auto">
            Añade el primer bloque usando el formulario. Los bloques definen la estructura del documento.
          </p>
        </div>
        <div className="w-full max-w-sm bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6">
          {renderBlockForm('Añadir bloque', handleAddBlock, resetForm)}
        </div>
      </div>
    );
  }

  // ── Variant B — two columns ──────────────────────────────────────────────────

  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Columna Izquierda */}
      <div className="md:w-1/2 min-w-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden bg-white dark:bg-ui-dark-card">
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 flex items-center justify-between shrink-0">
          <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
            BLOQUES ({blocks.length})
          </span>
          <Button type="button" variant="ghost" size="xs" onClick={handleToggleSelectAll}>
            {allBlocksSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-2">
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={blocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
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
                      onClick={(e) => handleBlockClick(block.id, e.ctrlKey || e.metaKey)}
                    />
                  );
                })}
              </div>
            </SortableContext>
          </DndContext>

          <button
            type="button"
            onClick={openCreate}
            className="w-full text-left rounded-lg px-3 py-3 flex items-center gap-2 border-2 border-dashed border-ui-border hover:border-odoo-purple/50 hover:text-odoo-purple transition-all text-text-muted shrink-0 mt-2"
          >
            <span className="text-sm font-medium">+ Añadir bloque</span>
          </button>
        </div>
      </div>

      {/* Columna Derecha: Panel */}
      <div className="md:w-1/2 min-w-0 flex flex-col overflow-hidden bg-ui-body/30 dark:bg-ui-dark-bg">

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
                <Button variant="outline" size="xs" className="text-danger" onClick={() => setDeleteConfirm(true)}>Eliminar</Button>
              </div>
            </div>
            <div className="flex-1 overflow-y-auto p-6 space-y-6">
              {deleteConfirm && (
                <div className="p-3 bg-danger-light/20 border border-danger/30 rounded-md flex items-center justify-between gap-4 animate-in slide-in-from-top-1">
                  <span className="text-xs text-danger-dark font-medium">¿Confirmas la eliminación permanente?</span>
                  <div className="flex gap-2">
                    <button className="text-xs font-bold underline" onClick={() => void handleDelete()}>Sí</button>
                    <button className="text-xs underline" onClick={() => setDeleteConfirm(false)}>No</button>
                  </div>
                </div>
              )}
              <dl className="grid grid-cols-1 gap-6">
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Nombre</dt>
                  <dd className="mt-1 text-sm font-medium">{selectedBlock.title}</dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Descripción</dt>
                  <dd className="mt-1 text-sm text-text-secondary">
                    {Array.isArray(selectedBlock.default_content)
                      ? (selectedBlock.default_content as string[]).join(' ')
                      : '—'}
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
                    {blocks.findIndex((b) => b.id === selectedBlock.id) + 1} de {blocks.length}
                  </dd>
                </div>
              </dl>
              <p className="text-xs text-text-muted italic pt-4 border-t border-ui-border dark:border-ui-dark-border">
                Pulsa «Editar» para modificar o «Eliminar» para borrar permanentemente.
              </p>
            </div>
          </div>
        )}

        {/* edit / create */}
        {(panelMode === 'create' || panelMode === 'edit') && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border shrink-0">
              <h3 className="text-sm font-bold text-text-primary">
                {panelMode === 'create' ? 'Nuevo bloque' : `Editando — ${selectedBlock?.title}`}
              </h3>
            </div>
            <div className="flex-1 overflow-y-auto p-6">
              {renderBlockForm(
                panelMode === 'create' ? 'Añadir bloque' : 'Guardar bloque',
                panelMode === 'create' ? handleAddBlock : handleSaveEdit,
                () => (panelMode === 'create' ? setPanelMode('empty') : setPanelMode('summary')),
              )}
            </div>
          </div>
        )}

        {/* multi */}
        {panelMode === 'multi' && (
          <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
            {/* Header + navigation */}
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border shrink-0">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-odoo-purple">Editando selección</h3>
                <span className="text-[10px] text-text-muted">
                  {orderedSelection.length} bloque{orderedSelection.length !== 1 ? 's' : ''}
                </span>
              </div>
              <div className="flex items-center gap-3 mt-3">
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
            </div>

            {/* Form body */}
            <div className="flex-1 overflow-y-auto p-6 space-y-4">
              <p className="text-[10px] font-bold uppercase text-text-muted truncate">
                {currentMultiBlock?.title || 'Bloque sin nombre'}
              </p>

              {formUiState === 'locked' && (
                <div className="px-3 py-2 bg-warning-light/20 border border-warning/30 rounded text-[11px] text-warning-dark font-bold">
                  Bloque bloqueado: su obligatoriedad es siempre Obligatorio.
                </div>
              )}
              <div>
                <FieldLabel required>Nombre</FieldLabel>
                <TextInput
                  type="text"
                  fieldSize="comfortable"
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  placeholder="Ej: Introducción"
                />
              </div>
              <div>
                <FieldLabel>Descripción</FieldLabel>
                <TextArea
                  fieldSize="comfortable"
                  rows={2}
                  value={formDesc}
                  onChange={(e) => setFormDesc(e.target.value)}
                  placeholder="Descripción del bloque…"
                  style={{ minHeight: '52px' }}
                />
              </div>
              <div>
                <FieldLabel required>Estado del bloque</FieldLabel>
                <div className="mt-1">
                  <BlockUiStateToggle value={formUiState} onChange={setFormUiState} disabled={busy} />
                </div>
              </div>
              {actionError && (
                <p className="text-xs text-danger-dark animate-in fade-in">{actionError}</p>
              )}
            </div>

            {/* Multi footer */}
            <div className="shrink-0 px-6 py-4 border-t border-ui-border dark:border-ui-dark-border flex gap-3">
              <Button
                type="button"
                variant="primary"
                size="md"
                className="flex-1"
                loading={busy}
                disabled={!formName.trim()}
                onClick={() => void handleMultiSaveAndNext()}
              >
                {multiIndex === orderedSelection.length - 1 ? 'Guardar y terminar ✓' : 'Guardar y siguiente →'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                size="md"
                disabled={busy}
                onClick={handleMultiCancelAll}
              >
                Cancelar todo
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
