import { useMemo, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentsPage,
  PlaceholderPage,
  TemplateEditPage,
  TemplateNewPage,
  TemplatesPage,
} from './pages';
import { useAuth } from '@maya/shared-auth-react';
import { HierarchyProvider } from './features/hierarchy';

function App() {
  const { isLoading, isAuthenticated, login } = useAuth();
  const [isDark, setIsDark] = useState(() => {
    return (
      localStorage.getItem('theme') === 'dark' ||
      (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
    );
  });
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  const location = useLocation();

  const handleToggleDark = () => {
    const next = !isDark;
    setIsDark(next);
    document.documentElement.classList.toggle('dark', next);
    localStorage.setItem('theme', next ? 'dark' : 'light');
  };

  if (isDark) document.documentElement.classList.add('dark');

  const isEditorRoute = location.pathname.startsWith('/documents/') && location.pathname.endsWith('/editor');
  const currentNav = useMemo(
    () => NAV_ITEMS.find((n) => location.pathname === n.path || location.pathname.startsWith(`${n.path}/`)),
    [location.pathname],
  );
  const pageTitle = isEditorRoute ? 'Editor de Programación' : currentNav?.label ?? 'Maya DMS';

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Autenticando con Keycloak...
      </div>
    );
  }

  if (!isAuthenticated) {
    login();
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Redirigiendo al inicio de sesión...
      </div>
    );
  }

  return (
    <HierarchyProvider>
      <div className="h-screen overflow-hidden bg-ui-body dark:bg-ui-dark-bg">
        <Sidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((prev) => !prev)}
          mobileOpen={mobileOpen}
          onMobileClose={() => setMobileOpen(false)}
        />

        <div
          className="flex flex-col h-full transition-[margin] duration-200"
          style={{ marginLeft: mobileOpen ? '0' : sidebarCollapsed ? '3.5rem' : '16rem' }}
        >
          <Topbar
            title={pageTitle}
            isDark={isDark}
            onToggleDark={handleToggleDark}
            onMobileMenuOpen={() => setMobileOpen(true)}
          />

          <main className="flex-1 overflow-auto">
            <Routes>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/documents" element={<DocumentsPage />} />
              <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
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
