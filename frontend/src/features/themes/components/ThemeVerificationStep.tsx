import { useState } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { PagedThemedPreview } from '../../documents/components/PagedThemedPreview';
import { buildApiUrl, getBearerToken } from '../../../api/http';
import type { Theme } from '../../../types/themes';

interface ThemeVerificationStepProps {
  theme: Theme;
  /** Publica el theme (draft → published). */
  onPublish: () => Promise<void>;
  /** Archiva el theme (published → archived). */
  onArchive: () => Promise<void>;
  /** Vuelve al paso de Layout. */
  onBack: () => void;
}

/**
 * Paso 3 del wizard — Verificación. Muestra cómo quedará el theme:
 *  - Vista "previsualizar": HTML themed paginado (paged.js) con lorem ipsum en
 *    el área de contenido si el layout tiene un bloque content_slot.
 *  - Vista PDF: genera bajo demanda un PDF/UA real de muestra con WeasyPrint.
 * Desde aquí se publica (gating: el estado no avanza sin pasar por aquí).
 */
export function ThemeVerificationStep({ theme, onPublish, onArchive, onBack }: ThemeVerificationStepProps) {
  const [pdfLoading, setPdfLoading] = useState(false);
  const [pdfError, setPdfError] = useState<string | null>(null);

  const openSamplePdf = async () => {
    setPdfLoading(true);
    setPdfError(null);
    try {
      const token = await getBearerToken();
      const response = await fetch(buildApiUrl(`themes/${theme.id}/sample-pdf`), {
        headers: token ? { Authorization: `Bearer ${token}` } : undefined,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank', 'noopener');
      // Liberamos la URL tras un margen para que la nueva pestaña la cargue.
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
      setPdfError(e instanceof Error ? e.message : 'No se pudo generar el PDF de muestra.');
    } finally {
      setPdfLoading(false);
    }
  };

  return (
    <div className="flex flex-1 min-h-0 gap-6 p-6">
      {/* Previsualización paginada (paged.js) */}
      <div className="flex flex-1 min-h-0 flex-col rounded border border-ui-border bg-ui-body">
        <div className="border-b border-ui-border px-4 py-2 text-sm font-semibold">
          Previsualización
        </div>
        <div className="flex-1 min-h-0 overflow-auto">
          <PagedThemedPreview kind="theme" id={theme.id} />
        </div>
      </div>

      {/* Resumen y acciones */}
      <aside className="w-72 shrink-0 space-y-4">
        <div>
          <h3 className="text-base font-semibold">Verificación</h3>
          <p className="mt-1 text-xs text-text-muted">
            Comprueba cómo se aplican colores, fuentes e imágenes. La vista PDF genera
            un PDF/UA real de muestra con lorem ipsum.
          </p>
        </div>

        <dl className="space-y-1 rounded bg-ui-body p-3 text-sm">
          <div className="flex justify-between">
            <dt className="text-text-muted">Nombre</dt>
            <dd className="font-medium">{theme.name}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-text-muted">Estado</dt>
            <dd className="font-medium">{theme.status}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-text-muted">Bloques</dt>
            <dd className="font-medium">{theme.layout?.regions?.length ?? 0}</dd>
          </div>
        </dl>

        <div className="space-y-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            loading={pdfLoading}
            onClick={() => void openSamplePdf()}
            className="w-full"
          >
            Generar PDF de muestra
          </Button>
          {pdfError && <p className="text-xs text-danger-dark">⚠ {pdfError}</p>}

          {theme.status === 'draft' && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => void onPublish()}
              className="w-full text-xs font-black uppercase tracking-widest rounded-full shadow-sm"
            >
              Publicar
            </Button>
          )}
          {theme.status === 'published' && (
            <Button type="button" variant="outline" size="sm" onClick={() => void onArchive()} className="w-full">
              Archivar
            </Button>
          )}

          <Button type="button" variant="ghost" size="sm" onClick={onBack} className="w-full">
            ← Volver a Layout
          </Button>
        </div>
      </aside>
    </div>
  );
}
