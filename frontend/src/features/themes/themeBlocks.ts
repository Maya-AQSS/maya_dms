import type { ThemeBlockType, ThemeLayoutRegion } from '../../types/themes';

/** Caja por defecto (mm) de un bloque recién añadido. */
export interface DefaultBox {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface BlockCatalogEntry {
  type: ThemeBlockType;
  /** Clave i18n del nombre del bloque (namespace `themes`, p. ej. `blocks.text`). */
  labelKey: string;
  /** Geometría inicial en mm (relativa a la esquina superior-izq de la página). */
  defaultBox: DefaultBox;
  defaultProps: Record<string, unknown>;
}

/**
 * Catálogo de bloques disponibles en el editor de layout. Las cajas están en
 * milímetros, pensadas para A4 (210×297); en páginas mayores el usuario las
 * reubica. El `content_slot` reserva ~2 cm de margen lateral y 3.5 cm sup/inf.
 */
export const BLOCK_CATALOG: BlockCatalogEntry[] = [
  {
    type: 'content_slot',
    labelKey: 'blocks.documentContent',
    defaultBox: { x: 20, y: 35, w: 170, h: 227 },
    defaultProps: { labelKey: 'blocks.bodyPlaceholder' },
  },
  {
    type: 'text',
    labelKey: 'blocks.text',
    defaultBox: { x: 20, y: 10, w: 70, h: 12 },
    defaultProps: { text: 'Texto', size: 9, color: '#333333', align: 'left' },
  },
  {
    type: 'image',
    labelKey: 'blocks.image',
    defaultBox: { x: 20, y: 20, w: 60, h: 60 },
    defaultProps: { alt: '', opacity: 1, objectFit: 'contain' },
  },
  {
    type: 'page_number',
    labelKey: 'blocks.pageNumber',
    defaultBox: { x: 150, y: 282, w: 45, h: 8 },
    defaultProps: { format: 'page-of-pages', align: 'right' },
  },
  {
    type: 'date',
    labelKey: 'blocks.date',
    defaultBox: { x: 20, y: 282, w: 50, h: 8 },
    defaultProps: { format: 'short', align: 'left' },
  },
];

type TFn = (key: string) => string;

/** Crea una nueva region con caja en mm y `z` por encima de las existentes. */
export function newRegion(type: ThemeBlockType, occupiedZ: number, t?: TFn): ThemeLayoutRegion {
  const def = BLOCK_CATALOG.find((b) => b.type === type)!;
  const props = { ...def.defaultProps };
  // El content_slot lleva una etiqueta por defecto resoluble vía i18n.
  if (t && typeof props.labelKey === 'string') {
    props.label = t(props.labelKey);
    delete props.labelKey;
  }
  return {
    id: `r-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
    type,
    box: { ...def.defaultBox, z: occupiedZ + 1 },
    props,
  };
}

/** Clave i18n del nombre del bloque (namespace `themes`). */
export function labelKeyForType(type: ThemeBlockType): string {
  return BLOCK_CATALOG.find((b) => b.type === type)?.labelKey ?? '';
}

/** Nombre traducido del bloque; si no se pasa `t`, devuelve el tipo crudo. */
export function labelForType(type: ThemeBlockType, t?: TFn): string {
  const key = labelKeyForType(type);
  return t && key ? t(key) : (key || type);
}
