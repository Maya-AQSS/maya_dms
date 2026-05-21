import { useCallback, useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import { fetchTheme } from '../../../api/themes';
import type { Theme } from '../../../types/themes';

function formatLoadError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 404) return 'Theme no encontrado.';
    if (err.status === 401) return 'Sesión no válida.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

/** Carga un theme por id. Sin polling — usar refetch tras mutaciones. */
export function useTheme(id: string | undefined) {
  const [theme, setTheme] = useState<Theme | null>(null);
  const [loading, setLoading] = useState(Boolean(id));
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) {
      setTheme(null);
      setLoading(false);
      return;
    }
    try {
      setError(null);
      setLoading(true);
      const res = await fetchTheme(id);
      setTheme(res.data);
    } catch (e) {
      setError(formatLoadError(e));
      setTheme(null);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  return { theme, loading, error, refetch: load };
}
