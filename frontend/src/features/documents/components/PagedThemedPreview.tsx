import { useEffect, useState } from 'react';
import { buildApiUrl, getBearerToken } from '../../../api/http';

interface PagedThemedPreviewProps {
  /** `document` o `template` — define el endpoint de preview. */
  kind: 'document' | 'template';
  /** UUID del documento o de la plantilla. */
  id: string;
  /** Altura del iframe. Defecto: ocupa el alto restante del contenedor. */
  className?: string;
}

/**
 * Iframe paginado con la vista themed (HTML + CSS Paged Media + paged.js).
 *
 * El endpoint `/documents/{id}/preview` (o `/templates/{id}/preview`)
 * requiere Bearer JWT — no podemos hacer un `<iframe src="...">` directo
 * porque el browser no añade el header. Hacemos fetch, leemos el HTML
 * como texto, creamos un `Blob` y lo cargamos en el iframe vía blob URL.
 *
 * El HTML devuelto ya incluye `<script src="/vendor/pagedjs/...">` (el
 * Blade lo emite cuando `preview_mode=true`), que repagina el contenido
 * en hojas A4 reales dentro del iframe.
 *
 * Limpiamos la blob URL al desmontar para evitar leak de memoria.
 */
export function PagedThemedPreview({ kind, id, className }: PagedThemedPreviewProps) {
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    let createdUrl: string | null = null;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const path = kind === 'document' ? `documents/${id}/preview` : `templates/${id}/preview`;
        const token = await getBearerToken();
        const response = await fetch(buildApiUrl(path), {
          headers: token ? { Authorization: `Bearer ${token}` } : undefined,
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const html = await response.text();
        if (cancelled) return;
        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        createdUrl = URL.createObjectURL(blob);
        setBlobUrl(createdUrl);
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'No se pudo cargar la vista previa.');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();

    return () => {
      cancelled = true;
      if (createdUrl) URL.revokeObjectURL(createdUrl);
    };
  }, [kind, id]);

  if (loading) {
    return (
      <div className={`flex h-full min-h-[400px] items-center justify-center text-sm text-text-muted ${className ?? ''}`}>
        Cargando vista PDF themed…
      </div>
    );
  }

  if (error) {
    return (
      <div className={`flex h-full min-h-[400px] items-center justify-center text-sm text-danger-dark ${className ?? ''}`}>
        ⚠ {error}
      </div>
    );
  }

  if (!blobUrl) return null;

  return (
    <iframe
      src={blobUrl}
      title={kind === 'document' ? 'Vista PDF del documento' : 'Vista PDF de la plantilla'}
      // sandbox: permite scripts (paged.js los necesita) pero bloquea formularios,
      // navegación top-level, popups y same-origin (cookies). El HTML es de
      // nuestro backend autenticado, así que el riesgo es bajo, pero
      // limitamos por defensa en profundidad.
      sandbox="allow-scripts allow-same-origin"
      className={`block h-full w-full border-0 bg-white ${className ?? ''}`}
    />
  );
}
