import { useQuery } from '@tanstack/react-query';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { fetchDashboard, type DashboardPayload } from '../../../api/dashboard';

type State =
  | { status: 'loading' }
  | { status: 'ready'; data: DashboardPayload }
  | { status: 'error'; error: string };

export function useDmsDashboard(): State {
  const { hasPermission } = useUserProfile();
  const enabled = hasPermission(DMS_PERMISSIONS.index);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['dms', 'dashboard'],
    queryFn: fetchDashboard,
    enabled,
    staleTime: 30_000,
    refetchInterval: enabled ? 30_000 : false,
  });

  if (!enabled) {
    return { status: 'error', error: 'dms.index' };
  }

  if (isLoading) {
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
