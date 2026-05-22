import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { NavItem } from '@maya/shared-layout-react';
import { HomeIcon } from '@maya/shared-layout-react';

export function useNavItems(): NavItem[] {
  const { t } = useTranslation('nav');
  return useMemo<NavItem[]>(
    () => [
      { id: 'dashboard', label: t('nav.dashboard'), icon: HomeIcon, path: '/dashboard' },
      { id: 'themes', label: t('themes:title'), icon: HomeIcon, path: '/themes' },
    ],
    [t],
  );
}
