import { useState } from 'react';
import './index.css';
import { NAV_ITEMS, Sidebar, Topbar } from './components/layout';
import { ActiveSection } from './pages';

function App() {
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

  return (
    <div className="min-h-screen bg-ui-body dark:bg-ui-dark-bg">
      <Sidebar active={activeSection} onNav={setActiveSection} />

      <div className="ml-64 flex flex-col min-h-screen">
        <Topbar title={pageTitle} isDark={isDark} onToggleDark={handleToggleDark} />

        <main className="flex-1 overflow-auto">
          <ActiveSection section={activeSection} />
        </main>
      </div>
    </div>
  );
}

export default App;
