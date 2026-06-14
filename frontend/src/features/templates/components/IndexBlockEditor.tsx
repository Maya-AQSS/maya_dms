import { useTranslation } from 'react-i18next';
import type { BlockType } from '../../../types/blocks';
import { extractHeadings } from '../../../utils/tiptapHeadings';

export interface IndexConfig {
  kind: 'index';
  /** Claves `{blockId}#{idx}` de TÍTULOS excluidos del índice (deny-list). */
  excludedHeadings: string[];
}

/** Clave estable de un título: id del bloque + posición del encabezado en él. */
export const headingKey = (blockId: string, headingIndex: number): string =>
  `${blockId}#${headingIndex}`;

/** Normaliza el contenido de un bloque índice a una config válida. */
export function parseIndexConfig(raw: unknown): IndexConfig {
  if (!raw || typeof raw !== 'object') return { kind: 'index', excludedHeadings: [] };
  const obj = raw as Record<string, unknown>;
  const excludedHeadings = Array.isArray(obj.excludedHeadings)
    ? obj.excludedHeadings.filter((x): x is string => typeof x === 'string')
    : [];
  return { kind: 'index', excludedHeadings };
}

/**
 * Forma mínima de bloque que necesita el índice. La cumplen tanto `TemplateBlock`
 * (editor de plantilla) como un mapeo de `DocumentDisplayBlock` (documento:
 * `id` = template_block_id para que la config sea estable).
 */
export interface IndexSelectableBlock {
  id: string;
  title: string | null;
  block_type?: BlockType;
  /** Contenido del bloque (documento) para extraer los títulos internos. */
  content?: unknown;
  /** Contenido por defecto (plantilla) si no hay `content`. */
  default_content?: unknown;
}

interface IndexEntry {
  key: string;
  level: number;
  text: string;
}

/** Construye las entradas del índice: TODOS los títulos internos de TODOS los bloques. */
function buildEntries(blocks: IndexSelectableBlock[], currentBlockId: string | null): IndexEntry[] {
  const entries: IndexEntry[] = [];
  for (const b of blocks) {
    if (b.id === currentBlockId || b.block_type === 'index') continue;
    const headings = extractHeadings(b.content ?? b.default_content);
    headings.forEach((h, i) => entries.push({ key: headingKey(b.id, i), level: h.level, text: h.text }));
  }
  return entries;
}

interface IndexBlockEditorProps {
  /** Todos los bloques (en orden del documento). */
  blocks: IndexSelectableBlock[];
  /** Id del propio bloque índice (se excluye). */
  currentBlockId: string | null;
  value: IndexConfig;
  onChange: (next: IndexConfig) => void;
}

/**
 * Editor del bloque ÍNDICE. No selecciona bloques: muestra TODOS los títulos
 * internos (encabezados H1–H3) de TODOS los bloques con un checkbox por título,
 * para incluir/excluir cada uno. Los títulos se leen en vivo del contenido de
 * cada bloque (al renombrar un encabezado, el índice cambia). El número de
 * página lo resuelve el render.
 */
export function IndexBlockEditor({ blocks, currentBlockId, value, onChange }: IndexBlockEditorProps) {
  const { t } = useTranslation('templates');
  const entries = buildEntries(blocks, currentBlockId);
  const excluded = new Set(value.excludedHeadings);
  const minLevel = entries.length ? Math.min(...entries.map((e) => e.level)) : 1;

  const toggle = (key: string) => {
    const set = new Set(value.excludedHeadings);
    if (set.has(key)) set.delete(key);
    else set.add(key);
    onChange({ ...value, excludedHeadings: [...set] });
  };

  const allIncluded = entries.length > 0 && entries.every((e) => !excluded.has(e.key));
  const toggleAll = () => {
    onChange({ ...value, excludedHeadings: allIncluded ? entries.map((e) => e.key) : [] });
  };

  return (
    <div className="flex-1 overflow-y-auto p-6">
      <div className="mx-auto w-full max-w-2xl space-y-4 rounded-xl border border-ui-border bg-white p-6 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-bold uppercase tracking-widest text-text-secondary">{t('index.autoTitle')}</h3>
            <p className="mt-1 text-xs text-text-muted">
              {t('index.help')}
            </p>
          </div>
          {entries.length > 0 && (
            <button
              type="button"
              onClick={toggleAll}
              className="shrink-0 text-xs font-bold text-odoo-purple hover:underline"
            >
              {allIncluded ? t('index.none') : t('index.all')}
            </button>
          )}
        </div>

        {entries.length === 0 ? (
          <p className="text-sm text-text-muted italic">
            {t('index.noHeadings')}
          </p>
        ) : (
          <ul className="divide-y divide-ui-border rounded border border-ui-border dark:divide-ui-dark-border dark:border-ui-dark-border">
            {entries.map((e) => (
              <li key={e.key}>
                <label
                  className="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg"
                  style={{ paddingLeft: `${12 + (e.level - minLevel) * 16}px` }}
                >
                  <input
                    type="checkbox"
                    checked={!excluded.has(e.key)}
                    onChange={() => toggle(e.key)}
                    className="h-4 w-4 rounded border-ui-border"
                  />
                  <span className="flex-1 truncate text-text-primary dark:text-text-dark-primary">{e.text}</span>
                </label>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
