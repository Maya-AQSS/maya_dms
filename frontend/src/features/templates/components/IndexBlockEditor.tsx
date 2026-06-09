import { FieldLabel } from '@ceedcv-maya/shared-ui-react';
import type { TemplateBlock } from '../../../types/blocks';
import { BLOCK_TYPE_LABELS } from '../../../types/blocks';

export interface IndexConfig {
  kind: 'index';
  blockIds: string[];
  includeHeadings: boolean;
}

/** Normaliza el default_content de un bloque índice a una config válida. */
export function parseIndexConfig(raw: unknown): IndexConfig {
  if (!raw || typeof raw !== 'object') return { kind: 'index', blockIds: [], includeHeadings: false };
  const obj = raw as Record<string, unknown>;
  const blockIds = Array.isArray(obj.blockIds) ? obj.blockIds.filter((x): x is string => typeof x === 'string') : [];
  return { kind: 'index', blockIds, includeHeadings: Boolean(obj.includeHeadings) };
}

interface IndexBlockEditorProps {
  /** Todos los bloques de la plantilla (en orden). */
  blocks: TemplateBlock[];
  /** Id del propio bloque índice (se excluye de la lista). */
  currentBlockId: string | null;
  value: IndexConfig;
  onChange: (next: IndexConfig) => void;
}

/**
 * Editor del bloque ÍNDICE. No tiene editor de texto: el índice se autogenera.
 * El usuario elige con checkboxes qué bloques entran y si además se incluyen los
 * subtítulos (H1–H3) internos de cada bloque. El número de página lo resuelve el
 * render (no se configura aquí).
 */
export function IndexBlockEditor({ blocks, currentBlockId, value, onChange }: IndexBlockEditorProps) {
  // Bloques seleccionables: todos menos el propio índice y otros índices/blanco.
  const selectable = blocks.filter(
    (b) => b.id !== currentBlockId && b.block_type !== 'index' && b.block_type !== 'blank',
  );

  const toggle = (id: string) => {
    const set = new Set(value.blockIds);
    if (set.has(id)) set.delete(id);
    else set.add(id);
    onChange({ ...value, blockIds: [...set] });
  };

  const allSelected = selectable.length > 0 && selectable.every((b) => value.blockIds.includes(b.id));
  const toggleAll = () => {
    onChange({ ...value, blockIds: allSelected ? [] : selectable.map((b) => b.id) });
  };

  return (
    <div className="flex-1 overflow-y-auto p-6">
      <div className="mx-auto w-full max-w-2xl space-y-5 rounded-xl border border-ui-border bg-white p-6 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card">
        <div>
          <h3 className="text-sm font-bold uppercase tracking-widest text-text-secondary">Índice automático</h3>
          <p className="mt-1 text-xs text-text-muted">
            El índice se genera al exportar. Elige qué bloques aparecen y su número de página se calcula solo.
          </p>
        </div>

        <label className="flex items-center gap-2 text-sm text-text-primary dark:text-text-dark-primary cursor-pointer">
          <input
            type="checkbox"
            checked={value.includeHeadings}
            onChange={(e) => onChange({ ...value, includeHeadings: e.target.checked })}
            className="h-4 w-4 rounded border-ui-border"
          />
          Incluir también los subtítulos (H1–H3) del contenido de cada bloque
        </label>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <FieldLabel>Bloques en el índice</FieldLabel>
            {selectable.length > 0 && (
              <button type="button" onClick={toggleAll} className="text-xs font-bold text-odoo-purple hover:underline">
                {allSelected ? 'Ninguno' : 'Todos'}
              </button>
            )}
          </div>
          {selectable.length === 0 ? (
            <p className="text-sm text-text-muted">No hay otros bloques que incluir todavía.</p>
          ) : (
            <ul className="divide-y divide-ui-border rounded border border-ui-border dark:divide-ui-dark-border dark:border-ui-dark-border">
              {selectable.map((b) => (
                <li key={b.id}>
                  <label className="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg">
                    <input
                      type="checkbox"
                      checked={value.blockIds.includes(b.id)}
                      onChange={() => toggle(b.id)}
                      className="h-4 w-4 rounded border-ui-border"
                    />
                    <span className="flex-1 truncate">{b.title || '(sin título)'}</span>
                    {b.block_type && b.block_type !== 'content' && (
                      <span className="text-xs text-text-muted">{BLOCK_TYPE_LABELS[b.block_type]}</span>
                    )}
                  </label>
                </li>
              ))}
            </ul>
          )}
          {value.blockIds.length === 0 && selectable.length > 0 && (
            <p className="mt-2 text-xs text-text-muted italic">
              Si no marcas ninguno, el índice incluirá por defecto todos los bloques de contenido.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
