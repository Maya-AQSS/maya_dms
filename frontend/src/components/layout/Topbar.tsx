import { MoonIcon, SunIcon } from './navIcons';

type User = {
  name?: string;
  preferred_username?: string;
};

type Props = {
  title: string;
  isDark: boolean;
  onToggleDark: () => void;
  user?: User;
  onLogout: () => void;
};

export function Topbar({ title, isDark, onToggleDark, user, onLogout }: Props) {
  const displayName = user?.name ?? user?.preferred_username ?? '';
  const initial = (displayName || 'U').charAt(0).toUpperCase();

  return (
    <header className="h-14 bg-ui-topbar dark:bg-ui-dark-topbar border-b border-ui-border-l dark:border-ui-dark-border shadow-topbar flex items-center justify-between px-6 z-[200]">
      <h1 className="text-md font-semibold text-text-primary dark:text-text-dark-primary">{title}</h1>

      <div className="flex items-center gap-3">
        <button
          onClick={onToggleDark}
          className="rounded-lg p-2 text-text-secondary transition-colors hover:bg-ui-body dark:text-text-dark-secondary dark:hover:bg-ui-dark-card"
          title={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
        >
          {isDark ? <SunIcon /> : <MoonIcon />}
        </button>

        <span className="text-text-primary dark:text-text-dark-primary font-medium hidden sm:inline">
          {displayName}
        </span>

        <div className="w-8 h-8 rounded-full bg-odoo-purple flex items-center justify-center">
          <span className="text-xs font-bold text-white">{initial}</span>
        </div>

        <button
          onClick={onLogout}
          className="border border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:text-text-primary dark:hover:text-text-dark-primary hover:border-text-secondary dark:hover:border-text-dark-secondary px-3 py-1 rounded text-sm transition-colors cursor-pointer bg-transparent"
        >
          Cerrar sesión
        </button>
      </div>
    </header>
  );
}
