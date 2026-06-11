import { useEffect, useState } from 'react';
import { fetchThemeFonts } from '../../../api/themes';
import type { ThemeFontsCatalog } from '../../../types/themes';

/** Fallback estable mientras no llega la respuesta del backend. */
const FALLBACK: ThemeFontsCatalog = {
  sans: [
    { value: 'DejaVu Sans, Liberation Sans, sans-serif', label: 'DejaVu Sans' },
  ],
  serif: [
    { value: 'DejaVu Serif, Liberation Serif, serif', label: 'DejaVu Serif' },
  ],
  mono: [
    { value: 'DejaVu Sans Mono, Liberation Mono, monospace', label: 'DejaVu Sans Mono' },
  ],
};

/**
 * Carga el catálogo de tipografías servidas por el backend. Cachea en memoria
 * de la app — el listado raramente cambia (sólo al re-deploy del container).
 *
 * Mientras carga (o si falla) devuelve un fallback con las fuentes del
 * sistema garantizadas (DejaVu/Liberation), evitando que el formulario
 * aparezca con selects vacíos.
 */
export function useThemeFonts() {
  const [catalog, setCatalog] = useState<ThemeFontsCatalog>(FALLBACK);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetchThemeFonts()
      .then((fonts) => {
        if (cancelled) return;
        setCatalog(fonts);
        setError(null);
      })
      .catch((e: unknown) => {
        if (cancelled) return;
        setError(e instanceof Error ? e.message : 'Error cargando tipografías');
        // Mantenemos FALLBACK.
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return { catalog, loading, error };
}
