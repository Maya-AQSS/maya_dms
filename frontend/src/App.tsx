import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { Spinner } from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { Route, Routes, Navigate, useLocation, useNavigate, useParams } from 'react-router-dom';
import { MayaAppShell } from '@ceedcv-maya/shared-layout-react';
import { resolveServiceUrl, useOidcSession } from '@ceedcv-maya/shared-auth-react';
import { ProcessesDrawer } from './components/layout/ProcessesDrawer';
import { HierarchyProvider } from './features/hierarchy/context/HierarchyContext';
import { useNavItems } from './components/layout/navItems';
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
const ThemeShowPage = lazy(() => import('./features/themes/pages/ThemeShowPage').then(m => ({ default: m.ThemeShowPage })));
const ThemeLayoutPage = lazy(() => import('./features/themes/pages/ThemeLayoutPage').then(m => ({ default: m.ThemeLayoutPage })));
const ProcessesManagePage = lazy(() => import('./features/processes/pages/ProcessesManagePage').then(m => ({ default: m.ProcessesManagePage })));
const ProcessShowPage = lazy(() => import('./features/processes/pages/ProcessShowPage').then(m => ({ default: m.ProcessShowPage })));
const PlaceholderPage = lazy(() => import('./pages/PlaceholderPage').then(m => ({ default: m.PlaceholderPage })));

const DASHBOARD_API_URL = resolveServiceUrl(
  import.meta.env.VITE_DASHBOARD_API_URL as string | undefined,
  'dashboard-api',
);
const DASHBOARD_URL = resolveServiceUrl(
  import.meta.env.VITE_DASHBOARD_URL as string | undefined,
  'dashboard',
);

/** Redirige las rutas antiguas en español a sus equivalentes en inglés. */
function LegacyProcesoRedirect() {
  const { processId } = useParams();
  return <Navigate to={processId ? `/processes/${processId}` : '/processes'} replace />;
}

function LegacyAdminProcesosRedirect() {
  const location = useLocation();
  return <Navigate to={location.pathname.replace('/admin/procesos', '/admin/processes')} replace />;
}

function AppRoutes() {
  return (
    <Suspense fallback={<div className="p-8 flex justify-center"><Spinner /></div>}>
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/processes" element={<ProcesosPage />} />
        <Route path="/processes/:processId" element={<ProcesosPage />} />
        <Route path="/documents/new" element={<NuevaProgramacionSelectorPage />} />
        <Route path="/documents/new/:templateId/wizard" element={<DocumentEditorPage />} />
        {/* Redirecciones legacy: rutas antiguas en español */}
        <Route path="/procesos" element={<Navigate to="/processes" replace />} />
        <Route path="/procesos/:processId" element={<LegacyProcesoRedirect />} />
        <Route path="/documentos/nuevo" element={<Navigate to="/documents/new" replace />} />
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
        <Route path="/themes/:id" element={<ThemeShowPage />} />
        <Route path="/themes/:id/layout" element={<ThemeLayoutPage />} />
        <Route path="/admin/processes" element={<ProcessesManagePage />} />
        <Route path="/admin/processes/new" element={<ProcessShowPage />} />
        <Route path="/admin/processes/:processId" element={<ProcessShowPage />} />
        <Route path="/admin/procesos/*" element={<LegacyAdminProcesosRedirect />} />
        <Route path="*" element={<PlaceholderPage />} />
      </Routes>
    </Suspense>
  );
}

/**
 * Comportamiento post-login que el shell compartido no cubre (específico dms):
 * - Cierra el ProcessesDrawer en cada cambio de ruta (y en cambios de sesión).
 * - Al completar el login, si la ruta era un wizard de creación
 *   (`/templates/new`, `/documents/new`) o `/`, redirige a `/dashboard`.
 *
 * Se monta como hijo de MayaAppShell para conservar el timing original:
 * solo existe una vez superado el gate de sesión + permiso.
 */
function PostLoginBehavior({ onRouteChange }: { onRouteChange: () => void }) {
  const { isOidcSignedIn } = useOidcSession();
  const navigate = useNavigate();
  const location = useLocation();
  const wasAuthenticatedRef = useRef(false);
  const previousPathRef = useRef<string | null>(null);

  useEffect(() => {
    previousPathRef.current = location.pathname;
  }, [location.pathname]);

  useEffect(() => {
    onRouteChange();
    const wasAuthenticated = wasAuthenticatedRef.current;
    if (!wasAuthenticated && isOidcSignedIn) {
      if (previousPathRef.current === '/templates/new' || previousPathRef.current === '/documents/new') {
        navigate('/dashboard', { replace: true });
        return;
      }

      if (location.pathname === '/') {
        navigate('/dashboard', { replace: true });
      }
    }
    wasAuthenticatedRef.current = isOidcSignedIn;
  }, [isOidcSignedIn, location.pathname, navigate, onRouteChange]);

  return null;
}

/**
 * App shell unificado (@ceedcv-maya/shared-layout-react).
 *
 * El shell gestiona: init OIDC + redirect a login, gate de permiso
 * (`dms.login` vía useRequireAppAccess con redirect al portal),
 * AppLayout con NotificationsBell/SidebarFavorites/resolveUserDisplay,
 * useKeycloakLocaleSync y useRealtimeNotifications.
 *
 * Específico dms: ProcessesDrawer en el slot `afterLayout` (su estado vive
 * aquí) y HierarchyProvider envolviendo las rutas (post-gate, como antes).
 */
export default function App() {
  const { t } = useTranslation('auth');
  const [processesDrawerOpen, setProcessesDrawerOpen] = useState(false);
  const openProcessesDrawer = useCallback(() => setProcessesDrawerOpen(true), []);
  const closeProcessesDrawer = useCallback(() => setProcessesDrawerOpen(false), []);
  const navItems = useNavItems({ onOpenProcessesDrawer: openProcessesDrawer });

  return (
    <MayaAppShell
      brandName="DocuCEED"
      brandVersion="v1.0"
      brandLogoUrl="/favicon.png"
      dashboardUrl={DASHBOARD_URL}
      dashboardApiUrl={DASHBOARD_API_URL}
      navItems={navItems}
      loginPermission={DMS_PERMISSIONS.login}
      portalLoginSlug="dashboard.login"
      onNotificationNavigate={(n) => window.open(`${DASHBOARD_URL}/notifications/${n.id}`, '_blank', 'noopener,noreferrer')}
      loadingInitializingMessage={t('auth.initializing')}
      loadingRedirectingMessage={t('auth.redirecting')}
      loadingProfileMessage={t('auth.initializing')}
      loadingNoPermissionMessage={t('signingOutNoPermission')}
      favoritesLabel="Favoritas"
      afterLayout={<ProcessesDrawer open={processesDrawerOpen} onClose={closeProcessesDrawer} />}
    >
      <HierarchyProvider>
        <PostLoginBehavior onRouteChange={closeProcessesDrawer} />
        <AppRoutes />
      </HierarchyProvider>
    </MayaAppShell>
  );
}
