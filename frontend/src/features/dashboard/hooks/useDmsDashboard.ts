import { useQuery, type QueryClient } from '@tanstack/react-query';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { fetchDashboard, type DashboardPayload } from '../../../api/dashboard';

/** Query key compartida para refrescar la bandeja tras acciones de validación. */
export const DMS_DASHBOARD_QUERY_KEY = ['dms', 'dashboard'] as const;

type State =
  | { status: 'loading' }
  | { status: 'ready'; data: DashboardPayload }
  | { status: 'error'; error: string };

/** Fuerza GET /dashboard y actualiza caché (aunque el panel no esté montado). */
export async function refreshDmsDashboardQuery(queryClient: QueryClient): Promise<void> {
  await queryClient.refetchQueries({ queryKey: [...DMS_DASHBOARD_QUERY_KEY] });
}

export function useDmsDashboard(): State {
  const { hasPermission, loading: profileLoading } = useUserProfile();
  const canLoadDashboard = hasPermission(DMS_PERMISSIONS.index);

  const { data, isPending, isError, error } = useQuery({
    queryKey: [...DMS_DASHBOARD_QUERY_KEY],
    queryFn: fetchDashboard,
    enabled: !profileLoading && canLoadDashboard,
    staleTime: 15_000,
    refetchOnMount: 'always',
    refetchInterval: !profileLoading && canLoadDashboard ? 30_000 : false,
  });

  if (profileLoading) {
    return { status: 'loading' };
  }

  if (!canLoadDashboard) {
    return { status: 'error', error: 'dms.index' };
  }

  if (isPending) {
    return { status: 'loading' };
  }

  if (isError) {
    return {
      status: 'error',
      error: error instanceof Error ? error.message : String(error),
    };
  }

  return { status: 'ready', data: data! };
}
