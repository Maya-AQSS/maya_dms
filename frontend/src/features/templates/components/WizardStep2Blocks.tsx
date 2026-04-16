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
            onClick={() => {
              if (s === 'locked') {
                onChange('locked');
              } else {
                onChange(s);
              }
            }}
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
        <p className="text-xs text-warning-dark dark:text-warning-light animate-in fade-in slide-in-from-top-1">
          Bloque bloqueado: su obligatoriedad es siempre Obligatorio.
        </p>
      )}
    </div>
  );
}

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
        {cfg.label}
      </span>
    </button>
  );
}

// ── Main component ───────────────────────────────────────────────────────────

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
  const [allSelected, setAllSelected] = useState(false);

  // Form state
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState(false);

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
    setActionError(null);
    setDeleteConfirm(false);
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
    if (!formName.trim() || !selectedBlockId) return;
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
    try {
      await deleteBlock(selectedBlockId);
      setSelectedBlockId(null);
      setDeleteConfirm(false);
      setPanelMode('empty');
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al eliminar el bloque');
    } finally {
      setBusy(false);
    }
  };

  const renderBlockForm = (
    submitLabel: string,
    onSubmit: () => Promise<void>,
    onCancel: () => void,
  ) => (
    <div className="space-y-4">
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
      {actionError && (
        <p className="text-xs text-danger-dark animate-in fade-in">{actionError}</p>
      )}
    </div>
  );

  // ── Variant A — Empty State ──
  if (!loading && blocks.length === 0) {
    return (
      <div className="flex-1 overflow-auto p-6 flex flex-col items-center justify-center">
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

  // ── Variant B — Two Columns ──
  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Columna Izquierda: Lista */}
      <div className="md:w-1/2 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden bg-white dark:bg-ui-dark-card">
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 flex items-center justify-between shrink-0">
          <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
            BLOQUES ({blocks.length})
          </span>
          <Button
            type="button"
            variant="ghost"
            size="xs"
            onClick={() => setAllSelected(!allSelected)}
          >
            {allSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-2">
          {blocks.map((block) => (
            <BlockListItem
              key={block.id}
              block={block}
              isSelected={selectedBlockId === block.id}
              onClick={() => openSummary(block.id)}
            />
          ))}
          
          <button
            type="button"
            onClick={openCreate}
            className="w-full text-left rounded-lg px-3 py-3 flex items-center gap-2 border-2 border-dashed border-ui-border hover:border-odoo-purple/50 hover:text-odoo-purple transition-all text-text-muted"
          >
            <span className="text-sm font-medium">+ Añadir bloque</span>
          </button>
        </div>
      </div>

      {/* Columna Derecha: Panel */}
      <div className="md:w-1/2 flex flex-col overflow-hidden bg-ui-body/30 dark:bg-ui-dark-bg">
        {panelMode === 'empty' && (
          <div className="flex-1 flex flex-col items-center justify-center p-6 text-center animate-in fade-in">
             <svg className="w-10 h-10 text-text-muted mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5" />
             </svg>
            <p className="text-sm font-bold text-text-primary">Selecciona un bloque</p>
            <p className="text-xs text-text-muted mt-1">Haz clic en un bloque de la lista para ver su resumen o editarlo.</p>
          </div>
        )}

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
                    {Array.isArray(selectedBlock.default_content) ? (selectedBlock.default_content as string[]).join(' ') : '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Estado</dt>
                  <dd className="mt-2">
                    {(() => {
                      const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(selectedBlock)];
                      return <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}>{cfg.label}</span>
                    })()}
                  </dd>
                </div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-text-muted">Orden</dt>
                  <dd className="mt-1 text-sm">{blocks.findIndex(b => b.id === selectedBlock.id) + 1} de {blocks.length}</dd>
                </div>
              </dl>
              <p className="text-xs text-text-muted italic pt-4 border-t border-ui-border dark:border-ui-dark-border">
                Pulsa «Editar» para modificar este bloque o «Eliminar» para borrarlo permanentemente.
              </p>
            </div>
          </div>
        )}

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
                () => panelMode === 'create' ? setPanelMode('empty') : setPanelMode('summary')
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
