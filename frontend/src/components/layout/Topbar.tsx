import { HamburgerIcon, MoonIcon, SunIcon } from './navIcons';

type User = {
  name?: string;
  preferred_username?: string;
};

type Props = {
  title: string;
  isDark: boolean;
  onToggleDark: () => void;
  /** Opens the off-canvas sidebar drawer on mobile. */
  onMobileMenuOpen: () => void;
  user?: User;
  onLogout: () => void;
};

export function Topbar({ title, isDark, onToggleDark, user, onLogout, onMobileMenuOpen }: Props) {
  const displayName = user?.name ?? user?.preferred_username ?? '';
  const initial = (displayName || 'U').charAt(0).toUpperCase();

  return (
    <header className="relative h-14 bg-ui-topbar dark:bg-ui-dark-topbar border-b border-ui-border-l dark:border-ui-dark-border shadow-topbar flex items-center justify-between px-6 z-[200]">
      <div className="flex items-center gap-2">
        {/* Hamburger button — visible only on mobile (<md), hidden on desktop */}
        <button
          type="button"
          className="md:hidden flex items-center justify-center w-11 h-11 -ml-2 rounded-lg
            text-text-secondary hover:bg-ui-body dark:text-text-dark-secondary dark:hover:bg-ui-dark-card
            transition-colors"
          onClick={onMobileMenuOpen}
          aria-label="Abrir menú lateral"
        >
          <HamburgerIcon />
        </button>

        <h1 className="text-md font-semibold text-text-primary dark:text-text-dark-primary">
          {title}
        </h1>
      </div>

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

        {/* Avatar — slightly larger on mobile for better tappability */}
        <div className="w-10 h-10 md:w-8 md:h-8 rounded-full bg-odoo-purple flex items-center justify-center">
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
