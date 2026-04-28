import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import './index.css';
import './App.css';
import { AppLayout } from '@maya/shared-layout-react';
import { LocaleSelector, NotificationsBell, SidebarFavorites } from '@maya/shared-sidebar-react';
import { useNavItems } from './components/layout';

const DASHBOARD_API_URL = (import.meta.env.VITE_DASHBOARD_API_URL as string | undefined)
  ?? 'http://maya_dashboard_api.localhost';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentValidationPage,
  DocumentPreviewPage,
  DocumentsPage,
  PlaceholderPage,
  ProcesosPage,
  TemplateEditPage,
  TemplateNewPage,
  TemplatePreviewPage,
  TemplateReviewPage,
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
      <Route path="/procesos" element={<ProcesosPage />} />
      <Route path="/documents" element={<DocumentsPage />} />
      <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
      <Route path="/documents/:documentId/validate" element={<DocumentValidationPage />} />
      <Route path="/documents/:documentId" element={<DocumentPreviewPage />} />
      <Route path="/templates" element={<TemplatesPage />} />
      <Route path="/templates/new" element={<TemplateNewPage />} />
      <Route path="/templates/:id/edit" element={<TemplateEditPage />} />
      <Route path="/templates/:id/review" element={<TemplateReviewPage />} />
      <Route path="/templates/:id" element={<TemplatePreviewPage />} />
      <Route path="*" element={<PlaceholderPage />} />
    </Routes>
  );
}

function AppWithLayout() {
  const { logout, user } = useOidcSession();
  const { profile } = useUserProfile();
  const navItems = useNavItems();
  const { i18n } = useTranslation();
  const location = useLocation();

  useEffect(() => {
    if (user?.locale) void i18n.changeLanguage(user.locale);
  }, [user?.locale, i18n]);

  useEffect(() => {
    const onStorage = (e: StorageEvent) => {
      if (e.key === 'locale' && e.newValue) void i18n.changeLanguage(e.newValue);
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, [i18n]);

  const isEditorRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/editor');
  const isDocumentValidateRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/validate');
  const isDocumentPreviewRoute = /^\/documents\/[^/]+$/.test(location.pathname);
  const isTemplatePreviewRoute = /^\/templates\/[^/]+$/.test(location.pathname);
  const isProcesosRoute = location.pathname === '/procesos';
  const titleOverride = isEditorRoute
    ? 'Editor de Programación'
    : isDocumentValidateRoute
      ? 'Validación de programación'
      : isDocumentPreviewRoute
        ? 'Previsualización'
        : isTemplatePreviewRoute
          ? 'Previsualización'
          : isProcesosRoute
            ? 'Procesos'
            : undefined;

  const userName = profile?.name?.trim() ?? '';
  const userInitials = profileDisplayInitials(profile);

  return (
    <AppLayout
      navItems={navItems}
      brandName="Maya DMS"
      brandVersion="Maya DMS v1.0"
      userName={userName}
      userInitials={userInitials}
      onLogout={logout}
      titleOverride={titleOverride}
      topbarActions={
        <>
          <NotificationsBell dashboardApiUrl={DASHBOARD_API_URL} />
          <LocaleSelector />
        </>
      }
      sidebarFooter={<SidebarFavorites label="Favoritas" dashboardApiUrl={DASHBOARD_API_URL} />}
    >
      <AppRoutes />
    </AppLayout>
  );
}

function App() {
  const { isOidcLoading, isOidcSignedIn, beginSignIn } = useOidcSession();

  useEffect(() => {
    if (!isOidcLoading && !isOidcSignedIn) {
      beginSignIn();
    }
  }, [isOidcLoading, isOidcSignedIn, beginSignIn]);

  if (isOidcLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Iniciando sesión…
      </div>
    );
  }

  if (!isOidcSignedIn) {
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
