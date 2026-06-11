import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { NavItem } from '@ceedcv-maya/shared-layout-react';
import { FolderIcon, GridIcon, HomeIcon, TemplateIcon } from '@ceedcv-maya/shared-layout-react';
import { useUserProfile } from '@ceedcv-maya/shared-profile-react';
import { canManageThemesCatalog, DMS_PERMISSIONS } from '../../permissions';
import { useMediaQuery } from '../../hooks/useMediaQuery';

interface UseNavItemsOptions {
  onOpenProcessesDrawer?: () => void;
}

export function useNavItems({ onOpenProcessesDrawer }: UseNavItemsOptions = {}): NavItem[] {
  const { t } = useTranslation('nav');
  const { hasPermission } = useUserProfile();
  const isDesktop = useMediaQuery('(min-width: 768px)');
  const canIndexProcesses = hasPermission(DMS_PERMISSIONS.processIndex);
  const canManageProcesses =
    hasPermission(DMS_PERMISSIONS.processCreate) ||
    hasPermission(DMS_PERMISSIONS.processUpdate) ||
    hasPermission(DMS_PERMISSIONS.processDelete);

  return useMemo<NavItem[]>(() => {
    const items: NavItem[] = [
      { id: 'dashboard', label: t('nav.dashboard'), icon: HomeIcon, path: '/dashboard' },
    ];

    if (canManageThemesCatalog(hasPermission)) {
      items.push({ id: 'themes', label: t('themes:title'), icon: TemplateIcon, path: '/themes' });
    }

    if (canIndexProcesses) {
      items.push({
        id: 'procesos',
        label: t('procesos', { defaultValue: 'Procesos' }),
        icon: FolderIcon,
        path: '/processes',
        onClick: (event) => {
          if (isDesktop && onOpenProcessesDrawer) {
            event.preventDefault();
            onOpenProcessesDrawer();
          }
        },
      });
    }

    if (canManageProcesses) {
      items.push({
        id: 'admin-procesos',
        label: t('adminProcesos', { defaultValue: 'Gestión Procesos' }),
        icon: GridIcon,
        path: '/admin/processes',
      });
    }

    return items;
  }, [t, hasPermission, canIndexProcesses, canManageProcesses, isDesktop, onOpenProcessesDrawer]);
}
