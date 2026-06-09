import type { Theme, ThemeLayoutRegion, ThemeBlockType } from '../../../types/themes';
import { pageDimsMm, type PageDimsMm } from '../pageSizes';

interface ThemeA4PreviewProps {
  /** Theme con layout completo. `null` muestra placeholder. */
  theme: Theme | null;
  className?: string;
}

/**
 * Rectángulo en porcentajes para una region, a partir de su caja `box` (mm)
 * relativa a las dimensiones físicas de la página. Acepta `grid` legacy
 * (celdas 12×52) y `position` (%) como compatibilidad hacia atrás.
 */
function regionRect(
  region: ThemeLayoutRegion,
  page: PageDimsMm,
): { left: string; top: string; width: string; height: string; z: number } | null {
  const b = region.box;
  if (b) {
    return {
      left: `${(b.x / page.width) * 100}%`,
      top: `${(b.y / page.height) * 100}%`,
      width: `${(b.w / page.width) * 100}%`,
      height: `${(b.h / page.height) * 100}%`,
      z: b.z ?? 1,
    };
  }
  const g = region.grid;
  if (g) {
    return {
      left: `${(g.x / 12) * 100}%`,
      top: `${(g.y / 52) * 100}%`,
      width: `${(g.w / 12) * 100}%`,
      height: `${(g.h / 52) * 100}%`,
      z: g.z ?? 1,
    };
  }
  if (region.position) {
    return {
      left: `${region.position.x}%`,
      top: `${region.position.y}%`,
      width: `${region.position.width}%`,
      height: `${region.position.height}%`,
      z: 1,
    };
  }
  return null;
}

/** Estilo visual por tipo de bloque (color de fondo + borde + label). */
function blockStyle(
  type: ThemeBlockType,
  palette: Theme['palette'],
): { bg: string; border: string; label: string; textColor: string } {
  switch (type) {
    case 'content_slot':
      return {
        bg: 'transparent',
        border: `1.5px dashed ${palette.primary}`,
        label: 'Contenido',
        textColor: palette.text ?? '#1a1a1a',
      };
    case 'text':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px solid ${palette.secondary}`, label: 'Texto', textColor: palette.text };
    case 'image':
      return { bg: 'rgba(0,0,0,0.08)', border: `1px solid ${palette.secondary}`, label: 'Imagen', textColor: palette.text };
    case 'page_number':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px dashed ${palette.secondary}`, label: '# Pág.', textColor: palette.text };
    case 'date':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px dashed ${palette.secondary}`, label: 'Fecha', textColor: palette.text };
    default:
      return { bg: 'rgba(0,0,0,0.06)', border: `1px solid ${palette.secondary}`, label: type, textColor: palette.text };
  }
}

/**
 * Previsualización en miniatura del theme como página A4. Reproduce:
 *   - Color de fondo + tipografía base del theme.
 *   - Cada region del layout posicionada según su grid 12×52.
 *   - Slot de contenido con líneas simuladas para indicar dónde irá el documento.
 *
 * Pensada para el selector de theme en el wizard de Template — el usuario
 * ve a escala el aspecto que tendrá el documento al exportarse a PDF.
 */
export function ThemeA4Preview({ theme, className }: ThemeA4PreviewProps) {
  if (!theme) {
    return (
      <div
        className={[
          'flex aspect-[210/297] w-full max-w-[200px] items-center justify-center rounded border border-dashed border-ui-border bg-ui-body/40 text-xs text-text-muted',
          className ?? '',
        ].join(' ')}
      >
        Sin theme asignado
      </div>
    );
  }

  const { palette, typography, layout } = theme;
  const page = pageDimsMm(layout?.page?.size);
  const regions = (layout?.regions ?? [])
    .map((r) => ({ region: r, rect: regionRect(r, page) }))
    .filter((x): x is { region: ThemeLayoutRegion; rect: NonNullable<ReturnType<typeof regionRect>> } => x.rect !== null)
    // Pintar primero las capas bajas (z menor) para que las altas queden encima.
    .sort((a, b) => a.rect.z - b.rect.z);

  return (
    <div
      className={[
        'flex w-full flex-col items-center gap-2',
        className ?? '',
      ].join(' ')}
      aria-label={`Vista previa A4 del theme ${theme.name}`}
    >
      {/* Hoja A4 con sombra y aspect ratio real 210:297 */}
      <div
        className="relative aspect-[210/297] w-full max-w-[200px] overflow-hidden rounded-sm border border-ui-border shadow-md"
        style={{
          backgroundColor: palette.background ?? '#ffffff',
          color: palette.text ?? '#1a1a1a',
          fontFamily: typography?.body_font ?? undefined,
        }}
      >
        {regions.map(({ region, rect }) => {
          const style = blockStyle(region.type, palette);
          const p = region.props ?? {};
          const imageUrl = (p.srcUrl as string) || null;
          const isImage = region.type === 'image' && imageUrl;
          return (
            <div
              key={region.id}
              className="absolute flex items-center justify-center overflow-hidden"
              style={{
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height,
                zIndex: rect.z,
                // Imagen a sangre: sin recuadro ni padding para que pueda usarse
                // como fondo de página completa, igual que en el PDF.
                backgroundColor: isImage ? undefined : style.bg,
                border: isImage ? undefined : style.border,
                color: style.textColor,
                fontSize: '6px',
                lineHeight: 1.1,
                padding: isImage ? 0 : '2px',
              }}
              title={`${style.label} (${region.type})`}
            >
              {isImage ? (
                <img
                  src={imageUrl}
                  alt=""
                  style={{
                    width: '100%',
                    height: '100%',
                    objectFit: ((p.objectFit as string) ?? 'contain') as React.CSSProperties['objectFit'],
                  }}
                />
              ) : region.type === 'content_slot' ? (
                <ContentSlotPreview palette={palette} headingFont={typography?.heading_font ?? undefined} />
              ) : (
                <span
                  className="truncate font-semibold uppercase tracking-wide"
                  style={{ fontSize: '5.5px' }}
                >
                  {style.label}
                </span>
              )}
            </div>
          );
        })}

        {regions.length === 0 && (
          <div className="absolute inset-0 flex items-center justify-center text-[7px] text-text-muted" /* scale-down mock */>
            Layout vacío
          </div>
        )}
      </div>

      {/* Pie con nombre + paleta */}
      <div className="flex w-full max-w-[200px] items-center gap-2">
        <p
          className="min-w-0 flex-1 truncate text-2xs font-semibold text-text-primary dark:text-text-dark-primary"
          title={theme.name}
          style={typography?.heading_font ? { fontFamily: typography.heading_font } : undefined}
        >
          {theme.name}
        </p>
        <div className="flex gap-0.5">
          {[palette.primary, palette.secondary, palette.accent, palette.text]
            .filter((c): c is string => typeof c === 'string' && c.length > 0)
            .map((c, i) => (
              <span
                key={i}
                title={c}
                className="inline-block h-2.5 w-2.5 rounded-full border border-black/10"
                style={{ backgroundColor: c }}
              />
            ))}
        </div>
      </div>
    </div>
  );
}

/** Representa con líneas finas el contenido textual que iría dentro del slot. */
function ContentSlotPreview({
  palette,
  headingFont,
}: {
  palette: Theme['palette'];
  headingFont?: string;
}) {
  return (
    <div className="flex h-full w-full flex-col gap-[2px] p-[3px]">
      <div
        className="h-[5px] w-[60%] rounded-sm"
        style={{ backgroundColor: palette.primary, fontFamily: headingFont }}
      />
      <div className="h-[2px] w-[95%] rounded-sm" style={{ backgroundColor: palette.text, opacity: 0.7 }} />
      <div className="h-[2px] w-[90%] rounded-sm" style={{ backgroundColor: palette.text, opacity: 0.6 }} />
      <div className="h-[2px] w-[80%] rounded-sm" style={{ backgroundColor: palette.text, opacity: 0.6 }} />
      <div className="mt-[2px] h-[3px] w-[40%] rounded-sm" style={{ backgroundColor: palette.accent ?? palette.secondary }} />
      <div className="h-[2px] w-[85%] rounded-sm" style={{ backgroundColor: palette.text, opacity: 0.5 }} />
      <div className="h-[2px] w-[75%] rounded-sm" style={{ backgroundColor: palette.text, opacity: 0.5 }} />
    </div>
  );
}
