import { useState } from 'react';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import { ActiveSection } from './pages';
import { useAuth } from '@maya/shared-auth-react';
import { HierarchyProvider } from './features/hierarchy';

function App() {
  const { isLoading, isAuthenticated, login } = useAuth();
  const [activeSection, setActiveSection] = useState('dashboard');
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

  const currentNav = NAV_ITEMS.find((n) => n.id === activeSection);
  const pageTitle = currentNav?.label ?? 'Maya DMS';

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-ui-body dark:bg-ui-dark-bg text-text-muted dark:text-text-dark-muted font-sans">
        Autenticando con Keycloak...
      </div>
    );
  }

  // If we are not loading but also not authenticated, we should trigger login
  // instead of rendering the app components that will fail fetching data.
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
        <Sidebar active={activeSection} onNav={setActiveSection} />

        <div className="ml-64 flex flex-col min-h-screen">
          <Topbar title={pageTitle} isDark={isDark} onToggleDark={handleToggleDark} />

          <main className="flex-1 overflow-auto">
            <ActiveSection section={activeSection} />
          </main>
        </div>
      </div>
    </HierarchyProvider>
  );
}

export default App;
