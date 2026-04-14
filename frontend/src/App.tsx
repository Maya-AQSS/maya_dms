import { useEffect, useMemo, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import {
  DashboardPage,
  DocumentEditorPage,
  DocumentsPage,
  GroupsPage,
  PlaceholderPage,
  TemplateEditPage,
  TemplatesPage,
} from './pages';

function App() {
  const [isDark, setIsDark] = useState(() => {
    return (
      localStorage.getItem('theme') === 'dark' ||
      (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
    );
  });

  const [sidebarCollapsed, setSidebarCollapsed] = useState(
    () => localStorage.getItem('sidebar_collapsed') === 'true',
  );

  const handleSidebarToggle = () => {
    const next = !sidebarCollapsed;
    setSidebarCollapsed(next);
    localStorage.setItem('sidebar_collapsed', String(next));
  };

  // Mobile nav drawer state
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  // Track whether the viewport is mobile (<768px) to remove the sidebar offset.
  const [isMobile, setIsMobile] = useState(() => window.innerWidth < 768);

  // location must be declared before the effects that depend on it.
  const location = useLocation();

  useEffect(() => {
    const onResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      if (!mobile) setMobileNavOpen(false); // auto-close drawer when widening to desktop
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  // Close mobile drawer on any navigation event
  useEffect(() => {
    setMobileNavOpen(false);
  }, [location.pathname]);

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

  return (
    <div className="min-h-screen bg-ui-body dark:bg-ui-dark-bg">
      <Sidebar
        collapsed={sidebarCollapsed}
        onToggle={handleSidebarToggle}
        mobileOpen={mobileNavOpen}
        onMobileClose={() => setMobileNavOpen(false)}
      />

      {/* On mobile: no left offset (sidebar is off-canvas). On desktop: sync with sidebar width. */}
      <div
        className="flex flex-col min-h-screen"
        style={{
          marginLeft: isMobile ? '0' : (sidebarCollapsed ? '3.5rem' : '16rem'),
          transition: isMobile ? 'none' : 'margin-left 200ms ease',
          '--sidebar-width': isMobile ? '0px' : (sidebarCollapsed ? '3.5rem' : '16rem'),
        } as React.CSSProperties}
      >
        <Topbar
          title={pageTitle}
          isDark={isDark}
          onToggleDark={handleToggleDark}
          onMobileMenuOpen={() => setMobileNavOpen(true)}
        />

        <main className="flex-1 overflow-auto">
          <Routes>
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="/dashboard" element={<DashboardPage />} />
            <Route path="/documents" element={<DocumentsPage />} />
            <Route path="/documents/:documentId/editor" element={<DocumentEditorPage />} />
            <Route path="/templates" element={<TemplatesPage />} />
            <Route path="/templates/:id/edit" element={<TemplateEditPage />} />
            <Route path="/groups" element={<GroupsPage />} />
            <Route path="/upload" element={<PlaceholderPage />} />
            <Route path="/search" element={<PlaceholderPage />} />
            <Route path="*" element={<PlaceholderPage />} />
          </Routes>
        </main>
      </div>
    </div>
  );
}

export default App;
