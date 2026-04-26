import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import type { NavItem } from '@maya/shared-layout-react';
import { FolderIcon, HomeIcon, TemplateIcon } from '@maya/shared-layout-react';

export function useNavItems(): NavItem[] {
  const { t } = useTranslation('nav');
  return useMemo<NavItem[]>(
    () => [
      { id: 'dashboard', label: t('dashboard'), icon: HomeIcon, path: '/dashboard' },
      { id: 'documents', label: t('documents'), icon: FolderIcon, path: '/documents' },
      { id: 'templates', label: t('templates'), icon: TemplateIcon, path: '/templates' },
    ],
    [t],
  );
}
