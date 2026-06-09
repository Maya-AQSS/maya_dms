import { useMemo } from 'react';
import { PX_PER_MM } from '../../../components/canvas/canvasModel';
import { pageDimsMm } from '../../themes/pageSizes';
import { parseCoverContent, parseCoverFill, type CoverRegion } from '../../templates/cover/coverModel';
import { CoverRegionPreview } from '../../templates/cover/CoverRegionPreview';
import { parseIndexConfig, headingKey } from '../../templates/components/IndexBlockEditor';
import { extractHeadings, type HeadingEntry } from '../../../utils/tiptapHeadings';
import type { BlockType } from '../../../types/blocks';

/** Ancho objetivo (px) de la miniatura de portada en el previsualizador. */
const COVER_PREVIEW_WIDTH = 520;

/**
 * Forma mínima común a `TemplateBlock` y `DocumentDisplayBlock`: lo que la
 * previsualización estructural necesita. Así el componente sirve tanto al
 * previsualizador de plantilla (sin `content`) como al de documento.
 */
export interface StructuralBlock {
  block_type?: BlockType;
  default_content: unknown;
  /** Valores de relleno (solo documentos; las plantillas usan los por defecto). */
  content?: unknown;
  title: string | null;
  template_block_id?: string;
  id?: string;
  is_deleted?: boolean;
}

const structuralKey = (b: StructuralBlock, i: number): string =>
  b.template_block_id ?? b.id ?? String(i);

/** ¿Es un bloque estructural (portada, índice, hoja en blanco)? */
export const isStructuralBlockType = (type?: BlockType): boolean =>
  type === 'cover' || type === 'index' || type === 'blank';

/**
 * Render WYSIWYG de los bloques *estructurales* (portada, índice, hoja en
 * blanco) en el previsualizador. Estos bloques no tienen cuerpo Tiptap, así que
 * sin este componente caían en el fallback "Sin contenido.".
 *
 * La fidelidad total vive en el PDF (pantalla completa); aquí mostramos una
 * representación ligera y honesta de cada tipo.
 */
export function StructuralBlockPreview({
  block,
  allBlocks,
}: {
  block: StructuralBlock;
  /** Resto de bloques (para listar las secciones en el índice). */
  allBlocks?: StructuralBlock[];
}) {
  switch (block.block_type) {
    case 'cover':
      return <CoverMiniPreview geometry={block.default_content} fill={block.content} />;
    case 'index':
      return <IndexPreview block={block} allBlocks={allBlocks ?? []} />;
    case 'blank':
      return <BlankPreview />;
    default:
      return null;
  }
}

/** Miniatura escalada de la portada (misma geometría mm→px que el lienzo). */
function CoverMiniPreview({ geometry, fill }: { geometry: unknown; fill: unknown }) {
  const cover = useMemo(() => parseCoverContent(geometry, 'A4'), [geometry]);
  const values = useMemo(() => parseCoverFill(fill), [fill]);
  const page = pageDimsMm(cover.page.size);
  const wPx = page.width * PX_PER_MM;
  const hPx = page.height * PX_PER_MM;
  const scale = COVER_PREVIEW_WIDTH / wPx;

  if (cover.regions.length === 0) {
    return (
      <div
        className="mx-auto flex items-center justify-center rounded border border-dashed border-ui-border bg-ui-body/40 text-xs italic text-text-muted dark:border-ui-dark-border"
        style={{ width: COVER_PREVIEW_WIDTH, height: hPx * scale }}
      >
        Portada sin elementos.
      </div>
    );
  }

  return (
    <div
      className="mx-auto overflow-hidden border border-ui-border bg-white shadow-sm dark:border-ui-dark-border"
      style={{ width: COVER_PREVIEW_WIDTH, height: hPx * scale }}
    >
      <div
        style={{
          position: 'relative',
          width: wPx,
          height: hPx,
          transform: `scale(${scale})`,
          transformOrigin: 'top left',
        }}
      >
        {cover.regions.map((r: CoverRegion) => {
          const key = typeof r.props.key === 'string' ? r.props.key : '';
          const fillValue = r.type === 'text_placeholder' ? values[key] : undefined;
          return (
            <div
              key={r.id}
              style={{
                position: 'absolute',
                left: r.box.x * PX_PER_MM,
                top: r.box.y * PX_PER_MM,
                width: r.box.w * PX_PER_MM,
                height: r.box.h * PX_PER_MM,
                zIndex: r.box.z ?? 1,
              }}
            >
              <CoverRegionPreview region={r} fillValue={fillValue} />
            </div>
          );
        })}
      </div>
    </div>
  );
}

/**
 * Vista del índice: TODOS los títulos internos (encabezados H1–H3) de TODOS los
 * bloques, menos los excluidos en la config (`excludedHeadings`). Se resuelven
 * EN VIVO desde `allBlocks`, así que editar un encabezado actualiza el índice.
 * Espeja `TocBuilderService` (backend). Clave de título = `{blockId}#{idx}`.
 */
function IndexPreview({ block, allBlocks }: { block: StructuralBlock; allBlocks: StructuralBlock[] }) {
  const selfKey = block.template_block_id ?? block.id ?? '';
  const cfg = parseIndexConfig(block.content ?? block.default_content);
  const excluded = new Set(cfg.excludedHeadings);

  const entries: HeadingEntry[] = [];
  allBlocks.forEach((b, i) => {
    const bKey = structuralKey(b, i);
    if (b.is_deleted || bKey === selfKey || b.block_type === 'index') return;
    extractHeadings(b.content ?? b.default_content).forEach((h, hi) => {
      if (!excluded.has(headingKey(bKey, hi))) entries.push(h);
    });
  });
  const minLevel = entries.length ? Math.min(...entries.map((e) => e.level)) : 1;

  return (
    <div className="rounded border border-ui-border bg-ui-body/30 p-4 dark:border-ui-dark-border">
      <p className="mb-2 text-2xs font-black uppercase tracking-widest text-text-muted">
        Índice · se genera automáticamente al exportar
      </p>
      {entries.length === 0 ? (
        <p className="text-xs italic text-text-muted">
          Los bloques aún no tienen títulos internos (encabezados).
        </p>
      ) : (
        <ol className="space-y-1">
          {entries.map((e, i) => (
            <li
              key={`${i}-${e.text}`}
              className="flex items-baseline gap-2 text-sm"
              style={{ paddingLeft: `${(e.level - minLevel) * 16}px` }}
            >
              <span className="flex-1 truncate text-text-primary dark:text-text-dark-primary">
                {e.text}
              </span>
              <span className="text-text-muted">·</span>
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}

/** Hoja en blanco: una página blanca con borde (no un bloque gris). */
function BlankPreview() {
  return (
    <div className="mx-auto flex aspect-[210/297] w-full max-w-[280px] flex-col items-center justify-center gap-2 rounded border border-ui-border bg-white text-center shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card">
      <span className="text-xs font-semibold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
        Página en blanco
      </span>
    </div>
  );
}
