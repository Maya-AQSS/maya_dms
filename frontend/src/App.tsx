import { useMemo, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentsPage,
  PlaceholderPage,
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

  const handleToggleDark = () => {
    const next = !isDark;
    setIsDark(next);
    document.documentElement.classList.toggle('dark', next);
    localStorage.setItem('theme', next ? 'dark' : 'light');
  };

  if (isDark) document.documentElement.classList.add('dark');

  const location = useLocation();
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
      <div className="min-h-screen bg-ui-body dark:bg-ui-dark-bg">
        <Sidebar />

        <div className="ml-64 flex flex-col min-h-screen">
          <Topbar title={pageTitle} isDark={isDark} onToggleDark={handleToggleDark} />

          <main className="flex-1 overflow-auto">
            <Routes>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/documents" element={<DocumentsPage />} />
              <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
              <Route path="/templates" element={<TemplatesPage />} />
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
