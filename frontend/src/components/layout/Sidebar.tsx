import { NavLink } from 'react-router-dom';
import { NAV_ITEMS } from './navItems';

interface SidebarProps {
  collapsed: boolean;
  onToggle: () => void;
  /** Controlled by App; true when the off-canvas mobile drawer is open. */
  mobileOpen: boolean;
  onMobileClose: () => void;
}

// Chevron-left — rotates 180° when collapsed so it always points toward the action.
function ChevronIcon({ collapsed }: { collapsed: boolean }) {
  return (
    <svg
      className={`w-4 h-4 transition-transform duration-200 ${collapsed ? 'rotate-180' : ''}`}
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
    </svg>
  );
}

export function Sidebar({ collapsed, onToggle, mobileOpen, onMobileClose }: SidebarProps) {
  // On mobile the drawer always opens fully expanded regardless of desktop collapsed state.
  const effectiveCollapsed = collapsed && !mobileOpen;

  return (
    <>
      {/* ── Mobile backdrop: semi-transparent overlay, tap to close ── */}
      {mobileOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-[99] md:hidden"
          onClick={onMobileClose}
          aria-hidden="true"
        />
      )}

      <aside
        className={[
          'fixed inset-y-0 left-0 bg-ui-sidebar flex flex-col z-[100] overflow-hidden',
          // Mobile: slide off-canvas when closed, slide in when open.
          // Desktop (md+): permanently visible.
          mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        ].join(' ')}
        style={{
          width: effectiveCollapsed ? '3.5rem' : '16rem',
          transition: 'width 200ms ease, transform 200ms ease',
        }}
      >
        {/* ── Brand header — collapse toggle lives here (Task 1) ── */}
        {/*
            Expanded: title left, chevron right (justify-between).
            Collapsed (desktop only): single chevron centered (justify-center).
        */}
        <div
          className={`h-14 flex items-center border-b border-white/10 shrink-0 px-3 ${
            effectiveCollapsed ? 'justify-center' : 'justify-between'
          }`}
        >
          {!effectiveCollapsed && (
            <span className="text-lg font-bold text-white tracking-wide whitespace-nowrap">
              Maya DMS
            </span>
          )}

          {/* Desktop-only toggle — hidden on mobile (drawer opened via Topbar hamburger) */}
          <button
            type="button"
            onClick={onToggle}
            className="hidden md:flex items-center justify-center w-7 h-7 rounded
              text-white/40 hover:text-white hover:bg-ui-sidebar-hover transition-colors"
            aria-label={collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'}
          >
            <ChevronIcon collapsed={collapsed} />
          </button>
        </div>

        {/* ── Navigation links ── */}
        <nav className="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto overflow-x-hidden">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.id}
              to={item.path}
              // Native tooltip shows the label when icons-only.
              title={effectiveCollapsed ? item.label : undefined}
              // Close mobile drawer on navigation.
              onClick={mobileOpen ? onMobileClose : undefined}
              className={({ isActive }: { isActive: boolean }) =>
                [
                  'w-full flex items-center rounded text-left text-sm font-medium transition-colors',
                  'focus-visible:ring-white/35 overflow-hidden whitespace-nowrap',
                  // py-3 on mobile (≥44px tap target), py-2 on desktop.
                  'py-3 md:py-2',
                  effectiveCollapsed ? 'justify-center px-0' : 'gap-3 px-3',
                  isActive
                    ? 'bg-ui-sidebar-active text-white'
                    : 'text-white/70 hover:bg-ui-sidebar-hover hover:text-white',
                ].join(' ')
              }
            >
              <item.icon />
              {!effectiveCollapsed && <span>{item.label}</span>}
            </NavLink>
          ))}
        </nav>

        {/* ── Footer: version string only (toggle moved to header) ── */}
        <div className="border-t border-white/10 shrink-0 px-4 py-3">
          {!effectiveCollapsed && (
            <p className="text-xs text-white/40 whitespace-nowrap">Maya DMS v1.0</p>
          )}
        </div>
      </aside>
    </>
  );
}
