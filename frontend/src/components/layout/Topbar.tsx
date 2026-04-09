import { Button } from '../../ui';
import { MoonIcon, SunIcon } from './navIcons';

type Props = {
  title: string;
  isDark: boolean;
  onToggleDark: () => void;
};

export function Topbar({ title, isDark, onToggleDark }: Props) {
  return (
    <header className="h-14 bg-ui-topbar dark:bg-ui-dark-topbar shadow-topbar flex items-center justify-between px-6 z-[200]">
      <h1 className="text-md font-semibold text-text-primary dark:text-text-dark-primary">{title}</h1>

      <div className="flex items-center gap-3">
        <Button
          type="button"
          variant="unstyled"
          onClick={onToggleDark}
          className="rounded-lg p-2 text-text-secondary transition-colors hover:bg-ui-body dark:text-text-dark-secondary dark:hover:bg-ui-dark-card"
          aria-label={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
          {isDark ? <SunIcon /> : <MoonIcon />}
        </Button>

        <div className="w-8 h-8 rounded-full bg-odoo-purple flex items-center justify-center">
          <span className="text-xs font-bold text-white">U</span>
        </div>
      </div>
    </header>
  );
}
