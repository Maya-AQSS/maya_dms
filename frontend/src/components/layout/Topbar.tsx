import { Button } from '../../ui';
import { HamburgerIcon, MoonIcon, SunIcon } from './navIcons';

type Props = {
  title: string;
  isDark: boolean;
  onToggleDark: () => void;
  /** Opens the off-canvas sidebar drawer on mobile. */
  onMobileMenuOpen: () => void;
};

export function Topbar({ title, isDark, onToggleDark, onMobileMenuOpen }: Props) {
  return (
    <header className="h-14 bg-ui-topbar dark:bg-ui-dark-topbar shadow-topbar flex items-center justify-between px-6 z-[200]">
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
        {/* Dark-mode toggle — p-3 on mobile for ≥44px tap target, p-2 on desktop */}
        <Button
          type="button"
          variant="unstyled"
          onClick={onToggleDark}
          className="rounded-lg p-3 md:p-2 text-text-secondary transition-colors hover:bg-ui-body dark:text-text-dark-secondary dark:hover:bg-ui-dark-card"
          aria-label={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
          {isDark ? <SunIcon /> : <MoonIcon />}
        </Button>

        {/* Avatar — slightly larger on mobile for better tappability */}
        <div className="w-10 h-10 md:w-8 md:h-8 rounded-full bg-odoo-purple flex items-center justify-center">
          <span className="text-xs font-bold text-white">U</span>
        </div>
      </div>
    </header>
  );
}
