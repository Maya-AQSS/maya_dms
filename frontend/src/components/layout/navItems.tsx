import type { NavItem } from '@maya/shared-layout-react';
import {
  FolderIcon,
  HomeIcon,
  TemplateIcon,
} from '@maya/shared-layout-react';

export const NAV_ITEMS: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: HomeIcon, path: '/dashboard' },
  { id: 'documents', label: 'Documentos', icon: FolderIcon, path: '/documents' },
  { id: 'templates', label: 'Plantillas', icon: TemplateIcon, path: '/templates' },
];
