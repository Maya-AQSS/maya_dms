import { lazy, Suspense, useEffect } from'react';
import { Navigate, Route, Routes } from'react-router-dom';
import { useTranslation } from'react-i18next';
import'./index.css';
import'./App.css';
import { AppLayout } from'@maya/shared-layout-react';
import { NotificationsBell, SidebarFavorites } from'@maya/shared-sidebar-react';
import { SkeletonPage } from'@maya/shared-ui-react';
import { useOidcSession } from'@maya/shared-auth-react';
import { useNavItems } from'./components/layout';
import { HierarchyProvider } from'./features/hierarchy';
import { useUserProfile, profileDisplayInitials } from'./features/user-profile';

const DASHBOARD_API_URL = (import.meta.env.VITE_DASHBOARD_API_URL as string | undefined)
 ??'http://maya_dashboard_api.localhost';

// Code-splitting route-level: cada página carga en chunk separado bajo demanda.
const DashboardPage = lazy(() =>
 import('./pages/DashboardPage').then((m) => ({ default: m.DashboardPage })),
);
const DocumentEditorPage = lazy(() =>
 import('./pages/DocumentEditorPage').then((m) => ({ default: m.DocumentEditorPage })),
);
const DocumentValidationPage = lazy(() =>
 import('./pages/DocumentValidationPage').then((m) => ({ default: m.DocumentValidationPage })),
);
const DocumentPreviewPage = lazy(() =>
 import('./pages/DocumentPreviewPage').then((m) => ({ default: m.DocumentPreviewPage })),
);
const DocumentsPage = lazy(() =>
 import('./pages/DocumentsPage').then((m) => ({ default: m.DocumentsPage })),
);
const PlaceholderPage = lazy(() =>
 import('./pages/PlaceholderPage').then((m) => ({ default: m.PlaceholderPage })),
);
const TemplateEditPage = lazy(() =>
 import('./pages/TemplateEditPage').then((m) => ({ default: m.TemplateEditPage })),
);
const TemplateNewPage = lazy(() =>
 import('./pages/TemplateNewPage').then((m) => ({ default: m.TemplateNewPage })),
);
const TemplateReviewPage = lazy(() =>
 import('./pages/TemplateReviewPage').then((m) => ({ default: m.TemplateReviewPage })),
);
const TemplatesPage = lazy(() =>
 import('./pages/TemplatesPage').then((m) => ({ default: m.TemplatesPage })),
);

function AppRoutes() {
 return (<Suspense fallback={<SkeletonPage />}>
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
 <Route path="/templates/:id/review" element={<TemplateReviewPage />} />
 <Route path="*" element={<PlaceholderPage />} />
 </Routes>
 </Suspense>
 );
}

function AppWithLayout() {
 const { logout, user } = useOidcSession();
 const { profile } = useUserProfile();
 const navItems = useNavItems();
 const { i18n } = useTranslation();

 useEffect(() => {
 if (user?.locale) void i18n.changeLanguage(user.locale);
 }, [user?.locale, i18n]);

 useEffect(() => {
 const onStorage = (e: StorageEvent) => {
 if (e.key ==='locale' && e.newValue) void i18n.changeLanguage(e.newValue);
 };
 window.addEventListener('storage', onStorage);
 return () => window.removeEventListener('storage', onStorage);
 }, [i18n]);

 const userName = profile?.name?.trim() ??'';
 const userEmail = (profile?.email ?? user?.email) as string | undefined;
 const userInitials = profileDisplayInitials(profile);
 // Redirige al perfil del dashboard (donde vive el editor de perfil + idioma)
 const onProfile = () => {
 const dashboardOrigin = (import.meta.env.VITE_DASHBOARD_URL as string | undefined)
 ??'http://maya_dashboard.localhost';
 window.location.assign(`${dashboardOrigin}/profile`);
 };

 return (<AppLayout
 navItems={navItems}
 brandName="Maya DMS"
 brandVersion="v1.0"
 userName={userName}
 userEmail={userEmail}
 userInitials={userInitials}
 onLogout={logout}
 onProfile={onProfile}
 favoritesSlot={<SidebarFavorites label="Favoritas" dashboardApiUrl={DASHBOARD_API_URL} />}
 notificationsSlot={<NotificationsBell dashboardApiUrl={DASHBOARD_API_URL} />}
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
 return (<div className="flex items-center justify-center h-screen bg-surface text-on-surface-muted font-sans">
 Iniciando sesión…
 </div>
 );
 }

 if (!isOidcSignedIn) {
 return (<div className="flex items-center justify-center h-screen bg-surface text-on-surface-muted font-sans">
 Redirigiendo al inicio de sesión...
 </div>
 );
 }

 return (<HierarchyProvider>
 <AppWithLayout />
 </HierarchyProvider>
 );
}

export default App;
