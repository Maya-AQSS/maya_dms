import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { NavItem } from '@maya/shared-layout-react';
import { HomeIcon, TemplateIcon } from '@maya/shared-layout-react';
import { useUserProfile } from '@maya/shared-profile-react';
import { canManageThemesCatalog } from '../../permissions';

export function useNavItems(): NavItem[] {
  const { t } = useTranslation('nav');
  const { hasPermission } = useUserProfile();

  return useMemo<NavItem[]>(() => {
    const items: NavItem[] = [
      { id: 'dashboard', label: t('nav.dashboard'), icon: HomeIcon, path: '/dashboard' },
    ];

    if (canManageThemesCatalog(hasPermission)) {
      items.push({ id: 'themes', label: t('themes:title'), icon: TemplateIcon, path: '/themes' });
    }

    return items;
  }, [t, hasPermission]);
}
