import { FieldLabel, Select, TextInput } from '@maya/shared-ui-react';
import { useThemeFonts } from '../hooks/useThemeFonts';
import { ThemeAssetsSection } from './ThemeAssetsSection';
import type {
  Theme,
  ThemeAccessibility,
  ThemeFontsCatalog,
  ThemePalette,
  ThemeTypography,
} from '../../../types/themes';

export interface ThemeIdentityValue {
  name: string;
  description: string;
  status?: 'draft' | 'published' | 'archived';
  palette: ThemePalette;
  typography: ThemeTypography;
  accessibility: ThemeAccessibility;
}

interface ThemeWizardStepIdentityProps {
  value: ThemeIdentityValue;
  onChange: (next: ThemeIdentityValue) => void;
  showStatus?: boolean;
  /** Si hay theme persistido, se muestra el panel de assets. */
  theme?: Theme | null;
  onAssetsUploaded?: (theme: Theme) => void;
}

const LANG_OPTIONS = [
  { value: 'es', label: 'Español' },
  { value: 'ca', label: 'Catalán/Valenciano' },
  { value: 'en', label: 'Inglés' },
  { value: 'fr', label: 'Francés' },
];

const STATUS_OPTIONS = [
  { value: 'draft', label: 'Borrador' },
  { value: 'published', label: 'Publicado' },
  { value: 'archived', label: 'Archivado' },
];

/**
 * Paso 1 del wizard: información estática (general, paleta, tipografía,
 * accesibilidad, assets). Sin layout drag-and-drop, ese es el paso 2.
 *
 * Es un componente controlado — el wizard mantiene el estado y persiste en
 * cada transición de paso.
 */
export function ThemeWizardStepIdentity({
  value,
  onChange,
  showStatus,
  theme,
  onAssetsUploaded,
}: ThemeWizardStepIdentityProps) {
  const { catalog: fonts } = useThemeFonts();

  const set = (patch: Partial<ThemeIdentityValue>) => onChange({ ...value, ...patch });
  const setPalette = (patch: Partial<ThemePalette>) =>
    onChange({ ...value, palette: { ...value.palette, ...patch } });
  const setTypography = (patch: Partial<ThemeTypography>) =>
    onChange({ ...value, typography: { ...value.typography, ...patch } });
  const setAccessibility = (patch: Partial<ThemeAccessibility>) =>
    onChange({ ...value, accessibility: { ...value.accessibility, ...patch } });

  return (
    <div className="flex-1 overflow-y-auto px-6 py-4">
      <div className="mx-auto max-w-4xl space-y-8 pb-8">
        <section className="space-y-3">
          <h2 className="text-base font-semibold">Información general</h2>

          <div>
            <FieldLabel required htmlFor="theme-name">
              Nombre
            </FieldLabel>
            <TextInput
              id="theme-name"
              value={value.name}
              onChange={(e) => set({ name: e.target.value })}
              required
              maxLength={255}
              placeholder="Ej.: Tema corporativo CEEDCV"
            />
          </div>

          <div>
            <FieldLabel htmlFor="theme-description">Descripción</FieldLabel>
            <TextInput
              id="theme-description"
              value={value.description}
              onChange={(e) => set({ description: e.target.value })}
              maxLength={2000}
              placeholder="Para qué tipo de documentos usar este theme"
            />
          </div>

          {showStatus && (
            <div>
              <FieldLabel htmlFor="theme-status">Estado</FieldLabel>
              <Select
                id="theme-status"
                value={value.status ?? 'draft'}
                onChange={(e) =>
                  set({ status: e.target.value as 'draft' | 'published' | 'archived' })
                }
              >
                {STATUS_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
            </div>
          )}
        </section>

        <section className="space-y-3">
          <h2 className="text-base font-semibold">Paleta de colores</h2>
          <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
            <ColorField label="Primario" value={value.palette.primary} onChange={(c) => setPalette({ primary: c })} />
            <ColorField label="Secundario" value={value.palette.secondary} onChange={(c) => setPalette({ secondary: c })} />
            <ColorField label="Acento" value={value.palette.accent ?? '#f59e0b'} onChange={(c) => setPalette({ accent: c })} />
            <ColorField label="Texto" value={value.palette.text} onChange={(c) => setPalette({ text: c })} />
            <ColorField label="Fondo" value={value.palette.background} onChange={(c) => setPalette({ background: c })} />
          </div>
        </section>

        <section className="space-y-3">
          <h2 className="text-base font-semibold">Tipografía</h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FieldLabel htmlFor="theme-heading-font">Fuente de encabezados</FieldLabel>
              <Select
                id="theme-heading-font"
                value={value.typography.heading_font}
                onChange={(e) => setTypography({ heading_font: e.target.value })}
              >
                {renderFontOptions(fonts, value.typography.heading_font)}
              </Select>
            </div>
            <div>
              <FieldLabel htmlFor="theme-body-font">Fuente de cuerpo</FieldLabel>
              <Select
                id="theme-body-font"
                value={value.typography.body_font}
                onChange={(e) => setTypography({ body_font: e.target.value })}
              >
                {renderFontOptions(fonts, value.typography.body_font)}
              </Select>
            </div>
            <div>
              <FieldLabel htmlFor="theme-base-size">Tamaño base (pt)</FieldLabel>
              <TextInput
                id="theme-base-size"
                type="number"
                min={6}
                max={24}
                value={String(value.typography.base_size_pt)}
                onChange={(e) =>
                  setTypography({ base_size_pt: Number.parseInt(e.target.value, 10) || 11 })
                }
              />
            </div>
            <div>
              <FieldLabel htmlFor="theme-line-height">Altura de línea</FieldLabel>
              <TextInput
                id="theme-line-height"
                type="number"
                step="0.1"
                min={1}
                max={3}
                value={String(value.typography.line_height)}
                onChange={(e) =>
                  setTypography({ line_height: Number.parseFloat(e.target.value) || 1.5 })
                }
              />
            </div>
          </div>
        </section>

        <section className="space-y-3">
          <h2 className="text-base font-semibold">Accesibilidad (PDF/UA)</h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FieldLabel htmlFor="theme-language">Idioma</FieldLabel>
              <Select
                id="theme-language"
                value={value.accessibility.language}
                onChange={(e) => setAccessibility({ language: e.target.value })}
              >
                {LANG_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <FieldLabel htmlFor="theme-author">Autor (metadatos PDF)</FieldLabel>
              <TextInput
                id="theme-author"
                value={value.accessibility.author}
                onChange={(e) => setAccessibility({ author: e.target.value })}
              />
            </div>
          </div>
        </section>

        {theme && onAssetsUploaded ? (
          <ThemeAssetsSection theme={theme} onUploaded={onAssetsUploaded} />
        ) : (
          <section className="rounded border border-dashed border-gray-300 p-4 text-sm text-text-muted">
            <strong>Assets visuales (logo, fondo, marca de agua):</strong> guarda primero el theme
            para poder subir imágenes. Una vez creado, este panel mostrará las tarjetas de subida.
          </section>
        )}
      </div>
    </div>
  );
}

function renderFontOptions(catalog: ThemeFontsCatalog, currentValue: string) {
  const allValues = new Set([
    ...catalog.sans.map((f) => f.value),
    ...catalog.serif.map((f) => f.value),
    ...catalog.mono.map((f) => f.value),
  ]);

  return (
    <>
      <optgroup label="Sans-serif">
        {catalog.sans.map((f) => (
          <option key={f.value} value={f.value} title={f.note}>
            {f.label}
          </option>
        ))}
      </optgroup>
      <optgroup label="Serif">
        {catalog.serif.map((f) => (
          <option key={f.value} value={f.value} title={f.note}>
            {f.label}
          </option>
        ))}
      </optgroup>
      <optgroup label="Monoespacio">
        {catalog.mono.map((f) => (
          <option key={f.value} value={f.value} title={f.note}>
            {f.label}
          </option>
        ))}
      </optgroup>
      {currentValue && !allValues.has(currentValue) && (
        <optgroup label="Personalizada (legacy)">
          <option value={currentValue}>{currentValue}</option>
        </optgroup>
      )}
    </>
  );
}

interface ColorFieldProps {
  label: string;
  value: string;
  onChange: (color: string) => void;
}

function ColorField({ label, value, onChange }: ColorFieldProps) {
  return (
    <div className="flex flex-col">
      <FieldLabel>{label}</FieldLabel>
      <div className="mt-1 flex items-center gap-2">
        <input
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 w-12 cursor-pointer rounded border border-gray-300"
          aria-label={`${label} (selector de color)`}
        />
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="flex-1 rounded border border-gray-300 px-2 py-1 text-sm font-mono"
          pattern="^#[0-9a-fA-F]{3,8}$"
          maxLength={9}
          aria-label={`${label} (valor hexadecimal)`}
        />
      </div>
    </div>
  );
}
