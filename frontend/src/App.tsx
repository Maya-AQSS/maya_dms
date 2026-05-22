import { lazy, Suspense, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Route, Routes, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { AppLayout } from '@maya/shared-layout-react';
import { NotificationsBell, SidebarFavorites } from '@maya/shared-sidebar-react';
import { useKeycloakLocaleSync } from '@maya/shared-i18n-react';
import { useOidcSession } from '@maya/shared-auth-react';
import { useRequireAppAccess } from '@maya/shared-profile-react';
import { SidebarProcesos } from './components/layout';
import { useUserProfile, profileDisplayInitials } from './features/user-profile';
import { HierarchyProvider } from './features/hierarchy/context/HierarchyContext';
import { useNavItems } from './components/layout/navItems';
import { resolveServiceUrl } from './lib/peerService';
import { DMS_PERMISSIONS } from './permissions';

// Lazy-loaded pages
const DashboardPage = lazy(() => import('./pages/DashboardPage').then(m => ({ default: m.DashboardPage })));
const ProcesosPage = lazy(() => import('./pages/ProcesosPage').then(m => ({ default: m.ProcesosPage })));
const NuevaProgramacionSelectorPage = lazy(() => import('./pages/NuevaProgramacionSelectorPage').then(m => ({ default: m.NuevaProgramacionSelectorPage })));
const DocumentEditorPage = lazy(() => import('./pages/DocumentEditorPage').then(m => ({ default: m.DocumentEditorPage })));
const DocumentValidationPage = lazy(() => import('./pages/DocumentValidationPage').then(m => ({ default: m.DocumentValidationPage })));
const DocumentPreviewPage = lazy(() => import('./pages/DocumentPreviewPage').then(m => ({ default: m.DocumentPreviewPage })));
const TemplateNewPage = lazy(() => import('./pages/TemplateNewPage').then(m => ({ default: m.TemplateNewPage })));
const TemplateEditPage = lazy(() => import('./pages/TemplateEditPage').then(m => ({ default: m.TemplateEditPage })));
const TemplateReviewPage = lazy(() => import('./pages/TemplateReviewPage').then(m => ({ default: m.TemplateReviewPage })));
const TemplatePreviewPage = lazy(() => import('./pages/TemplatePreviewPage').then(m => ({ default: m.TemplatePreviewPage })));
const ThemesListPage = lazy(() => import('./features/themes/pages/ThemesListPage').then(m => ({ default: m.ThemesListPage })));
const ThemeNewPage = lazy(() => import('./features/themes/pages/ThemeNewPage').then(m => ({ default: m.ThemeNewPage })));
const ThemeEditPage = lazy(() => import('./features/themes/pages/ThemeEditPage').then(m => ({ default: m.ThemeEditPage })));
const ThemeLayoutPage = lazy(() => import('./features/themes/pages/ThemeLayoutPage').then(m => ({ default: m.ThemeLayoutPage })));
const PlaceholderPage = lazy(() => import('./pages/PlaceholderPage').then(m => ({ default: m.PlaceholderPage })));

const DASHBOARD_API_URL = resolveServiceUrl(
  import.meta.env.VITE_DASHBOARD_API_URL as string | undefined,
  'dashboard-api',
);

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
        <Route path="/templates" element={<Navigate to="/dashboard" replace />} />
        <Route path="/templates/new" element={<TemplateNewPage />} />
        <Route path="/templates/:id/edit" element={<TemplateEditPage />} />
        <Route path="/templates/:id/review" element={<TemplateReviewPage />} />
        <Route path="/templates/:id" element={<TemplatePreviewPage />} />
        <Route path="/themes" element={<ThemesListPage />} />
        <Route path="/themes/new" element={<ThemeNewPage />} />
        <Route path="/themes/:id/edit" element={<ThemeEditPage />} />
        <Route path="/themes/:id/layout" element={<ThemeLayoutPage />} />
        <Route path="*" element={<PlaceholderPage />} />
      </Routes>
    </Suspense>
  );
}

function AppWithLayout() {
  const { logout, isOidcSignedIn } = useOidcSession();
  const { profile } = useUserProfile();
  const navItems = useNavItems();
  const navigate = useNavigate();
  const location = useLocation();
  const wasAuthenticatedRef = useRef(false);
  const previousPathRef = useRef<string | null>(null);
  useKeycloakLocaleSync();

  useEffect(() => {
    previousPathRef.current = location.pathname;
  }, [location.pathname]);

  useEffect(() => {
    const wasAuthenticated = wasAuthenticatedRef.current;
    if (!wasAuthenticated && isOidcSignedIn) {
      if (previousPathRef.current === '/templates/new' || previousPathRef.current === '/documentos/nuevo') {
        navigate('/dashboard', { replace: true });
        return;
      }

      if (location.pathname === '/') {
        navigate('/dashboard', { replace: true });
      }
    }
    wasAuthenticatedRef.current = isOidcSignedIn;
  }, [isOidcSignedIn, location.pathname, navigate]);

  const userName = profile?.name?.trim() ?? '';
  const userInitials = profileDisplayInitials(profile);
  const onProfile = () => {
    const dashboardOrigin = resolveServiceUrl(
      import.meta.env.VITE_DASHBOARD_URL as string | undefined,
      'dashboard',
    );
    window.location.assign(`${dashboardOrigin}/profile`);
  };

  return (
    <AppLayout
      navItems={navItems}
      brandName="DocuCEED"
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

function AuthLoadingScreen({ message }: { message: string }) {
  return (
    <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
      {message}
    </div>
  );
}

/**
 * Requiere `dms.login` en /me. Si falta:
 *  - Si el usuario tiene `dashboard.login`, redirige al portal (preserva SSO).
 *  - Si no, cierra sesión SSO.
 */
function AppAfterProfile() {
  const { t } = useTranslation('auth');
  const dashboardOrigin = resolveServiceUrl(
    import.meta.env.VITE_DASHBOARD_URL as string | undefined,
    'dashboard',
  );
  const { profileLoading, lacksLoginPermission } = useRequireAppAccess(
    DMS_PERMISSIONS.login,
    { portalLoginSlug: 'dashboard.login', portalUrl: dashboardOrigin },
  );

  if (profileLoading) {
    return <AuthLoadingScreen message={t('auth.initializing')} />;
  }

  if (lacksLoginPermission) {
    return <AuthLoadingScreen message={t('signingOutNoPermission')} />;
  }

  return (
    <HierarchyProvider>
      <AppWithLayout />
    </HierarchyProvider>
  );
}

export default function App() {
  const { t } = useTranslation('auth');
  const { isOidcLoading, isOidcSignedIn, beginSignIn } = useOidcSession();

  useEffect(() => {
    if (!isOidcLoading && !isOidcSignedIn) {
      beginSignIn();
    }
  }, [isOidcLoading, isOidcSignedIn, beginSignIn]);

  if (isOidcLoading) {
    return <AuthLoadingScreen message={t('auth.initializing')} />;
  }

  if (!isOidcSignedIn) {
    return <AuthLoadingScreen message={t('auth.redirecting')} />;
  }

  return <AppAfterProfile />;
}
