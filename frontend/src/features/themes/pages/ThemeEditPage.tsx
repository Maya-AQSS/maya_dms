import { Navigate, useParams } from 'react-router-dom';
import { useUserProfile } from '@ceedcv-maya/shared-profile-react';
import { canUpdateTheme } from '../../../permissions';
import { ThemeWizard } from '../components/ThemeWizard';
import { useTheme } from '../hooks/useTheme';

export function ThemeEditPage() {
  const { id } = useParams<{ id: string }>();
  const { profile, hasPermission } = useUserProfile();
  const { theme, loading, error } = useTheme(id);

  if (loading && !theme) {
    return <p className="p-4 text-sm">Cargando theme…</p>;
  }

  if (error || !theme) {
    return (
      <div className="m-4 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
        {error || 'No se ha podido cargar el theme.'}
      </div>
    );
  }

  if (!canUpdateTheme(hasPermission, profile?.id, theme.created_by)) {
    return <Navigate to="/themes" replace />;
  }

  return <ThemeWizard initial={theme} />;
}
