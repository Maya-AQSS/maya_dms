import { useMemo } from 'react';
import { createDataHook } from '@maya/shared-auth-react';
import { fetchFavorites } from '../api/favorites';

/**
 * Favoritos a nivel ENTIDAD (templates + documents) dentro de DMS.
 * Coexiste intencionalmente con `useSharedFavorites` (paquete shared
 * `@maya/shared-sidebar-react`), que gestiona favoritos a nivel APLICACIÓN
 * en el sidebar del ecosistema. No son duplicados: backends distintos
 * (este → `dms-api/api/v1/favorites`; shared → `dashboard-api/api/v1/dashboard/user/{sub}/favorites`),
 * dominios distintos y rutas de mutación distintas.
 *
 * Decisión registrada en `maya_dms/SPIKE_useSharedFavorites.md` (2026-05-14).
 */

interface FavoritesIds {
  template_ids: string[];
  document_ids: string[];
}

const useFavoritesIdsQuery = createDataHook<void, FavoritesIds>({
  queryKey: () => ['favorites', 'ids'],
  fetcher: async () => {
    const { data } = await fetchFavorites();
    return { template_ids: data.template_ids, document_ids: data.document_ids };
  },
  defaultOptions: {
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  },
});

/**
 * IDs de plantillas y documentos favoritos del usuario (GET /favorites).
 * TanStack Query refresca al recuperar el foco de la ventana automáticamente.
 *
 * NOTA: este hook es local a DMS y NO duplica `@maya/shared-sidebar-react`
 * `useSharedFavorites`. Ese es para favoritos de aplicaciones (sidebar
 * cross-app, datos del backend maya_dashboard). Éste maneja favoritos de
 * plantillas/documentos (entidad-nivel, backend maya_dms).
 */
export function useFavoritesIds(): {
  templateIds: ReadonlySet<string>;
  documentIds: ReadonlySet<string>;
  loading: boolean;
  refetch: () => Promise<void>;
} {
  const query = useFavoritesIdsQuery();

  const templateIds = useMemo(
    () => new Set(query.data?.template_ids ?? []),
    [query.data],
  );
  const documentIds = useMemo(
    () => new Set(query.data?.document_ids ?? []),
    [query.data],
  );

  return {
    templateIds,
    documentIds,
    loading: query.isLoading,
    refetch: async () => {
      await query.refetch();
    },
  };
}
