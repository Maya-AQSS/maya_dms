import type { CanvasBox, CanvasRegion } from '../../../components/canvas/canvasModel';

/**
 * Modelo del bloque "portada" (cover): una página con elementos posicionados de
 * forma absoluta en mm (reutiliza `AbsoluteCanvas`). En la PLANTILLA se diseña
 * la geometría + textos estáticos + *placeholders*; en el DOCUMENTO sólo se
 * rellena el texto de los placeholders (la geometría queda bloqueada).
 *
 * La geometría se guarda en `default_content` del bloque de plantilla; los
 * valores de placeholders en `content` del bloque de documento
 * (`{ kind:'cover-fill', values:{ [key]: texto } }`). El render PHP
 * (`CoverRenderService`) hace el merge — este modelo es su espejo en cliente.
 */

export type CoverRegionType = 'text' | 'text_placeholder' | 'date' | 'page_number' | 'image';

export type TextAlign = 'left' | 'center' | 'right';

export interface CoverRegion extends CanvasRegion {
  id: string;
  type: CoverRegionType;
  box: CanvasBox;
  props: Record<string, unknown>;
}

export interface CoverContent {
  kind: 'cover';
  page: { size: string };
  regions: CoverRegion[];
}

export interface CoverFill {
  kind: 'cover-fill';
  values: Record<string, string>;
}

interface CatalogEntry {
  type: CoverRegionType;
  label: string;
  defaultBox: CanvasBox;
  defaultProps: Record<string, unknown>;
}

/**
 * Catálogo de elementos colocables en la portada. Cajas en mm (pensadas para
 * A4 210×297; en otras páginas el usuario las reubica).
 *
 */
export const COVER_CATALOG: CatalogEntry[] = [
  {
    type: 'text',
    label: 'Texto',
    defaultBox: { x: 30, y: 40, w: 150, h: 20 },
    defaultProps: { text: 'Texto', size: 16, color: '#1a1a1a', align: 'left', weight: 'normal' },
  },
  {
    type: 'text_placeholder',
    label: 'Campo de texto (rellenable)',
    defaultBox: { x: 30, y: 80, w: 150, h: 16 },
    defaultProps: { key: '', label: 'Campo', defaultText: '', size: 14, color: '#1a1a1a', align: 'left', weight: 'normal' },
  },
  {
    type: 'date',
    label: 'Fecha',
    defaultBox: { x: 30, y: 255, w: 80, h: 10 },
    defaultProps: { format: 'long', align: 'left', size: 11, color: '#666666' },
  },
  {
    type: 'page_number',
    label: 'Nº de página',
    defaultBox: { x: 150, y: 282, w: 45, h: 8 },
    defaultProps: { format: 'page-of-pages', align: 'right', size: 10, color: '#666666' },
  },
  {
    type: 'image',
    label: 'Imagen / logo',
    defaultBox: { x: 75, y: 30, w: 60, h: 40 },
    defaultProps: { src: '', srcUrl: '', alt: '', objectFit: 'contain', opacity: 1 },
  },
];

export function coverLabelForType(type: CoverRegionType): string {
  return COVER_CATALOG.find((c) => c.type === type)?.label ?? type;
}

/** Crea una región nueva con caja en mm y `z` por encima de las existentes. */
export function newCoverRegion(type: CoverRegionType, occupiedZ: number): CoverRegion {
  const def = COVER_CATALOG.find((c) => c.type === type);
  const base = def ?? COVER_CATALOG[0];
  return {
    id: `cv-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
    type,
    box: { ...base.defaultBox, z: occupiedZ + 1 },
    props: { ...base.defaultProps },
  };
}

/** Portada vacía con el tamaño de página dado. */
export function emptyCover(pageSize: string): CoverContent {
  return { kind: 'cover', page: { size: pageSize }, regions: [] };
}

/**
 * Normaliza un `default_content` (unknown, puede venir de snapshots/JSON) a un
 * `CoverContent` válido. Tolerante: descarta regiones malformadas.
 */
export function parseCoverContent(raw: unknown, pageSize: string): CoverContent {
  if (!raw || typeof raw !== 'object') return emptyCover(pageSize);
  const obj = raw as Record<string, unknown>;
  const regionsRaw = Array.isArray(obj.regions) ? obj.regions : [];
  const regions: CoverRegion[] = [];
  for (const r of regionsRaw) {
    if (!r || typeof r !== 'object') continue;
    const rr = r as Record<string, unknown>;
    const box = rr.box as CanvasBox | undefined;
    if (!box || typeof box !== 'object') continue;
    const type = (rr.type as CoverRegionType) ?? 'text';
    regions.push({
      id: typeof rr.id === 'string' ? rr.id : `cv-${Math.random().toString(36).slice(2, 9)}`,
      type,
      box: {
        x: Number(box.x) || 0,
        y: Number(box.y) || 0,
        w: Number(box.w) || 10,
        h: Number(box.h) || 10,
        z: Number(box.z) || 1,
      },
      props: (rr.props && typeof rr.props === 'object' ? (rr.props as Record<string, unknown>) : {}),
    });
  }
  const page = (obj.page && typeof obj.page === 'object' ? (obj.page as { size?: string }) : {});
  return { kind: 'cover', page: { size: page.size ?? pageSize }, regions };
}

/** Extrae los placeholders (key + label) de una portada, para el editor de relleno. */
export function coverPlaceholders(cover: CoverContent): Array<{ id: string; key: string; label: string; box: CanvasBox }> {
  return cover.regions
    .filter((r) => r.type === 'text_placeholder')
    .map((r) => ({
      id: r.id,
      key: typeof r.props.key === 'string' ? r.props.key : '',
      label: typeof r.props.label === 'string' ? r.props.label : 'Campo',
      box: r.box,
    }));
}

/** Normaliza el `content` de un bloque de documento a un mapa de valores. */
export function parseCoverFill(raw: unknown): Record<string, string> {
  if (!raw || typeof raw !== 'object') return {};
  const values = (raw as Record<string, unknown>).values;
  if (!values || typeof values !== 'object') return {};
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(values as Record<string, unknown>)) {
    if (typeof v === 'string' || typeof v === 'number') out[k] = String(v);
  }
  return out;
}
