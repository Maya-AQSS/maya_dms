import { useCallback, useRef, useState } from 'react';
import { Button, FieldLabel, Select, TextArea } from '../../../ui';
import type { BlockState, TemplateBlock } from '../../../types/blocks';
import { BLOCK_STATE_LABELS } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import type { Template } from '../../../types/templates';

const BLOCK_TYPES = ['paragraph', 'heading', 'table', 'list', 'image', 'custom'] as const;

const STATE_BADGE: Record<BlockState, { cls: string; label: string }> = {
  editable: {
    cls: 'bg-odoo-teal/15 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
    label: 'Edit.',
  },
  modifiable: {
    cls: 'bg-odoo-purple/10 text-odoo-purple dark:bg-odoo-dark-purple/20 dark:text-odoo-dark-purple',
    label: 'Mod.',
  },
  locked: {
    cls: 'bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted border border-ui-border dark:border-ui-dark-border',
    label: 'Bloq.',
  },
};

type PanelMode = 'create' | 'edit-single' | 'edit-multi';

type BlockVisualState =
  | 'default'
  | 'dimmed'
  | 'selected'
  | 'queue-pending'
  | 'queue-current'
  | 'queue-saved';

type Props = {
  template: Template;
  onClose?: () => void;
  inline?: boolean;
};

export function TemplateBlockEditor({ template, onClose, inline = false }: Props) {
  const { blocks, loading, error, createBlock, updateBlock, deleteBlock, reorderBlocks } =
    useTemplateBlocks(template.id);

  const [panelMode, setPanelMode] = useState<PanelMode>('create');
  // Ordered list of block IDs being edited; length 1 → edit-single, 2+ → edit-multi
  const [editQueue, setEditQueue] = useState<string[]>([]);
  const [editQueueIndex, setEditQueueIndex] = useState(0);
  const [savedInSession, setSavedInSession] = useState<Set<string>>(new Set());

  // Shared form state
  const [formType, setFormType] = useState<string>(BLOCK_TYPES[0]);
  const [formTitle, setFormTitle] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formBlockState, setFormBlockState] = useState<BlockState>('editable');
  const [formMandatory, setFormMandatory] = useState(false);

  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState(false);

  const clickTimeout = useRef<NodeJS.Timeout | null>(null);

  // ── Derived ──────────────────────────────────────────────────────────────

  const hasBlocks = !loading && blocks.length > 0;
  const currentEditBlockId = editQueue[editQueueIndex] ?? null;
  const currentEditBlock = currentEditBlockId
    ? (blocks.find((b) => b.id === currentEditBlockId) ?? null)
    : null;
  const isLastInQueue = editQueueIndex === editQueue.length - 1;
  const allSelected = editQueue.length > 0 && editQueue.length === blocks.length;

  // ── Helpers ──────────────────────────────────────────────────────────────

  const populateFormFromBlock = useCallback((block: TemplateBlock) => {
    setFormType(block.type);
    setFormTitle(block.title ?? '');
    setFormContent(Array.isArray(block.default_content) ? block.default_content.join('\n') : '');
    setFormBlockState(block.block_state);
    setFormMandatory(block.mandatory);
  }, []);

  const resetForm = () => {
    setFormType(BLOCK_TYPES[0]);
    setFormTitle('');
    setFormContent('');
    setFormBlockState('editable');
    setFormMandatory(false);
  };

  const goToCreateMode = () => {
    setPanelMode('create');
    setEditQueue([]);
    setEditQueueIndex(0);
    setSavedInSession(new Set());
    setDeleteConfirm(false);
    resetForm();
  };

  const getBlockVisualState = (blockId: string): BlockVisualState => {
    if (editQueue.length === 0) return 'default';
    const inQueue = editQueue.includes(blockId);
    if (!inQueue) return 'dimmed';
    if (panelMode === 'edit-single') return 'selected';
    if (savedInSession.has(blockId)) return 'queue-saved';
    if (editQueue[editQueueIndex] === blockId) return 'queue-current';
    return 'queue-pending';
  };

  // ── Interaction handlers ──────────────────────────────────────────────────

  const handleBlockClick = (blockId: string) => {
    // If a click is already pending, don't start a second one for the same sequence
    // But allow the timer to differentiate from double click.
    if (clickTimeout.current) return;

    clickTimeout.current = setTimeout(() => {
      clickTimeout.current = null;
      setDeleteConfirm(false);
      setActionError(null);
      const block = blocks.find((b) => b.id === blockId);
      if (block) {
        setPanelMode('edit-single');
        setEditQueue([blockId]);
        setEditQueueIndex(0);
        setSavedInSession(new Set());
        populateFormFromBlock(block);
      }
    }, 250);
  };

  const handleBlockDoubleClick = (blockId: string, _e: React.MouseEvent) => {
    // Cancel the pending single click action
    if (clickTimeout.current) {
      clearTimeout(clickTimeout.current);
      clickTimeout.current = null;
    }

    setDeleteConfirm(false);
    setActionError(null);
    
    // Toggle from queue
    const inQueue = editQueue.includes(blockId);
    let newQueue = [...editQueue];

    if (inQueue) {
      // If already in queue, remove it (unless it's the only one?)
      if (newQueue.length > 1) {
        newQueue = newQueue.filter(id => id !== blockId);
      }
    } else {
      // Add to queue
      newQueue.push(blockId);
    }

    if (newQueue.length > 1) {
      // Sort queue by render position, not click order
      const sortedQueue = [...newQueue].sort(
        (a, b) => blocks.findIndex((bl) => bl.id === a) - blocks.findIndex((bl) => bl.id === b),
      );
      setPanelMode('edit-multi');
      setEditQueue(sortedQueue);
      // Entering multi for the first time → open at block 1 (index 0)
      // Already in multi mode → keep current block's position in the new sorted queue
      let newIndex = 0;
      if (panelMode === 'edit-multi') {
        const idx = sortedQueue.indexOf(editQueue[editQueueIndex]);
        if (idx !== -1) newIndex = idx;
      }
      setEditQueueIndex(newIndex);
      const block = blocks.find((b) => b.id === sortedQueue[newIndex]);
      if (block) populateFormFromBlock(block);
    } else if (newQueue.length === 1) {
      // If only one left, it's single edit mode
      // Bypass the timeout here since we are inside handleBlockDoubleClick already
      const block = blocks.find((b) => b.id === newQueue[0]);
      if (block) {
        setPanelMode('edit-single');
        setEditQueue([newQueue[0]]);
        setEditQueueIndex(0);
        setSavedInSession(new Set());
        populateFormFromBlock(block);
      }
    }
  };

  // ── Drag & Drop ──────────────────────────────────────────────────────────

  const handleDragStart = (e: React.DragEvent, blockId: string) => {
    e.dataTransfer.setData('blockId', blockId);
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  };

  const handleDrop = async (e: React.DragEvent, targetId: string) => {
    e.preventDefault();
    const draggedId = e.dataTransfer.getData('blockId');
    if (!draggedId || draggedId === targetId) return;

    const sourceIndex = blocks.findIndex(b => b.id === draggedId);
    const targetIndex = blocks.findIndex(b => b.id === targetId);
    if (sourceIndex === -1 || targetIndex === -1) return;

    try {
      // Persist the move (backend uses sort_order)
      await reorderBlocks(draggedId, targetIndex);
    } catch (err) {
      console.error('Failed to persist reorder:', err);
    }
  };

  const handleSelectAll = () => {
    if (allSelected) {
      goToCreateMode();
      return;
    }
    const ids = blocks.map((b) => b.id);
    setEditQueue(ids);
    setEditQueueIndex(0);
    setSavedInSession(new Set());
    setDeleteConfirm(false);
    const first = blocks[0];
    if (!first) return;
    populateFormFromBlock(first);
    setPanelMode(ids.length === 1 ? 'edit-single' : 'edit-multi');
  };

  const handleNavPrev = () => {
    if (editQueueIndex === 0) return;
    const prevIndex = editQueueIndex - 1;
    const prevId = editQueue[prevIndex];
    setSavedInSession((prev) => {
      const next = new Set(prev);
      next.delete(prevId);
      return next;
    });
    setEditQueueIndex(prevIndex);
    setDeleteConfirm(false);
    const prevBlock = blocks.find((b) => b.id === prevId);
    if (prevBlock) populateFormFromBlock(prevBlock);
  };

  const handleNavNext = () => {
    if (isLastInQueue) return;
    const nextIndex = editQueueIndex + 1;
    setEditQueueIndex(nextIndex);
    setDeleteConfirm(false);
    const nextBlock = blocks.find((b) => b.id === editQueue[nextIndex]);
    if (nextBlock) populateFormFromBlock(nextBlock);
  };

  // ── Form block-state change (enforces Bloqueado → Obligatorio) ───────────

  const handleFormBlockStateChange = (state: BlockState) => {
    setFormBlockState(state);
    if (state === 'locked') setFormMandatory(true);
  };

  // ── API actions ───────────────────────────────────────────────────────────

  const handleAddBlock = async () => {
    setBusy(true);
    setActionError(null);
    try {
      const block = await createBlock({
        type: formType,
        title: formTitle.trim() || null,
        default_content: formContent.trim() ? formContent.split('\n').map(l => l.trim()).filter(Boolean) : null,
        block_state: formBlockState,
        mandatory: formMandatory,
      });
      resetForm();
      setEditQueue([block.id]);
      setEditQueueIndex(0);
      setSavedInSession(new Set());
      setPanelMode('edit-single');
      populateFormFromBlock(block);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al crear bloque');
    } finally {
      setBusy(false);
    }
  };

  const handleSaveSingle = async () => {
    if (!currentEditBlockId) return;
    setBusy(true);
    setActionError(null);
    try {
      await updateBlock(currentEditBlockId, {
        type: formType,
        title: formTitle.trim() || null,
        default_content: formContent.trim() ? formContent.split('\n').map(l => l.trim()).filter(Boolean) : null,
        block_state: formBlockState,
        mandatory: formMandatory,
      });
      goToCreateMode();
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al guardar');
    } finally {
      setBusy(false);
    }
  };

  const handleSaveAndNext = async () => {
    if (!currentEditBlockId) return;
    setBusy(true);
    setActionError(null);
    try {
      await updateBlock(currentEditBlockId, {
        type: formType,
        title: formTitle.trim() || null,
        default_content: formContent.trim() ? formContent.split('\n').map(l => l.trim()).filter(Boolean) : null,
        block_state: formBlockState,
        mandatory: formMandatory,
      });
      setSavedInSession((prev) => new Set([...prev, currentEditBlockId]));
      if (isLastInQueue) {
        goToCreateMode();
      } else {
        const nextIndex = editQueueIndex + 1;
        setEditQueueIndex(nextIndex);
        setDeleteConfirm(false);
        const nextBlock = blocks.find((b) => b.id === editQueue[nextIndex]);
        if (nextBlock) populateFormFromBlock(nextBlock);
      }
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al guardar');
    } finally {
      setBusy(false);
    }
  };

  const handleDeleteBlock = async (blockId: string) => {
    setBusy(true);
    setActionError(null);
    try {
      await deleteBlock(blockId);
      setDeleteConfirm(false);
      const newQueue = editQueue.filter((id) => id !== blockId);
      setSavedInSession((prev) => {
        const next = new Set(prev);
        next.delete(blockId);
        return next;
      });
      if (newQueue.length === 0) {
        goToCreateMode();
        return;
      }
      const newIndex = Math.min(editQueueIndex, newQueue.length - 1);
      setEditQueue(newQueue);
      setEditQueueIndex(newIndex);
      setPanelMode(newQueue.length === 1 ? 'edit-single' : 'edit-multi');
      const nextBlock = blocks.find((b) => b.id === newQueue[newIndex]);
      if (nextBlock) populateFormFromBlock(nextBlock);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al eliminar');
    } finally {
      setBusy(false);
    }
  };

  // ── Shared form fields ────────────────────────────────────────────────────

  const renderFormFields = () => (
    <div className="space-y-4">
      <div>
        <FieldLabel>Tipo de contenido</FieldLabel>
        <Select fieldSize="sm" value={formType} onChange={(e) => setFormType(e.target.value)}>
          {BLOCK_TYPES.map((t) => (
            <option key={t} value={t}>
              {t.charAt(0).toUpperCase() + t.slice(1)}
            </option>
          ))}
        </Select>
      </div>

      <div>
        <FieldLabel>Nombre</FieldLabel>
        <input
          type="text"
          className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary px-2 py-1.5 text-sm"
          placeholder="Ej: Introducción"
          value={formTitle}
          onChange={(e) => setFormTitle(e.target.value)}
        />
      </div>

      <div>
        <FieldLabel>Contenido inicial</FieldLabel>
        <TextArea
          fieldSize="sm"
          rows={3}
          placeholder="Texto del bloque..."
          value={formContent}
          onChange={(e) => setFormContent(e.target.value)}
        />
      </div>

      <div>
        <FieldLabel>Estado del bloque</FieldLabel>
        <div className="flex gap-2 mt-1">
          {(['editable', 'modifiable', 'locked'] as const).map((s) => (
            <button
              key={s}
              type="button"
              disabled={busy}
              onClick={() => handleFormBlockStateChange(s)}
              className={[
                'px-3 py-1.5 rounded text-xs font-medium transition-all',
                'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                formBlockState === s
                  ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                  : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
                'disabled:opacity-50 disabled:pointer-events-none',
              ].join(' ')}
            >
              {BLOCK_STATE_LABELS[s]}
            </button>
          ))}
        </div>
        {formBlockState === 'locked' && (
          <p className="mt-1.5 text-xs text-warning-dark dark:text-warning-light">
            Bloque bloqueado: la obligatoriedad es siempre Obligatorio.
          </p>
        )}
      </div>

      <div>
        <FieldLabel>Obligatoriedad</FieldLabel>
        <div className="flex gap-2 mt-1">
          {(
            [
              { value: true, label: 'Obligatorio' },
              { value: false, label: 'Opcional' },
            ] as const
          ).map(({ value, label }) => {
            const isDisabledOptional = value === false && formBlockState === 'locked';
            return (
              <button
                key={label}
                type="button"
                disabled={busy || isDisabledOptional}
                onClick={() => setFormMandatory(value)}
                className={[
                  'px-3 py-1.5 rounded text-xs font-medium transition-all',
                  'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                  formMandatory === value
                    ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                    : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
                  isDisabledOptional ? 'opacity-30 pointer-events-none' : '',
                  'disabled:opacity-50 disabled:pointer-events-none',
                ].join(' ')}
              >
                {label}
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );

  // ── Inline delete confirmation ────────────────────────────────────────────

  const renderDeleteConfirm = (blockId: string) => (
    <span className="flex items-center gap-1.5 text-xs text-warning-dark dark:text-warning-light">
      ¿Seguro?{' '}
      <button
        type="button"
        className="underline font-medium focus:outline-none"
        onClick={() => void handleDeleteBlock(blockId)}
      >
        Confirmar
      </button>{' '}
      /{' '}
      <button
        type="button"
        className="underline focus:outline-none"
        onClick={() => setDeleteConfirm(false)}
      >
        No
      </button>
    </span>
  );

  // ── Render ────────────────────────────────────────────────────────────────

  const bodyPanel = (
    <div className="flex flex-1 overflow-hidden">
      {/* Left column — only rendered when blocks exist (Task 1) */}
      {hasBlocks && (
        <div className="w-72 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden">
          {/* Column header (Task 2) */}
          <div className="px-3.5 py-2.5 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30">
            <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
              Bloques ({blocks.length})
            </span>
          </div>

          {/* Scrollable block list */}
          <div
            className="flex-1 overflow-y-auto p-2.5 space-y-1.5"
            onDragOver={handleDragOver}
          >
            {error && (
              <p className="text-xs text-warning-dark dark:text-warning-light px-2 py-2">{error}</p>
            )}
            {blocks.map((block) => (
              <BlockOutlineItem
                key={block.id}
                block={block}
                visualState={getBlockVisualState(block.id)}
                onClick={handleBlockClick}
                onDoubleClick={handleBlockDoubleClick}
                onDragStart={handleDragStart}
                onDrop={handleDrop}
              />
            ))}
          </div>

          {/* Sticky footer with selection toggle and hints (Task 2) */}
          <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 p-3 space-y-2.5">
            <p className="text-[10px] text-text-muted dark:text-text-dark-muted leading-snug px-1">
              {editQueue.length === 0
                ? 'Doble clic en un bloque para editarlo'
                : 'Mantén Ctrl / ⌘ + doble clic para añadir a la selección'}
            </p>
            <button
              type="button"
              onClick={handleSelectAll}
              className={[
                'w-full text-left px-2.5 py-2 rounded text-xs font-medium transition-all',
                'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                allSelected
                  ? 'border-odoo-purple/40 text-odoo-purple dark:border-odoo-dark-purple/40 dark:text-odoo-dark-purple bg-odoo-purple/5 dark:bg-odoo-dark-purple/10'
                  : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/30 dark:hover:border-odoo-dark-purple/30 bg-white dark:bg-ui-dark-bg',
              ].join(' ')}
            >
              {allSelected ? 'Deseleccionar todos' : 'Seleccionar todos los bloques'}
            </button>
          </div>
        </div>
      )}

      {/* Right panel (Task 3) */}
      <div className={[
        'flex-1 overflow-y-auto p-6 transition-all bg-white dark:bg-ui-dark-bg',
        !hasBlocks ? 'max-w-4xl mx-auto' : ''
      ].join(' ')}>
        {actionError && (
          <div className="mb-4 rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light flex justify-between gap-4">
            <span>{actionError}</span>
            <Button type="button" variant="ghost" size="xs" onClick={() => setActionError(null)}>
              ✕
            </Button>
          </div>
        )}

        {/* ── Mode A: Nuevo bloque ─────────────────────────────────────── */}
        {panelMode === 'create' && (
          <div className="max-w-md space-y-6">
            {/* Task 4: empty-state message */}
            {!loading && blocks.length === 0 && !error && (
              <div className="mb-6 rounded-md bg-ui-body dark:bg-ui-dark-bg/50 border border-dashed border-ui-border dark:border-ui-dark-border px-4 py-3">
                <p className="text-xs text-text-muted dark:text-text-dark-muted leading-relaxed">
                  Esta plantilla aún no tiene bloques. Añade el primero usando el formulario.
                </p>
              </div>
            )}

            <h3 className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary flex items-center gap-2">
              Nuevo bloque
            </h3>

            {renderFormFields()}

            <Button
              type="button"
              variant="primary"
              size="md"
              loading={busy}
              className="w-full mt-2 shadow-sm"
              onClick={() => void handleAddBlock()}
            >
              Añadir bloque
            </Button>
          </div>
        )}

        {/* ── Mode B: Editar bloque (single) ───────────────────────────── */}
        {panelMode === 'edit-single' && currentEditBlock && (
          <div className="max-w-md space-y-5">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
                Editar bloque
              </p>
              <p className="text-sm font-medium text-text-primary dark:text-text-dark-primary mt-0.5">
                «{currentEditBlock.title ?? currentEditBlock.type}»
              </p>
            </div>

            {renderFormFields()}

            <div className="flex gap-2">
              <Button
                type="button"
                variant="primary"
                size="sm"
                loading={busy}
                onClick={() => void handleSaveSingle()}
              >
                Guardar cambios
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={busy}
                onClick={goToCreateMode}
              >
                Cancelar
              </Button>
            </div>

            <div className="pt-2 border-t border-ui-border dark:border-ui-dark-border space-y-2">
              {deleteConfirm ? (
                renderDeleteConfirm(currentEditBlock.id)
              ) : (
                <Button
                  type="button"
                  variant="outlineWarning"
                  size="sm"
                  disabled={busy}
                  onClick={() => setDeleteConfirm(true)}
                >
                  Eliminar bloque
                </Button>
              )}
            </div>
          </div>
        )}

        {/* ── Mode C: Editando selección (multi) ───────────────────────── */}
        {panelMode === 'edit-multi' && currentEditBlock && (
          <div className="max-w-md space-y-5">
            <div className="space-y-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
                Editando selección
              </p>

              {/* Navigation bar */}
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  disabled={editQueueIndex === 0 || busy}
                  onClick={handleNavPrev}
                  className={[
                    'flex items-center justify-center size-6 rounded border text-xs font-medium transition-all',
                    'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary',
                    'hover:border-odoo-purple/40 dark:hover:border-odoo-dark-purple/40',
                    'disabled:opacity-30 disabled:pointer-events-none',
                    'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                  ].join(' ')}
                >
                  ←
                </button>
                <span className="flex-1 text-center text-xs text-text-muted dark:text-text-dark-muted">
                  {editQueueIndex + 1} / {editQueue.length}
                </span>
                <button
                  type="button"
                  disabled={isLastInQueue || busy}
                  onClick={handleNavNext}
                  className={[
                    'flex items-center justify-center size-6 rounded border text-xs font-medium transition-all',
                    'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary',
                    'hover:border-odoo-purple/40 dark:hover:border-odoo-dark-purple/40',
                    'disabled:opacity-30 disabled:pointer-events-none',
                    'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                  ].join(' ')}
                >
                  →
                </button>
              </div>

              {/* Progress bar */}
              <div className="h-1 w-full rounded-full bg-ui-border dark:bg-ui-dark-border overflow-hidden">
                <div
                  className="h-full bg-odoo-purple dark:bg-odoo-dark-purple transition-all"
                  style={{ width: `${((editQueueIndex + 1) / editQueue.length) * 100}%` }}
                />
              </div>

              <p className="text-sm font-medium text-text-primary dark:text-text-dark-primary">
                «{currentEditBlock.title ?? currentEditBlock.type}»
              </p>
            </div>

            {renderFormFields()}

            <div className="flex items-center gap-3 pt-2">
              <Button
                type="button"
                variant={isLastInQueue ? 'primary' : 'teal'}
                size="sm"
                loading={busy}
                className="flex-1"
                onClick={() => void handleSaveAndNext()}
              >
                {isLastInQueue ? 'Guardar y terminar ✓' : 'Guardar y siguiente →'}
              </Button>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={busy}
                className="px-6"
                onClick={goToCreateMode}
              >
                Cancelar todo
              </Button>
            </div>

            <div className="pt-2 border-t border-ui-border dark:border-ui-dark-border space-y-2">
              {deleteConfirm ? (
                renderDeleteConfirm(currentEditBlock.id)
              ) : (
                <Button
                  type="button"
                  variant="outlineWarning"
                  size="sm"
                  disabled={busy}
                  onClick={() => setDeleteConfirm(true)}
                >
                  Eliminar este bloque
                </Button>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );

  if (inline) return bodyPanel;

  return (
    <>
      {/* Backdrop — only covers the content area, leaving the sidebar clear */}
      <div
        className="fixed inset-0 z-40 bg-black/20 dark:bg-black/40 backdrop-blur-[1px] transition-opacity duration-300"
        style={{ left: 'var(--sidebar-width, 0px)' }}
        onClick={onClose}
        aria-hidden="true"
      />

      <div
        className={[
          'fixed z-40 flex flex-col bg-ui-body dark:bg-ui-dark-bg transition-all duration-200 ease-in-out',
          'shadow-2xl shadow-black/20 border border-ui-border dark:border-ui-dark-border',
          'md:rounded-xl overflow-hidden'
        ].join(' ')}
        style={{
          top: 'calc(3.5rem + 1.5rem)',
          bottom: '1.5rem',
          right: '1.5rem',
          left: 'calc(var(--sidebar-width, 0px) + 1.5rem)',
        }}
      >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">
             {template.name}
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-0.5">
            {blocks.length} {blocks.length === 1 ? 'bloque' : 'bloques'}
            {editQueue.length > 1 && (
              <span className="ml-2 text-odoo-purple dark:text-odoo-dark-purple font-medium">
                · Editando selección ({editQueue.length})
              </span>
            )}
          </p>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={onClose}>
          Cerrar
        </Button>
      </div>

      {/* Body */}
      {bodyPanel}
    </div>
    </>
  );
}

// ── Block outline item ────────────────────────────────────────────────────────

type OutlineItemProps = {
  block: TemplateBlock;
  visualState: BlockVisualState;
  onClick: (blockId: string) => void;
  onDoubleClick: (blockId: string, e: React.MouseEvent) => void;
  onDragStart: (e: React.DragEvent, blockId: string) => void;
  onDrop: (e: React.DragEvent, blockId: string) => void;
};

function BlockOutlineItem({ block, visualState, onClick, onDoubleClick, onDragStart, onDrop }: OutlineItemProps) {
  const badge = STATE_BADGE[block.block_state];

  const containerCls = (() => {
    const base =
      'w-full text-left rounded px-2.5 py-2 flex items-center gap-2 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 group';
    switch (visualState) {
      case 'selected':
      case 'queue-current':
        return `${base} bg-odoo-purple/10 dark:bg-odoo-dark-purple/15 border border-odoo-purple/30 dark:border-odoo-dark-purple/40 shadow-sm`;
      case 'queue-pending':
        return `${base} bg-odoo-purple/5 dark:bg-odoo-dark-purple/10 border border-odoo-purple/15 dark:border-odoo-dark-purple/20`;
      case 'queue-saved':
        return `${base} bg-odoo-teal/5 dark:bg-odoo-dark-teal/10 border border-odoo-teal/20 dark:border-odoo-dark-teal/30`;
      default:
        return `${base} border border-ui-border/50 dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border dark:hover:border-ui-dark-border`;
    }
  })();

  const nameCls = [
    'flex-1 min-w-0 text-xs truncate transition-opacity',
    visualState === 'queue-pending' || visualState === 'queue-saved' || visualState === 'dimmed'
      ? 'text-text-muted dark:text-text-dark-muted opacity-50'
      : 'text-text-primary dark:text-text-dark-primary font-medium',
  ].join(' ');

  return (
    <button
      type="button"
      className={containerCls}
      onClick={() => onClick(block.id)}
      onDoubleClick={(e) => onDoubleClick(block.id, e)}
      draggable
      onDragStart={(e) => onDragStart(e, block.id)}
      onDrop={(e) => onDrop(e, block.id)}
    >
      {/* Dot: shown only for the block currently being edited in multi-edit */}
      {visualState === 'queue-current' && (
        <span
          className="shrink-0 size-2 rounded-full bg-odoo-purple dark:bg-odoo-dark-purple animate-pulse"
          aria-hidden
        />
      )}

      <span className={nameCls}>{block.title || block.type.charAt(0).toUpperCase() + block.type.slice(1)}</span>

      <span className={[
        'shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-tight transition-all',
        badge.cls,
        (visualState === 'queue-pending' || visualState === 'queue-saved' || visualState === 'dimmed') ? 'opacity-50 grayscale' : ''
      ].join(' ')}>
        {badge.label}
      </span>

      {visualState === 'queue-saved' && (
        <span
          className="shrink-0 flex items-center justify-center size-4 rounded-full bg-odoo-teal text-white text-[10px]"
          aria-label="Guardado"
        >
          ✓
        </span>
      )}
    </button>
  );
}
