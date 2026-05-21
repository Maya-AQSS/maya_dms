import { useCallback, useMemo, useState } from 'react';
import { Puck, type Config, type Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import './theme-puck.css';
import { Button } from '@maya/shared-ui-react';
import { themeAssetUrl } from '../../../api/themes';
import type { Theme, ThemeLayoutRegion } from '../../../types/themes';

interface ThemeLayoutEditorProps {
  theme: Theme;
  /** Persiste el layout. Recibe el nuevo array de regions serializable. */
  onSave: (regions: ThemeLayoutRegion[]) => Promise<void>;
  /** Botón de salida opcional (vuelve al paso anterior, p.ej. en wizard). */
  onClose?: () => void;
  /** Si está embebido en un wizard, no renderizamos la barra de cabecera local. */
  embedded?: boolean;
}

/**
 * Modelo simple del slot dentro de una @page de WeasyPrint.
 * Limitamos las zonas a las 4 esquinas de @page + un slot de watermark + el
 * cuerpo (content_slot). El backend Blade traduce cada slot a CSS Paged Media.
 */
type SlotName = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'watermark';

type PuckComponentProps = {
  TextBlock: { text: string; size: number; color: string };
  LogoBlock: { caption: string; height: number };
  PageNumberBlock: { format: 'page' | 'page-of-pages' };
  DateBlock: { format: 'short' | 'long' };
  TwoColumns: { gap: number; left: unknown; right: unknown };
  ThreeColumns: { gap: number; left: unknown; middle: unknown; right: unknown };
  Row: { gap: number; align: 'start' | 'center' | 'end'; items: unknown };
};

/**
 * Config de Puck. Categorías para que en el panel lateral los bloques
 * aparezcan agrupados (contenido vs. estructura).
 *
 * Los componentes `TwoColumns` / `ThreeColumns` / `Row` usan el campo `slot`
 * — nuevo en Puck 0.21 — para crear zonas anidadas donde el usuario puede
 * arrastrar otros bloques.
 */
const puckConfig: Config<PuckComponentProps> = {
  categories: {
    content: { title: 'Contenido', components: ['TextBlock', 'LogoBlock', 'PageNumberBlock', 'DateBlock'] },
    layout: { title: 'Estructura', components: ['Row', 'TwoColumns', 'ThreeColumns'] },
  },
  components: {
    TextBlock: {
      label: 'Texto',
      fields: {
        text: { type: 'text', label: 'Contenido' },
        size: { type: 'number', label: 'Tamaño (pt)', min: 6, max: 24 },
        color: { type: 'text', label: 'Color (hex)' },
      },
      defaultProps: { text: 'Texto', size: 9, color: '#666666' },
      render: ({ text, size, color }) => (
        <span style={{ fontSize: `${size}pt`, color }}>{text}</span>
      ),
    },
    LogoBlock: {
      label: 'Logo',
      fields: {
        caption: { type: 'text', label: 'Texto alternativo' },
        height: { type: 'number', label: 'Altura (cm)', min: 1, max: 5 },
      },
      defaultProps: { caption: 'Logo', height: 2 },
      render: ({ caption, height }) => (
        <div
          style={{
            border: '1px dashed #999',
            padding: '0.2cm',
            height: `${height}cm`,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '8pt',
            color: '#666',
          }}
        >
          {caption} (logo del theme)
        </div>
      ),
    },
    PageNumberBlock: {
      label: 'Nº de página',
      fields: {
        format: {
          type: 'select',
          label: 'Formato',
          options: [
            { label: 'Página N', value: 'page' },
            { label: 'Página N de M', value: 'page-of-pages' },
          ],
        },
      },
      defaultProps: { format: 'page-of-pages' },
      render: ({ format }) => (
        <span style={{ fontSize: '9pt', color: '#666' }}>
          {format === 'page-of-pages' ? 'Página N de M' : 'Página N'}
        </span>
      ),
    },
    DateBlock: {
      label: 'Fecha',
      fields: {
        format: {
          type: 'select',
          label: 'Formato',
          options: [
            { label: 'Corto (dd/mm/aaaa)', value: 'short' },
            { label: 'Largo (1 de enero de 2026)', value: 'long' },
          ],
        },
      },
      defaultProps: { format: 'short' },
      render: ({ format }) => (
        <span style={{ fontSize: '9pt', color: '#666' }}>
          {format === 'long' ? '1 de enero de 2026' : '01/01/2026'}
        </span>
      ),
    },
    Row: {
      label: 'Fila (apila horizontal)',
      fields: {
        gap: { type: 'number', label: 'Espacio (px)', min: 0, max: 64 },
        align: {
          type: 'select',
          label: 'Alineación',
          options: [
            { label: 'Inicio', value: 'start' },
            { label: 'Centro', value: 'center' },
            { label: 'Final', value: 'end' },
          ],
        },
        items: { type: 'slot' },
      },
      defaultProps: { gap: 8, align: 'start', items: [] },
      render: ({ gap, align, items: Items }) => {
        // Items es un SlotComponent que renderiza la dropzone. Se llama como JSX.
        const ItemsSlot = Items as React.ComponentType<{ style?: React.CSSProperties; className?: string }>;
        return (
          <ItemsSlot
            className="theme-puck-row"
            style={{
              display: 'flex',
              flexDirection: 'row',
              gap: `${gap}px`,
              alignItems: align === 'center' ? 'center' : align === 'end' ? 'flex-end' : 'flex-start',
              minHeight: '1.5cm',
            }}
          />
        );
      },
    },
    TwoColumns: {
      label: 'Dos columnas',
      fields: {
        gap: { type: 'number', label: 'Espacio entre columnas (px)', min: 0, max: 64 },
        left: { type: 'slot' },
        right: { type: 'slot' },
      },
      defaultProps: { gap: 16, left: [], right: [] },
      render: ({ gap, left: Left, right: Right }) => {
        const L = Left as React.ComponentType<{ style?: React.CSSProperties }>;
        const R = Right as React.ComponentType<{ style?: React.CSSProperties }>;
        return (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: `${gap}px`, minHeight: '1.5cm' }}>
            <L style={{ minHeight: '1cm' }} />
            <R style={{ minHeight: '1cm' }} />
          </div>
        );
      },
    },
    ThreeColumns: {
      label: 'Tres columnas',
      fields: {
        gap: { type: 'number', label: 'Espacio entre columnas (px)', min: 0, max: 64 },
        left: { type: 'slot' },
        middle: { type: 'slot' },
        right: { type: 'slot' },
      },
      defaultProps: { gap: 16, left: [], middle: [], right: [] },
      render: ({ gap, left: Left, middle: Middle, right: Right }) => {
        const L = Left as React.ComponentType<{ style?: React.CSSProperties }>;
        const M = Middle as React.ComponentType<{ style?: React.CSSProperties }>;
        const R = Right as React.ComponentType<{ style?: React.CSSProperties }>;
        return (
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: '1fr 1fr 1fr',
              gap: `${gap}px`,
              minHeight: '1.5cm',
            }}
          >
            <L style={{ minHeight: '1cm' }} />
            <M style={{ minHeight: '1cm' }} />
            <R style={{ minHeight: '1cm' }} />
          </div>
        );
      },
    },
  },
  root: {
    fields: {},
    defaultProps: {},
  },
};

const SLOT_LABELS: Record<SlotName, string> = {
  'top-left': 'Cabecera izquierda',
  'top-right': 'Cabecera derecha',
  'bottom-left': 'Pie izquierdo',
  'bottom-right': 'Pie derecho',
  watermark: 'Marca de agua',
};

/** Convierte un array de ThemeLayoutRegion al modelo de Data de Puck. */
function regionsToPuckData(regions: ThemeLayoutRegion[]): Data {
  // Cada region.puck es un Data ya serializado (output de onPublish anterior).
  // Buscamos la primera region marcada como "_root" y la usamos como Data global.
  for (const r of regions) {
    if (r.id === '_root' && r.puck) {
      return r.puck as Data;
    }
  }
  return { content: [], root: { props: {} }, zones: {} };
}

/** Inversa: empaqueta el Data de Puck en una region "_root". */
function puckDataToRegions(data: Data, existing: ThemeLayoutRegion[]): ThemeLayoutRegion[] {
  const next = existing.filter((r) => r.id !== '_root');
  next.push({
    id: '_root',
    type: 'content_slot',
    puck: data as unknown,
  });
  return next;
}

/**
 * Editor visual del layout del theme. Renderiza el shell de Puck a altura
 * completa del contenedor padre (el wizard ya lo limita), con la imagen de
 * fondo del theme (si la hay) detrás del canvas para previsualizar cómo
 * quedará el documento generado.
 */
export function ThemeLayoutEditor({ theme, onSave, onClose, embedded }: ThemeLayoutEditorProps) {
  const initialData = useMemo(() => regionsToPuckData(theme.layout.regions ?? []), [theme]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const backgroundUrl = theme.assets.background_image_path
    ? `${themeAssetUrl(theme.id, 'background')}?t=${encodeURIComponent(theme.updated_at)}`
    : null;

  const handlePublish = useCallback(
    async (data: Data) => {
      setSaving(true);
      setError(null);
      try {
        const newRegions = puckDataToRegions(data, theme.layout.regions ?? []);
        await onSave(newRegions);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Error guardando el layout');
      } finally {
        setSaving(false);
      }
    },
    [onSave, theme],
  );

  return (
    <div className="flex h-full min-h-0 flex-col">
      {!embedded && (
        <div className="flex shrink-0 items-center justify-between border-b border-ui-border bg-ui-bg px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-bg">
          <div className="text-sm">
            <strong>Editor de layout</strong> — {theme.name}
            <p className="text-xs text-text-muted">
              Disponible: {Object.values(SLOT_LABELS).join(' · ')}. La distribución
              visual se aplicará en la generación de PDF/UA.
            </p>
          </div>
          {onClose && (
            <Button type="button" variant="ghost" size="sm" onClick={onClose} disabled={saving}>
              Cerrar
            </Button>
          )}
        </div>
      )}

      {error && (
        <div className="shrink-0 border-b border-red-300 bg-red-50 px-4 py-2 text-sm text-red-700">
          {error}
        </div>
      )}

      {/*
        El wrapper marca al hijo Puck su altura efectiva. Las clases
        `theme-puck-shell` aplican (vía CSS global) la imagen de fondo del
        theme sobre el canvas y permiten que las barras laterales hagan
        scroll de forma independiente del canvas (overflow-y: auto interno).
      */}
      <div
        className="theme-puck-shell flex-1 min-h-0 overflow-hidden"
        style={
          backgroundUrl
            ? { ['--theme-bg-url' as string]: `url("${backgroundUrl}")` }
            : undefined
        }
      >
        <Puck<typeof puckConfig>
          config={puckConfig}
          data={initialData}
          onPublish={(d) => void handlePublish(d)}
        />
      </div>
    </div>
  );
}
