import { useMemo } from 'react';
import { themeAssetUrl } from '../../../api/themes';
import type { Theme, ThemeLayoutRegion, ThemeBlockType } from '../../../types/themes';

interface ThemeA4PreviewProps {
  /** Theme con layout completo. `null` muestra placeholder. */
  theme: Theme | null;
  className?: string;
}

const GRID_COLS = 12;
const GRID_ROWS = 52;

/** Devuelve el rectángulo en porcentajes para una region en la rejilla 12×52. */
function regionRect(region: ThemeLayoutRegion): {
  left: string;
  top: string;
  width: string;
  height: string;
  z: number;
} | null {
  const g = region.grid;
  if (!g) {
    // Fallback al modelo legacy en %.
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
  return {
    left: `${(g.x / GRID_COLS) * 100}%`,
    top: `${(g.y / GRID_ROWS) * 100}%`,
    width: `${(g.w / GRID_COLS) * 100}%`,
    height: `${(g.h / GRID_ROWS) * 100}%`,
    z: g.z ?? 1,
  };
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
    case 'logo':
      return { bg: 'rgba(255,255,255,0.6)', border: `1px solid ${palette.secondary}`, label: 'Logo', textColor: palette.text };
    case 'header':
      return { bg: palette.primary, border: 'none', label: 'Header', textColor: '#ffffff' };
    case 'footer':
      return { bg: palette.secondary, border: 'none', label: 'Footer', textColor: '#ffffff' };
    case 'sidebar':
      return { bg: palette.accent ?? palette.secondary, border: 'none', label: 'Sidebar', textColor: '#ffffff' };
    case 'text':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px solid ${palette.secondary}`, label: 'Texto', textColor: palette.text };
    case 'image':
      return { bg: 'rgba(0,0,0,0.08)', border: `1px solid ${palette.secondary}`, label: 'Imagen', textColor: palette.text };
    case 'page_number':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px dashed ${palette.secondary}`, label: '# Pág.', textColor: palette.text };
    case 'date':
      return { bg: 'rgba(0,0,0,0.04)', border: `1px dashed ${palette.secondary}`, label: 'Fecha', textColor: palette.text };
    case 'watermark':
      return { bg: 'rgba(0,0,0,0.05)', border: `1px dotted ${palette.secondary}`, label: 'Marca agua', textColor: palette.text };
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
  const logoUrl = useMemo(() => {
    if (!theme?.assets.logo_path) return null;
    return `${themeAssetUrl(theme.id, 'logo')}?t=${encodeURIComponent(theme.updated_at)}`;
  }, [theme]);

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
  const regions = (layout?.regions ?? [])
    .map((r) => ({ region: r, rect: regionRect(r) }))
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
          const isLogo = region.type === 'logo' && logoUrl;
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
                backgroundColor: style.bg,
                border: style.border,
                color: style.textColor,
                fontSize: '6px',
                lineHeight: 1.1,
                padding: '2px',
              }}
              title={`${style.label} (${region.type})`}
            >
              {isLogo ? (
                <img
                  src={logoUrl}
                  alt=""
                  className="max-h-full max-w-full object-contain"
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
          <div className="absolute inset-0 flex items-center justify-center text-[7px] text-text-muted">
            Layout vacío
          </div>
        )}
      </div>

      {/* Pie con nombre + paleta */}
      <div className="flex w-full max-w-[200px] items-center gap-2">
        <p
          className="min-w-0 flex-1 truncate text-[11px] font-semibold text-text-primary dark:text-text-dark-primary"
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
