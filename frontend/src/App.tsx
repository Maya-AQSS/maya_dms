import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import './index.css';
import { AppLayout } from '@maya/shared-layout-react';
import { LocaleSelector, SidebarFavorites } from '@maya/shared-sidebar-react';
import { NAV_ITEMS } from './components/layout/navItems';

const DASHBOARD_API_URL = (import.meta.env.VITE_DASHBOARD_API_URL as string | undefined)
  ?? 'http://maya_dashboard_api.localhost';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentValidationPage,
  DocumentPreviewPage,
  DocumentsPage,
  PlaceholderPage,
  TemplateEditPage,
  TemplateNewPage,
  TemplatesPage,
} from './pages';
import { useOidcSession } from './auth/useOidcSession';
import { HierarchyProvider } from './features/hierarchy';
import { useUserProfile, profileDisplayInitials } from './features/user-profile';

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/dashboard" element={<DashboardPage />} />
      <Route path="/documents" element={<DocumentsPage />} />
      <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
      <Route path="/documents/:documentId/validate" element={<DocumentValidationPage />} />
      <Route path="/documents/:documentId" element={<DocumentPreviewPage />} />
      <Route path="/templates" element={<TemplatesPage />} />
      <Route path="/templates/new" element={<TemplateNewPage />} />
      <Route path="/templates/:id/edit" element={<TemplateEditPage />} />
      <Route path="*" element={<PlaceholderPage />} />
    </Routes>
  );
}

function AppWithLayout() {
  const { logout } = useOidcSession();
  const { profile } = useUserProfile();
  const location = useLocation();

  const isEditorRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/editor');
  const isDocumentValidateRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/validate');
  const isDocumentPreviewRoute = /^\/documents\/[^/]+$/.test(location.pathname);
  const titleOverride = isEditorRoute
    ? 'Editor de Programación'
    : isDocumentValidateRoute
      ? 'Validación de programación'
      : isDocumentPreviewRoute
        ? 'Previsualización'
        : undefined;

  const userName = profile?.name?.trim() ?? '';
  const userInitials = profileDisplayInitials(profile);

  return (
    <AppLayout
      navItems={NAV_ITEMS}
      brandName="Maya DMS"
      brandVersion="Maya DMS v1.0"
      userName={userName}
      userInitials={userInitials}
      onLogout={logout}
      titleOverride={titleOverride}
      topbarActions={<LocaleSelector />}
      sidebarFooter={<SidebarFavorites label="Favoritas" dashboardApiUrl={DASHBOARD_API_URL} />}
    >
      <AppRoutes />
    </AppLayout>
  );
}

function App() {
  const { isOidcLoading, isOidcSignedIn, beginSignIn } = useOidcSession();

  if (isOidcLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Iniciando sesión…
      </div>
    );
  }

  if (!isOidcSignedIn) {
    beginSignIn();
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Redirigiendo al inicio de sesión...
      </div>
    );
  }

  return (
    <HierarchyProvider>
      <AppWithLayout />
    </HierarchyProvider>
  );
}

export default App;
