import { lazy, Suspense, useEffect, useRef } from 'react';
import { Route, Routes, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { AppLayout } from '@maya/shared-layout-react';
import { NotificationsBell, SidebarFavorites } from '@maya/shared-sidebar-react';
import { useKeycloakLocaleSync } from '@maya/shared-i18n-react';
import { useAuth } from '@maya/shared-auth-react';
import { SidebarProcesos } from './components/layout';
import { useUserProfile, profileDisplayInitials } from './features/user-profile';
import { HierarchyProvider } from './features/hierarchy/context/HierarchyContext';
import { useNavItems } from './components/layout/navItems';

// Lazy-loaded pages
const DashboardPage = lazy(() => import('./pages/DashboardPage').then(m => ({ default: m.DashboardPage })));
const ProcesosPage = lazy(() => import('./pages/ProcesosPage').then(m => ({ default: m.ProcesosPage })));
const NuevaProgramacionSelectorPage = lazy(() => import('./pages/NuevaProgramacionSelectorPage').then(m => ({ default: m.NuevaProgramacionSelectorPage })));
const DocumentEditorPage = lazy(() => import('./pages/DocumentEditorPage').then(m => ({ default: m.DocumentEditorPage })));
const DocumentValidationPage = lazy(() => import('./pages/DocumentValidationPage').then(m => ({ default: m.DocumentValidationPage })));
const DocumentPreviewPage = lazy(() => import('./pages/DocumentPreviewPage').then(m => ({ default: m.DocumentPreviewPage })));
const TemplatesPage = lazy(() => import('./pages/TemplatesPage').then(m => ({ default: m.TemplatesPage })));
const TemplateNewPage = lazy(() => import('./pages/TemplateNewPage').then(m => ({ default: m.TemplateNewPage })));
const TemplateEditPage = lazy(() => import('./pages/TemplateEditPage').then(m => ({ default: m.TemplateEditPage })));
const TemplateReviewPage = lazy(() => import('./pages/TemplateReviewPage').then(m => ({ default: m.TemplateReviewPage })));
const TemplatePreviewPage = lazy(() => import('./pages/TemplatePreviewPage').then(m => ({ default: m.TemplatePreviewPage })));
const PlaceholderPage = lazy(() => import('./pages/PlaceholderPage').then(m => ({ default: m.PlaceholderPage })));

const DASHBOARD_API_URL = (import.meta.env.VITE_DASHBOARD_API_URL as string | undefined) ?? 'http://maya-dashboard-api.localhost';

function AppRoutes() {
  return (
    <Suspense fallback={<div className="p-8">Cargando...</div>}>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/procesos" element={<ProcesosPage />} />
        <Route path="/procesos/:processId" element={<ProcesosPage />} />
        <Route path="/documentos/nuevo" element={<NuevaProgramacionSelectorPage />} />
        <Route path="/documentos/nuevo/:templateId/wizard" element={<DocumentEditorPage />} />
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
    </Suspense>
  );
}

function Main() {
  const { logout, user } = useAuth();
  const { profile } = useUserProfile();
  const navItems = useNavItems();
  useKeycloakLocaleSync();

  const userName = profile?.name?.trim() ?? '';
  const userInitials = profileDisplayInitials(profile);
  const onProfile = () => {
    const dashboardOrigin = (import.meta.env.VITE_DASHBOARD_URL as string | undefined)
      ?? 'http://maya-dashboard.localhost';
    window.location.assign(`${dashboardOrigin}/profile`);
  };

  return (
    <AppLayout
      navItems={navItems}
      brandName="Maya DMS"
      brandVersion="v1.0"
      brandLogoUrl="/favicon.png"
      userName={userName}
      userInitials={userInitials}
      onLogout={logout}
      onProfile={onProfile}
      favoritesSlot={
        <>
          <SidebarProcesos />
          <SidebarFavorites label="Favoritas" dashboardApiUrl={DASHBOARD_API_URL} />
        </>
      }
      notificationsSlot={<NotificationsBell dashboardApiUrl={DASHBOARD_API_URL} />}
    >
      <AppRoutes />
    </AppLayout>
  );
}

function App() {
  const { isLoading, isAuthenticated, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const wasAuthenticatedRef = useRef(false);

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      login();
    }
  }, [isLoading, isAuthenticated, login]);

  useEffect(() => {
    if (isLoading) return;
    const wasAuthenticated = wasAuthenticatedRef.current;
    if (!wasAuthenticated && isAuthenticated && location.pathname !== '/dashboard') {
      navigate('/dashboard', { replace: true });
    }
    wasAuthenticatedRef.current = isAuthenticated;
  }, [isAuthenticated, isLoading, location.pathname, navigate]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Iniciando sesión…
      </div>
    );
  }

  if (!isAuthenticated) return null;

  return (
    <HierarchyProvider>
      <Main />
    </HierarchyProvider>
  );
}

export default App;
