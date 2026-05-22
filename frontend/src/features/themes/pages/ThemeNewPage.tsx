import { Navigate } from 'react-router-dom';
import { useUserProfile } from '@maya/shared-profile-react';
import { canCreateTheme } from '../../../permissions';
import { ThemeWizard } from '../components/ThemeWizard';

export function ThemeNewPage() {
  const { hasPermission } = useUserProfile();

  if (!canCreateTheme(hasPermission)) {
    return <Navigate to="/themes" replace />;
  }

  return <ThemeWizard initial={null} />;
}
