import { useEffect, useState } from 'react';
import { Button, FieldLabel, TextArea, TextInput } from '../../../ui';
import type { TemplateBlock } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import type { Template } from '../../../types/templates';
import {
  type BlockUiState,
  BLOCK_UI_STATE_CONFIG,
  blockToUiState,
} from '../blockUiState';

// ── Sub-components ────────────────────────────────────────────────────────────

type PanelMode = 'empty' | 'summary' | 'edit' | 'create';

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
    <div className="space-y-2">
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
                : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
              'disabled:opacity-50 disabled:pointer-events-none',
            ].join(' ')}
          >
            {BLOCK_UI_STATE_CONFIG[s].label}
          </button>
        ))}
      </div>
      {value === 'locked' && (
        <p className="text-xs text-warning-dark dark:text-warning-light">
          Bloque bloqueado: su obligatoriedad es siempre Obligatorio.
        </p>
      )}
    </div>
  );
}

// ── Block list item ───────────────────────────────────────────────────────────

function BlockListItem({
  block,
  isSelected,
  onClick,
}: {
  block: TemplateBlock;
  isSelected: boolean;
  onClick: () => void;
}) {
  const uiState = blockToUiState(block);
  const cfg = BLOCK_UI_STATE_CONFIG[uiState];
  return (
    <button
      type="button"
      onClick={onClick}
      className={[
        'w-full text-left rounded px-3 py-2 flex items-center gap-2 transition-all min-h-11',
        'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
        isSelected
          ? 'bg-odoo-purple/10 dark:bg-odoo-dark-purple/15 border-odoo-purple/30 dark:border-odoo-dark-purple/40 shadow-sm'
          : 'bg-white dark:bg-ui-dark-card border-ui-border/50 dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border dark:hover:border-ui-dark-border',
      ].join(' ')}
    >
      <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
        {block.title || 'Bloque sin nombre'}
      </span>
      <span
        className={[
          'shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-tight',
          cfg.badgeCls,
        ].join(' ')}
      >
        {cfg.label.slice(0, 4)}.
      </span>
    </button>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  onBlocksCountChange: (count: number) => void;
};

export function WizardStep2Blocks({ template, onBlocksCountChange }: Props) {
  const { blocks, loading, error, createBlock, updateBlock, deleteBlock } = useTemplateBlocks(
    template.id,
  );

  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);

  // Form state (shared create / edit)
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [formNameError, setFormNameError] = useState('');
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState(false);

  // Notify parent when block count changes
  useEffect(() => {
    if (!loading) onBlocksCountChange(blocks.length);
  }, [blocks.length, loading, onBlocksCountChange]);

  const selectedBlock = selectedBlockId
    ? (blocks.find((b) => b.id === selectedBlockId) ?? null)
    : null;

  const resetForm = () => {
    setFormName('');
    setFormDesc('');
    setFormUiState('editable');
    setFormNameError('');
    setActionError(null);
    setDeleteConfirm(false);
  };

  const openCreate = () => {
    resetForm();
    setSelectedBlockId(null);
    setPanelMode('create');
  };

  const openSummary = (blockId: string) => {
    setSelectedBlockId(blockId);
    setPanelMode('summary');
    setDeleteConfirm(false);
    setActionError(null);
  };

  const openEdit = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setFormDesc(
      Array.isArray(block.default_content)
        ? (block.default_content as string[]).join('\n')
        : '',
    );
    setFormUiState(blockToUiState(block));
    setFormNameError('');
    setActionError(null);
    setDeleteConfirm(false);
    setPanelMode('edit');
  };

  const validateForm = () => {
    if (!formName.trim()) {
      setFormNameError('El nombre del bloque es obligatorio.');
      return false;
    }
    setFormNameError('');
    return true;
  };

  const handleAddBlock = async () => {
    if (!validateForm()) return;
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
    if (!validateForm() || !selectedBlockId) return;
    setBusy(true);
    setActionError(null);
    try {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
      await updateBlock(selectedBlockId, {
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
    if (!selectedBlockId) return;
    setBusy(true);
    setActionError(null);
    try {
      await deleteBlock(selectedBlockId);
      const remaining = blocks.filter((b) => b.id !== selectedBlockId);
      setSelectedBlockId(null);
      setDeleteConfirm(false);
      setPanelMode(remaining.length === 0 ? 'empty' : 'empty');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al eliminar el bloque');
    } finally {
      setBusy(false);
    }
  };

  // ── Shared block form ─────────────────────────────────────────────────────

  const renderBlockForm = (
    submitLabel: string,
    onSubmit: () => Promise<void>,
    onCancel: () => void,
  ) => (
    <div className="space-y-4">
      <div>
        <FieldLabel>Nombre</FieldLabel>
        <TextInput
          type="text"
          fieldSize="comfortable"
          value={formName}
          onChange={(e) => {
            setFormName(e.target.value);
            if (formNameError) setFormNameError('');
          }}
          placeholder="Ej: Introducción"
        />
        {formNameError && (
          <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formNameError}</p>
        )}
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
        <FieldLabel>Estado del bloque</FieldLabel>
        <div className="mt-1">
          <BlockUiStateToggle value={formUiState} onChange={setFormUiState} disabled={busy} />
        </div>
      </div>

      {actionError && (
        <p className="text-xs text-danger-dark dark:text-danger">{actionError}</p>
      )}

      <div className="flex gap-2 pt-1">
        <Button type="button" variant="primary" size="md" loading={busy} onClick={() => void onSubmit()}>
          {submitLabel}
        </Button>
        <Button type="button" variant="outline" size="md" disabled={busy} onClick={onCancel}>
          Cancelar
        </Button>
      </div>
    </div>
  );

  // ── Variant A — No blocks ─────────────────────────────────────────────────

  if (!loading && blocks.length === 0) {
    return (
      <div className="flex-1 overflow-auto p-6">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-10 h-10 rounded-full bg-ui-body dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border mb-3">
            <svg
              className="w-5 h-5 text-text-muted dark:text-text-dark-muted"
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
          <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Esta plantilla aún no tiene bloques
          </h3>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1 max-w-sm mx-auto">
            Añade el primer bloque usando el formulario. Los bloques definen la estructura del
            documento.
          </p>
        </div>

        {error && (
          <p className="text-xs text-warning-dark dark:text-warning-light text-center mb-4">
            {error}
          </p>
        )}

        <div className="max-w-md mx-auto bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-5">
          {renderBlockForm('Añadir bloque', handleAddBlock, resetForm)}
        </div>
      </div>
    );
  }

  // ── Variant B — Two-column layout ─────────────────────────────────────────

  return (
    <div className="flex flex-1 overflow-hidden flex-col md:flex-row">
      {/* Left column — Block list */}
      <div className="md:w-1/2 flex flex-col border-b md:border-b-0 md:border-r border-ui-border dark:border-ui-dark-border overflow-hidden">
        {/* Column header */}
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 flex items-center justify-between shrink-0">
          <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
            Bloques ({blocks.length})
          </span>
        </div>

        {/* Scrollable list */}
        <div className="flex-1 overflow-y-auto p-3 space-y-1.5">
          {error && (
            <p className="text-xs text-warning-dark dark:text-warning-light px-2 py-2">{error}</p>
          )}

          {blocks.map((block) => (
            <BlockListItem
              key={block.id}
              block={block}
              isSelected={
                selectedBlockId === block.id &&
                (panelMode === 'summary' || panelMode === 'edit')
              }
              onClick={() => openSummary(block.id)}
            />
          ))}

          {/* Add block — dashed element */}
          <button
            type="button"
            onClick={openCreate}
            className={[
              'w-full text-left rounded px-3 py-2 flex items-center gap-2 transition-all min-h-11',
              'border-2 border-dashed focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
              panelMode === 'create'
                ? 'border-odoo-purple/50 text-odoo-purple dark:border-odoo-dark-purple/50 dark:text-odoo-dark-purple'
                : 'border-ui-border dark:border-ui-dark-border text-text-muted dark:text-text-dark-muted hover:border-odoo-purple/40 hover:text-odoo-purple dark:hover:text-odoo-dark-purple',
            ].join(' ')}
          >
            <span className="text-xs font-medium">+ Añadir bloque</span>
          </button>
        </div>
      </div>

      {/* Right column — Contextual panel */}
      <div className="md:w-1/2 flex flex-col overflow-hidden bg-white dark:bg-ui-dark-bg">
        {/* ── Empty ── */}
        {panelMode === 'empty' && (
          <div className="flex-1 flex flex-col items-center justify-center p-6 text-center">
            <div className="inline-flex items-center justify-center w-10 h-10 rounded-full bg-ui-body dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border mb-3">
              <svg
                className="w-5 h-5 text-text-muted dark:text-text-dark-muted"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1.5}
                  d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"
                />
              </svg>
            </div>
            <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
              Selecciona un bloque
            </p>
            <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1 max-w-xs">
              Haz clic en un bloque de la lista para ver su resumen o editarlo.
            </p>
          </div>
        )}

        {/* ── Summary ── */}
        {panelMode === 'summary' && selectedBlock && (
          <div className="flex-1 flex flex-col overflow-hidden">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 flex items-center gap-3 shrink-0">
              <h3 className="flex-1 min-w-0 text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">
                {selectedBlock.title || 'Bloque sin nombre'}
              </h3>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => openEdit(selectedBlock)}
              >
                Editar
              </Button>
              <Button
                type="button"
                variant="outlineWarning"
                size="sm"
                disabled={busy}
                onClick={() => setDeleteConfirm(true)}
              >
                Eliminar
              </Button>
            </div>

            <div className="flex-1 overflow-y-auto p-5">
              {deleteConfirm && (
                <div className="mb-4 flex items-center gap-2 text-xs text-warning-dark dark:text-warning-light">
                  <span>¿Seguro?</span>
                  <button
                    type="button"
                    className="underline font-semibold focus:outline-none"
                    onClick={() => void handleDelete()}
                    disabled={busy}
                  >
                    Confirmar
                  </button>
                  <span>/</span>
                  <button
                    type="button"
                    className="underline focus:outline-none"
                    onClick={() => setDeleteConfirm(false)}
                  >
                    No
                  </button>
                </div>
              )}

              {actionError && (
                <p className="mb-3 text-xs text-danger-dark dark:text-danger">{actionError}</p>
              )}

              <dl className="space-y-4">
                <div>
                  <dt className="text-[10px] font-bold uppercase tracking-wider text-text-muted dark:text-text-dark-muted">
                    Nombre
                  </dt>
                  <dd className="mt-1 text-sm text-text-primary dark:text-text-dark-primary">
                    {selectedBlock.title || '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase tracking-wider text-text-muted dark:text-text-dark-muted">
                    Descripción
                  </dt>
                  <dd className="mt-1 text-sm text-text-secondary dark:text-text-dark-secondary">
                    {Array.isArray(selectedBlock.default_content)
                      ? (selectedBlock.default_content as string[]).join(' ') || '—'
                      : '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase tracking-wider text-text-muted dark:text-text-dark-muted">
                    Estado
                  </dt>
                  <dd className="mt-1">
                    {(() => {
                      const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(selectedBlock)];
                      return (
                        <span
                          className={[
                            'inline-block px-2 py-0.5 rounded text-xs font-bold uppercase tracking-tight',
                            cfg.badgeCls,
                          ].join(' ')}
                        >
                          {cfg.label}
                        </span>
                      );
                    })()}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase tracking-wider text-text-muted dark:text-text-dark-muted">
                    Orden
                  </dt>
                  <dd className="mt-1 text-sm text-text-secondary dark:text-text-dark-secondary">
                    {blocks.findIndex((b) => b.id === selectedBlock.id) + 1} de {blocks.length}
                  </dd>
                </div>
              </dl>

              <p className="mt-6 text-xs text-text-muted dark:text-text-dark-muted italic">
                Pulsa «Editar» para modificar este bloque o «Eliminar» para borrarlo
                permanentemente.
              </p>
            </div>
          </div>
        )}

        {/* ── Edit ── */}
        {panelMode === 'edit' && selectedBlock && (
          <div className="flex-1 flex flex-col overflow-hidden">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 shrink-0">
              <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                Editando — {selectedBlock.title || 'bloque sin nombre'}
              </h3>
            </div>
            <div className="flex-1 overflow-y-auto p-5">
              {renderBlockForm('Guardar bloque', handleSaveEdit, () => setPanelMode('summary'))}
            </div>
          </div>
        )}

        {/* ── Create ── */}
        {panelMode === 'create' && (
          <div className="flex-1 flex flex-col overflow-hidden">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 shrink-0">
              <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                Nuevo bloque
              </h3>
            </div>
            <div className="flex-1 overflow-y-auto p-5">
              {renderBlockForm('Añadir bloque', handleAddBlock, () => {
                resetForm();
                setPanelMode('empty');
              })}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
