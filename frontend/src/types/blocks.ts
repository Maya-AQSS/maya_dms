export type BlockState = 'editable' | 'modifiable' | 'locked' | 'optional';

/** Familia de "bloques de maquetación" (layout blocks). */
export type BlockType = 'content' | 'cover' | 'blank' | 'index';

export type TemplateBlock = {
  id: string;
  template_id: string;
  type: string;
  /** Familia de maquetación: content (normal) | cover (portada) | blank (hoja en blanco) | index (índice). Backend siempre lo envía; opcional por compat con snapshots antiguos/fixtures. */
  block_type?: BlockType;
  title: string | null;
  default_content: unknown | null;
  description: unknown | null;
  block_state: BlockState;
  /** Fuerza que el siguiente bloque empiece en página nueva en el PDF. */
  page_break_after?: boolean;
  /** La numeración de página del cuerpo empieza en este bloque (exclusivo por plantilla). */
  page_number_start?: boolean;
  /** Override de tema por bloque (null = hereda el tema por defecto de la plantilla). */
  theme_id?: string | null;
  /** Si false, el bloque no lleva tema (ni estilo ni chrome) y ocupa su propia página. */
  apply_theme?: boolean;
  mandatory: boolean;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
};

export type BlocksListResponse = {
  data: TemplateBlock[];
};

export type CreateBlockPayload = {
  type: string;
  block_type?: BlockType;
  title?: string | null;
  default_content?: unknown | null;
  description?: unknown | null;
  block_state?: BlockState;
  page_break_after?: boolean;
  page_number_start?: boolean;
  theme_id?: string | null;
  apply_theme?: boolean;
  mandatory?: boolean;
  sort_order?: number;
};

export type UpdateBlockPayload = {
  type?: string;
  block_type?: BlockType;
  title?: string | null;
  default_content?: unknown | null;
  description?: unknown | null;
  block_state?: BlockState;
  page_break_after?: boolean;
  page_number_start?: boolean;
  theme_id?: string | null;
  apply_theme?: boolean;
  mandatory?: boolean;
  sort_order?: number;
};

export const BLOCK_TYPE_LABELS: Record<BlockType, string> = {
  content: 'Contenido',
  cover: 'Portada',
  blank: 'Hoja en blanco',
  index: 'Índice',
};

/**
 * Tipos de bloque "estructurales" que NO llevan cuerpo editable y, por tanto,
 * están exentos de la invariante "los bloques bloqueados/modificables no pueden
 * estar vacíos" (p. ej. la hoja en blanco está vacía por definición).
 *
 * Fuente única de verdad en frontend; el espejo en backend es
 * `BlockType::requiresBodyContent()`. Para añadir un futuro tipo sin cuerpo,
 * basta con incluirlo aquí (y en el enum del backend).
 */
const BODYLESS_BLOCK_TYPES: ReadonlySet<BlockType> = new Set<BlockType>(['cover', 'index', 'blank']);

/** ¿Este tipo de bloque exige contenido predeterminado cuando está bloqueado/modificable? */
export const blockTypeRequiresContent = (type?: BlockType | null): boolean =>
  !BODYLESS_BLOCK_TYPES.has((type ?? 'content') as BlockType);

export type BulkUpdateBlockPayload = {
  ids: string[];
  block_state?: BlockState;
  mandatory?: boolean;
};

export const BLOCK_STATE_LABELS: Record<BlockState, string> = {
  editable: 'Editable',
  modifiable: 'Modificable',
  locked: 'Bloqueado',
  optional: 'Opcional',
};
