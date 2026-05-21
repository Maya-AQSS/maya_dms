import { useQuery } from '@tanstack/react-query';
import { fetchThemes } from '../../../api/themes';
import type { Theme } from '../../../types/themes';

/**
 * Lista los themes publicados disponibles para asignar a una plantilla.
 *
 * Cacheamos vía TanStack Query (clave estable) para que el selector del
 * wizard de plantilla y la página de Themes compartan la respuesta sin
 * duplicar requests. `staleTime: 5 min` porque crear/publicar themes no
 * es una acción frecuente.
 */
export function usePublishedThemes() {
  return useQuery({
    queryKey: ['themes', 'published-for-templates'],
    queryFn: async (): Promise<Theme[]> => {
      const res = await fetchThemes({ status: 'published', per_page: 100 });
      return res.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}
