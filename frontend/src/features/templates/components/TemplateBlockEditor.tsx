import { useState } from 'react';
import { Button, FieldLabel, Select } from '../../../ui';
import type { BlockState, TemplateBlock } from '../../../types/blocks';
import { BLOCK_STATE_LABELS } from '../../../types/blocks';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import type { Template } from '../../../types/templates';

const BLOCK_TYPES = ['heading', 'paragraph', 'table', 'list', 'image', 'custom'] as const;

const STATE_BADGE: Record<BlockState, string> = {
  editable:   'bg-odoo-teal/15 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
  modifiable: 'bg-odoo-purple/10 text-odoo-purple dark:bg-odoo-dark-purple/20 dark:text-odoo-dark-purple',
  locked:     'bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted border border-ui-border dark:border-ui-dark-border',
};

type Props = {
  template: Template;
  onClose: () => void;
};

export function TemplateBlockEditor({ template, onClose }: Props) {
  const {
    blocks,
    loading,
    error,
    selectedIds,
    createBlock,
    deleteBlock,
    applyStateToSelected,
    applyMandatoryToSelected,
    toggleSelect,
    selectOnly,
    clearSelection,
  } = useTemplateBlocks(template.id);

  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [newType, setNewType] = useState<string>(BLOCK_TYPES[1]);
  const [newTitle, setNewTitle] = useState('');

  const selectedArr = Array.from(selectedIds);
  const singleSelected = selectedArr.length === 1
    ? blocks.find((b) => b.id === selectedArr[0]) ?? null
    : null;

  // Derived panel state: when multiple selected & all same state, show it; otherwise show first
  const panelState: BlockState | '' = selectedArr.length === 0
    ? ''
    : selectedArr.every(
        (id) => blocks.find((b) => b.id === id)?.block_state === blocks.find((b) => b.id === selectedArr[0])?.block_state,
      )
      ? (blocks.find((b) => b.id === selectedArr[0])?.block_state ?? '')
      : '';

  // Derived panel mandatory: if multiple selected & mixed values, show null state.
  const panelMandatory: boolean | null = selectedArr.length === 0
    ? null
    : selectedArr.every(
        (id) => blocks.find((b) => b.id === id)?.mandatory === blocks.find((b) => b.id === selectedArr[0])?.mandatory,
      )
      ? (blocks.find((b) => b.id === selectedArr[0])?.mandatory ?? false)
      : null;

  const handleAddBlock = async () => {
    setBusy(true);
    setActionError(null);
    try {
      const block = await createBlock({
        type: newType,
        title: newTitle.trim() || null,
        // defaults: block_state='editable', mandatory=false
      });
      setNewTitle('');
      selectOnly(block.id);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al crear bloque');
    } finally {
      setBusy(false);
    }
  };

  const handleDeleteSelected = async () => {
    if (selectedArr.length === 0) return;
    setBusy(true);
    setActionError(null);
    try {
      for (const id of selectedArr) {
        await deleteBlock(id);
      }
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al eliminar');
    } finally {
      setBusy(false);
    }
  };

  const handleStateChange = async (state: BlockState) => {
    setBusy(true);
    setActionError(null);
    try {
      await applyStateToSelected(state);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al cambiar estado');
    } finally {
      setBusy(false);
    }
  };

  const handleMandatoryChange = async (mandatory: boolean) => {
    setBusy(true);
    setActionError(null);
    try {
      await applyMandatoryToSelected(mandatory);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Error al cambiar obligatoriedad');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-ui-body dark:bg-ui-dark-bg">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">
            Bloques — {template.name}
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-0.5">
            {blocks.length} {blocks.length === 1 ? 'bloque' : 'bloques'}
            {selectedArr.length > 0 && (
              <span className="ml-2 text-odoo-purple dark:text-odoo-dark-purple">
                · {selectedArr.length} seleccionado{selectedArr.length > 1 ? 's' : ''}
              </span>
            )}
          </p>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={onClose}>
          Cerrar
        </Button>
      </div>

      {/* Body: outline left + properties right */}
      <div className="flex flex-1 overflow-hidden">
        {/* Outline panel */}
        <div className="w-72 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden">
          <div className="flex-1 overflow-y-auto p-3 space-y-1">
            {loading && (
              <p className="text-xs text-text-muted dark:text-text-dark-muted px-2 py-4 text-center">
                Cargando bloques…
              </p>
            )}
            {error && (
              <p className="text-xs text-warning-dark dark:text-warning-light px-2 py-2">{error}</p>
            )}
            {!loading && blocks.length === 0 && !error && (
              <p className="text-xs text-text-muted dark:text-text-dark-muted px-2 py-4 text-center">
                Sin bloques. Añade el primero abajo.
              </p>
            )}
            {blocks.map((block) => (
              <BlockOutlineItem
                key={block.id}
                block={block}
                selected={selectedIds.has(block.id)}
                onSelect={(e) => {
                  if (e.ctrlKey || e.metaKey || e.shiftKey) {
                    toggleSelect(block.id);
                  } else {
                    selectOnly(block.id);
                  }
                }}
              />
            ))}
          </div>

          {/* Add block form */}
          <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border p-3 space-y-2">
            <p className="text-xs font-semibold text-text-secondary dark:text-text-dark-secondary uppercase tracking-wide">
              Añadir bloque
            </p>
            <div>
              <FieldLabel>Tipo</FieldLabel>
              <Select
                fieldSize="sm"
                value={newType}
                onChange={(e) => setNewType(e.target.value)}
              >
                {BLOCK_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <FieldLabel>Título (opcional)</FieldLabel>
              <input
                type="text"
                className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary px-2 py-1.5 text-sm"
                value={newTitle}
                onChange={(e) => setNewTitle(e.target.value)}
                placeholder="Título del bloque"
                onKeyDown={(e) => {
                  if (e.key === 'Enter') void handleAddBlock();
                }}
              />
            </div>
            <Button
              type="button"
              variant="primary"
              size="sm"
              loading={busy}
              className="w-full"
              onClick={() => void handleAddBlock()}
            >
              Añadir
            </Button>
          </div>
        </div>

        {/* Properties panel */}
        <div className="flex-1 overflow-y-auto p-6">
          {actionError && (
            <div className="mb-4 rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light flex justify-between gap-4">
              <span>{actionError}</span>
              <Button type="button" variant="ghost" size="xs" onClick={() => setActionError(null)}>
                ✕
              </Button>
            </div>
          )}

          {selectedArr.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center space-y-2">
              <p className="text-sm text-text-muted dark:text-text-dark-muted">
                Selecciona un bloque del outline para editar sus propiedades.
              </p>
              <p className="text-xs text-text-muted dark:text-text-dark-muted">
                Mantén Ctrl / ⌘ para selección múltiple.
              </p>
            </div>
          ) : (
            <div className="max-w-md space-y-6">
              <div>
                <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary mb-1">
                  {selectedArr.length === 1 && singleSelected
                    ? singleSelected.title
                      ? `«${singleSelected.title}»`
                      : `Bloque ${singleSelected.type}`
                    : `${selectedArr.length} bloques seleccionados`}
                </h3>
                {selectedArr.length > 1 && (
                  <p className="text-xs text-text-muted dark:text-text-dark-muted">
                    El cambio de estado se aplicará a todos los seleccionados simultáneamente.
                  </p>
                )}
              </div>

              {/* State selector */}
              <div>
                <FieldLabel>Estado del bloque</FieldLabel>
                <div className="flex gap-2 mt-1">
                  {(['editable', 'modifiable', 'locked'] as const).map((s) => (
                    <button
                      key={s}
                      type="button"
                      disabled={busy}
                      onClick={() => void handleStateChange(s)}
                      className={[
                        'px-3 py-1.5 rounded text-xs font-medium transition-all',
                        'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                        panelState === s
                          ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                          : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
                        'disabled:opacity-50 disabled:pointer-events-none',
                      ].join(' ')}
                    >
                      {BLOCK_STATE_LABELS[s]}
                    </button>
                  ))}
                </div>
                <p className="mt-2 text-xs text-text-muted dark:text-text-dark-muted">
                  {selectedArr.length > 1 && panelState === ''
                    ? 'Los bloques seleccionados tienen estados distintos.'
                    : panelState === 'editable'
                      ? 'El autor puede escribir libremente.'
                      : panelState === 'modifiable'
                        ? 'El revisor puede proponer cambios (diff visual).'
                        : panelState === 'locked'
                          ? 'Solo visible, no editable por ningún rol.'
                          : ''}
                </p>
              </div>

              {/* Mandatory selector */}
              <div>
                <FieldLabel>Obligatoriedad</FieldLabel>
                <div className="flex gap-2 mt-1">
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void handleMandatoryChange(true)}
                    className={[
                      'px-3 py-1.5 rounded text-xs font-medium transition-all',
                      'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                      panelMandatory === true
                        ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                        : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
                      'disabled:opacity-50 disabled:pointer-events-none',
                    ].join(' ')}
                  >
                    Obligatorio
                  </button>
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void handleMandatoryChange(false)}
                    className={[
                      'px-3 py-1.5 rounded text-xs font-medium transition-all',
                      'border focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                      panelMandatory === false
                        ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                        : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50 dark:hover:border-odoo-dark-purple/50',
                      'disabled:opacity-50 disabled:pointer-events-none',
                    ].join(' ')}
                  >
                    Opcional
                  </button>
                </div>
                <p className="mt-2 text-xs text-text-muted dark:text-text-dark-muted">
                  {selectedArr.length > 1 && panelMandatory === null
                    ? 'Los bloques seleccionados tienen obligatoriedad distinta.'
                    : panelMandatory
                      ? 'El bloque se marcará como requerido en el documento.'
                      : 'El bloque se considera opcional.'}
                </p>
              </div>

              {/* Individual block details */}
              {singleSelected && (
                <div className="rounded-lg border border-ui-border dark:border-ui-dark-border p-3 space-y-2 text-xs">
                  <p className="font-semibold text-text-secondary dark:text-text-dark-secondary uppercase tracking-wide">
                    Detalles
                  </p>
                  <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-text-muted dark:text-text-dark-muted">
                    <span>Tipo</span>
                    <span className="font-mono text-text-primary dark:text-text-dark-primary">{singleSelected.type}</span>
                    <span>ID</span>
                    <span className="font-mono text-text-primary dark:text-text-dark-primary truncate">{singleSelected.id.slice(0, 8)}…</span>
                    <span>Orden</span>
                    <span className="text-text-primary dark:text-text-dark-primary">{singleSelected.sort_order}</span>
                    <span>Obligatorio</span>
                    <span>
                      {singleSelected.mandatory ? (
                        <span className="rounded bg-odoo-purple/10 text-odoo-purple dark:bg-odoo-dark-purple/20 dark:text-odoo-dark-purple px-1.5 py-0.5 font-medium">
                          Sí
                        </span>
                      ) : (
                        <span className="text-text-muted dark:text-text-dark-muted">No</span>
                      )}
                    </span>
                  </div>
                </div>
              )}

              {/* Delete */}
              <div className="pt-2 border-t border-ui-border dark:border-ui-dark-border">
                <Button
                  type="button"
                  variant="outlineWarning"
                  size="sm"
                  loading={busy}
                  onClick={() => void handleDeleteSelected()}
                >
                  Eliminar {selectedArr.length > 1 ? `${selectedArr.length} bloques` : 'bloque'}
                </Button>
              </div>
            </div>
          )}

          {/* Selection shortcuts hint */}
          {selectedArr.length > 0 && (
            <div className="mt-6">
              <button
                type="button"
                className="text-xs text-text-muted dark:text-text-dark-muted hover:underline"
                onClick={clearSelection}
              >
                Deseleccionar todo
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Outline item ──────────────────────────────────────────────────────────────

type OutlineItemProps = {
  block: TemplateBlock;
  selected: boolean;
  onSelect: (e: React.MouseEvent) => void;
};

function BlockOutlineItem({ block, selected, onSelect }: OutlineItemProps) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={[
        'w-full text-left rounded px-2 py-1.5 flex items-center gap-2 transition-colors',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
        selected
          ? 'bg-odoo-purple/10 dark:bg-odoo-dark-purple/15 border border-odoo-purple/30 dark:border-odoo-dark-purple/40'
          : 'hover:bg-ui-body dark:hover:bg-ui-dark-bg border border-transparent',
      ].join(' ')}
    >
      {/* Mandatory indicator */}
      {block.mandatory && (
        <span
          className="shrink-0 size-1.5 rounded-full bg-odoo-purple dark:bg-odoo-dark-purple"
          title="Bloque obligatorio"
          aria-label="Obligatorio"
        />
      )}

      {/* Block title / type */}
      <span className="flex-1 min-w-0 text-xs text-text-primary dark:text-text-dark-primary truncate">
        {block.title ?? block.type}
      </span>

      {/* State badge */}
      <span
        className={[
          'shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium',
          STATE_BADGE[block.block_state],
        ].join(' ')}
      >
        {block.block_state === 'editable'
          ? 'E'
          : block.block_state === 'modifiable'
            ? 'M'
            : 'L'}
      </span>
    </button>
  );
}
