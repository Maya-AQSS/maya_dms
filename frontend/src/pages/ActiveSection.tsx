import { DashboardPage } from './DashboardPage';
import { DocumentsPage } from './DocumentsPage';
import { GroupsPage } from './GroupsPage';
import { PlaceholderPage } from './PlaceholderPage';
import { TemplatesPage } from './TemplatesPage';

type Props = {
  section: string;
};

/**
 * Capa de composición: enlaza el id de navegación con la página correspondiente.
 */
export function ActiveSection({ section }: Props) {
  switch (section) {
    case 'dashboard':
      return <DashboardPage />;
    case 'documents':
      return <DocumentsPage />;
    case 'templates':
      return <TemplatesPage />;
    case 'groups':
      return <GroupsPage />;
    default:
      return <PlaceholderPage />;
  }
}
