import { NAV_ITEMS } from './navItems';

type Props = {
  active: string;
  onNav: (id: string) => void;
};

export function Sidebar({ active, onNav }: Props) {
  return (
    <aside className="fixed inset-y-0 left-0 w-64 bg-ui-sidebar flex flex-col z-[100]">
      <div className="h-14 flex items-center px-5 border-b border-white/10">
        <span className="text-lg font-bold text-white tracking-wide">Maya DMS</span>
      </div>

      <nav className="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto">
        {NAV_ITEMS.map((item) => {
          const isActive = active === item.id;
          return (
            <button
              key={item.id}
              type="button"
              onClick={() => onNav(item.id)}
              className={`w-full flex items-center gap-3 px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                isActive
                  ? 'bg-ui-sidebar-active text-white'
                  : 'text-white/70 hover:bg-ui-sidebar-hover hover:text-white'
              }`}
            >
              <item.icon />
              {item.label}
            </button>
          );
        })}
      </nav>

      <div className="border-t border-white/10 px-4 py-3">
        <p className="text-xs text-white/40">Maya DMS v1.0</p>
      </div>
    </aside>
  );
}
