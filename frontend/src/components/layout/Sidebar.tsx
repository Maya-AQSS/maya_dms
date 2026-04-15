import { NavLink } from 'react-router-dom';
import { NAV_ITEMS } from './navItems';

export function Sidebar() {
  return (
    <aside className="fixed inset-y-0 left-0 w-64 bg-ui-sidebar dark:bg-ui-dark-bg flex flex-col z-[100] border-r border-white/10 dark:border-ui-dark-border">
      <div className="h-14 flex items-center px-5 border-b border-white/10 dark:border-ui-dark-border-l">
        <span className="text-lg font-bold text-white tracking-wide">Maya DMS</span>
      </div>

      <nav className="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto">
        {NAV_ITEMS.map((item) => (
          <NavLink
            key={item.id}
            to={item.path}
            className={({ isActive }) =>
              `w-full flex items-center gap-3 rounded px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-white/35 ${
                isActive
                  ? 'bg-ui-sidebar-active text-white'
                  : 'text-white/70 hover:bg-ui-sidebar-hover hover:text-white'
              }`
            }
          >
            <item.icon />
            {item.label}
          </NavLink>
        ))}
      </nav>

      <div className="border-t border-white/10 px-4 py-3">
        <p className="text-xs text-white/40">Maya DMS v1.0</p>
      </div>
    </aside>
  );
}
