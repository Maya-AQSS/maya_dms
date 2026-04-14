import type { FC } from 'react';
import {
  FolderIcon,
  HomeIcon,
  SearchIcon,
  TemplateIcon,
  UploadIcon,
  UsersIcon,
} from './navIcons';

export type NavItem = {
  id: string;
  label: string;
  icon: FC;
  path: string;
};

export const NAV_ITEMS: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: HomeIcon, path: '/dashboard' },
  { id: 'documents', label: 'Documentos', icon: FolderIcon, path: '/documents' },
  { id: 'templates', label: 'Plantillas', icon: TemplateIcon, path: '/templates' },
  { id: 'groups', label: 'Grupos', icon: UsersIcon, path: '/groups' },
  { id: 'upload', label: 'Subir archivo', icon: UploadIcon, path: '/upload' },
  { id: 'search', label: 'Búsqueda', icon: SearchIcon, path: '/search' },
];
