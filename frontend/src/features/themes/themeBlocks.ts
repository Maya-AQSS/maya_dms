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
  label: string;
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
    label: 'Contenido del documento',
    defaultBox: { x: 20, y: 35, w: 170, h: 227 },
    defaultProps: { label: 'Aquí se carga el cuerpo del documento' },
  },
  {
    type: 'text',
    label: 'Texto',
    defaultBox: { x: 20, y: 10, w: 70, h: 12 },
    defaultProps: { text: 'Texto', size: 9, color: '#333333', align: 'left' },
  },
  {
    type: 'image',
    label: 'Imagen',
    defaultBox: { x: 20, y: 20, w: 60, h: 60 },
    defaultProps: { alt: '', opacity: 1, objectFit: 'contain' },
  },
  {
    type: 'page_number',
    label: 'Nº de página',
    defaultBox: { x: 150, y: 282, w: 45, h: 8 },
    defaultProps: { format: 'page-of-pages', align: 'right' },
  },
  {
    type: 'date',
    label: 'Fecha',
    defaultBox: { x: 20, y: 282, w: 50, h: 8 },
    defaultProps: { format: 'short', align: 'left' },
  },
];

/** Crea una nueva region con caja en mm y `z` por encima de las existentes. */
export function newRegion(type: ThemeBlockType, occupiedZ: number): ThemeLayoutRegion {
  const def = BLOCK_CATALOG.find((b) => b.type === type)!;
  return {
    id: `r-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
    type,
    box: { ...def.defaultBox, z: occupiedZ + 1 },
    props: { ...def.defaultProps },
  };
}

export function labelForType(type: ThemeBlockType): string {
  return BLOCK_CATALOG.find((b) => b.type === type)?.label ?? type;
}
