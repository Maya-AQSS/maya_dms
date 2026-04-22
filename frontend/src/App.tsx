import { useMemo, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentPreviewPage,
  DocumentsPage,
  PlaceholderPage,
  TemplateEditPage,
  TemplateNewPage,
  TemplatesPage,
} from './pages';
import { useOidcSession } from './auth/useOidcSession';
import { HierarchyProvider } from './features/hierarchy';
import { useDarkMode } from './hooks/useDarkMode';

function App() {
  const { isOidcLoading, isOidcSignedIn, beginSignIn, logout } = useOidcSession();
  const { isDark, toggle: handleToggleDark } = useDarkMode();
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  const location = useLocation();
  const isEditorRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/editor');
  const isDocumentPreviewRoute = /^\/documents\/[^/]+$/.test(location.pathname);
  const currentNav = useMemo(
    () => NAV_ITEMS.find((n) => location.pathname === n.path || location.pathname.startsWith(`${n.path}/`)),
    [location.pathname],
  );
  const pageTitle = isEditorRoute
    ? 'Editor de Programación'
    : isDocumentPreviewRoute
      ? 'Previsualización'
      : currentNav?.label ?? 'Maya DMS';

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
      <div className="min-h-screen bg-ui-body dark:bg-ui-dark-bg">
        <Sidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
          mobileOpen={mobileOpen}
          onMobileClose={() => setMobileOpen(false)}
        />

        <div className={`flex flex-col h-screen transition-[margin] duration-200 ${sidebarCollapsed ? 'md:ml-14' : 'md:ml-64'}`}>
          <Topbar
            title={pageTitle}
            isDark={isDark}
            onToggleDark={handleToggleDark}
            onLogout={logout}
            onMobileMenuOpen={() => setMobileOpen(true)}
          />

          <main className="flex-1 overflow-auto">
            <Routes>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/documents" element={<DocumentsPage />} />
              <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
              <Route path="/documents/:documentId" element={<DocumentPreviewPage />} />
              <Route path="/templates" element={<TemplatesPage />} />
              <Route path="/templates/new" element={<TemplateNewPage />} />
              <Route path="/templates/:id/edit" element={<TemplateEditPage />} />
              <Route path="/upload" element={<PlaceholderPage />} />
              <Route path="/search" element={<PlaceholderPage />} />
              <Route path="*" element={<PlaceholderPage />} />
            </Routes>
          </main>
        </div>
      </div>
    </HierarchyProvider>
  );
}

export default App;
