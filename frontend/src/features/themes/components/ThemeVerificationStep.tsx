import { useState } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { PagedThemedPreview } from '../../documents/components/PagedThemedPreview';
import { buildApiUrl, getBearerToken } from '../../../api/http';
import type { Theme } from '../../../types/themes';

interface ThemeVerificationStepProps {
  theme: Theme;
}

/**
 * Paso 3 del wizard — Verificación. Muestra cómo quedará el theme:
 *  - Vista "previsualizar": HTML themed paginado (paged.js) con lorem ipsum en
 *    el área de contenido si el layout tiene un bloque content_slot.
 *  - Vista PDF: genera bajo demanda un PDF/UA real de muestra con WeasyPrint.
 *
 * Publicar / Archivar viven en la cabecera del wizard (junto a "Guardar y
 * salir"); volver al Layout se hace con el botón "Atrás" de la cabecera.
 */
export function ThemeVerificationStep({ theme }: ThemeVerificationStepProps) {
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
      {/* Previsualización paginada (paged.js) — PDF centrado */}
      <div className="flex flex-1 min-h-0 flex-col rounded border border-ui-border bg-ui-body dark:border-ui-dark-border dark:bg-ui-dark-bg">
        <div className="border-b border-ui-border px-4 py-2 text-sm font-semibold text-text-primary dark:border-ui-dark-border dark:text-text-dark-primary">
          Previsualización
        </div>
        <div className="flex flex-1 min-h-0 justify-center overflow-auto p-4">
          <PagedThemedPreview kind="theme" id={theme.id} />
        </div>
      </div>

      {/* Resumen y PDF de muestra */}
      <aside className="w-72 shrink-0 space-y-4">
        <div>
          <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">Verificación</h3>
          <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
            Comprueba cómo se aplican colores, fuentes e imágenes. La vista PDF genera
            un PDF/UA real de muestra con lorem ipsum.
          </p>
        </div>

        <dl className="space-y-1 rounded bg-ui-body p-3 text-sm dark:bg-ui-dark-bg">
          <div className="flex justify-between">
            <dt className="text-text-muted dark:text-text-dark-muted">Nombre</dt>
            <dd className="font-medium text-text-primary dark:text-text-dark-primary">{theme.name}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-text-muted dark:text-text-dark-muted">Estado</dt>
            <dd className="font-medium text-text-primary dark:text-text-dark-primary">{theme.status}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-text-muted dark:text-text-dark-muted">Bloques</dt>
            <dd className="font-medium text-text-primary dark:text-text-dark-primary">
              {theme.layout?.regions?.length ?? 0}
            </dd>
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
          {pdfError && <p className="text-xs text-danger-dark dark:text-danger-light">⚠ {pdfError}</p>}
        </div>
      </aside>
    </div>
  );
}
