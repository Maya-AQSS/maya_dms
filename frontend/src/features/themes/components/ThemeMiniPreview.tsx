import type { Theme } from '../../../types/themes';
import type { ThemeMini } from '../../../types/templates';

interface ThemeMiniPreviewProps {
  /** Theme completo o el mini-payload del backend. `null` muestra el placeholder. */
  theme: Theme | ThemeMini | null;
  /** `compact` (defecto) muestra paleta + logo. `full` añade muestra de tipografía. */
  variant?: 'compact' | 'full';
  className?: string;
}

interface NormalizedTheme {
  id: string;
  name: string;
  swatches: string[];
  headingFont: string | null;
  logoUrl: string | null;
}

function normalize(theme: Theme | ThemeMini): NormalizedTheme {
  const palette = theme.palette as Record<string, string | null | undefined>;
  const typo = (theme as Theme).typography ?? (theme as ThemeMini).typography;
  const assets = (theme as Theme).assets ?? (theme as ThemeMini).assets;

  const swatches = [
    palette?.primary,
    palette?.secondary,
    palette?.accent,
    palette?.background,
    palette?.text,
  ].filter((c): c is string => typeof c === 'string' && c.length > 0);

  const logoUrl = assets?.logo_path ?? null;

  return {
    id: theme.id,
    name: theme.name,
    swatches,
    headingFont: typo?.heading_font ?? null,
    logoUrl,
  };
}

/**
 * Tarjeta compacta con la identidad visual de un theme. Se usa en el
 * selector del wizard de Template, en la lista de Themes y en el preview
 * del template. Sólo presentación — no fetchea nada por su cuenta.
 */
export function ThemeMiniPreview({ theme, variant = 'compact', className }: ThemeMiniPreviewProps) {
  if (!theme) {
    return (
      <div
        className={[
          'flex h-20 w-full items-center justify-center rounded border border-dashed border-ui-border bg-ui-body/40 text-xs text-text-muted',
          className ?? '',
        ].join(' ')}
      >
        Sin theme asignado
      </div>
    );
  }

  const norm = normalize(theme);

  return (
    <div
      className={[
        'flex items-center gap-3 rounded border border-ui-border bg-white p-2 dark:border-ui-dark-border dark:bg-ui-dark-card',
        className ?? '',
      ].join(' ')}
      aria-label={`Vista previa del theme ${norm.name}`}
    >
      {norm.logoUrl ? (
        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-ui-border bg-white p-1">
          <img
            src={norm.logoUrl}
            alt=""
            className="max-h-full max-w-full object-contain"
          />
        </div>
      ) : (
        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded border border-dashed border-ui-border text-2xs text-text-muted">
          sin logo
        </div>
      )}

      <div className="min-w-0 flex-1">
        <p
          className="truncate text-sm font-semibold text-text-primary dark:text-text-dark-primary"
          title={norm.name}
          style={norm.headingFont ? { fontFamily: norm.headingFont } : undefined}
        >
          {norm.name}
        </p>
        <div className="mt-1 flex gap-1">
          {norm.swatches.map((color, idx) => (
            <span
              key={`${norm.id}-${idx}`}
              title={color}
              style={{ backgroundColor: color }}
              className="inline-block h-3 w-3 rounded-full border border-black/10"
            />
          ))}
        </div>
        {variant === 'full' && norm.headingFont && (
          <p
            className="mt-1 truncate text-xs text-text-muted"
            style={{ fontFamily: norm.headingFont }}
          >
            Aa — {norm.headingFont.split(',')[0]}
          </p>
        )}
      </div>
    </div>
  );
}
