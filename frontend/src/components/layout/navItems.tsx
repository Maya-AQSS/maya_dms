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
};

export const NAV_ITEMS: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: HomeIcon },
  { id: 'documents', label: 'Documentos', icon: FolderIcon },
  { id: 'templates', label: 'Plantillas', icon: TemplateIcon },
  { id: 'groups', label: 'Grupos', icon: UsersIcon },
  { id: 'upload', label: 'Subir archivo', icon: UploadIcon },
  { id: 'search', label: 'Búsqueda', icon: SearchIcon },
];
