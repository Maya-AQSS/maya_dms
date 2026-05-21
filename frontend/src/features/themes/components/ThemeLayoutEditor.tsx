import { useCallback, useMemo, useState } from 'react';
import { Puck, type Config, type Data } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { Button } from '@maya/shared-ui-react';
import type { Theme, ThemeLayoutRegion } from '../../../types/themes';

interface ThemeLayoutEditorProps {
  theme: Theme;
  /** Persiste el layout. Recibe el nuevo array de regions serializable. */
  onSave: (regions: ThemeLayoutRegion[]) => Promise<void>;
  /** Cierra el editor sin guardar (vuelve a /themes/:id/edit). */
  onClose: () => void;
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
};

const puckConfig: Config<PuckComponentProps> = {
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
  },
  // Cada zona es una región de la @page CSS de WeasyPrint.
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

export function ThemeLayoutEditor({ theme, onSave, onClose }: ThemeLayoutEditorProps) {
  const initialData = useMemo(() => regionsToPuckData(theme.layout.regions ?? []), [theme]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

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
    <div className="flex h-[calc(100vh-8rem)] flex-col">
      <div className="flex items-center justify-between border-b border-ui-border bg-ui-bg px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-bg">
        <div className="text-sm">
          <strong>Editor de layout</strong> — {theme.name}
          <p className="text-xs text-text-muted">
            Disponible: {Object.values(SLOT_LABELS).join(' · ')}. La distribución
            visual se aplicará en la generación de PDF/UA.
          </p>
        </div>
        <div className="flex gap-2">
          <Button type="button" variant="ghost" size="sm" onClick={onClose} disabled={saving}>
            Cerrar
          </Button>
        </div>
      </div>

      {error && (
        <div className="border-b border-red-300 bg-red-50 px-4 py-2 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="flex-1 overflow-hidden">
        <Puck<PuckComponentProps>
          config={puckConfig}
          data={initialData}
          onPublish={(d) => void handlePublish(d)}
        />
      </div>
    </div>
  );
}
