import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

// Mocks must be hoisted

/** Captura las props que App pasa a MayaAppShell para asserts de cableado. */
const capturedShellProps: Record<string, unknown> = {};

vi.mock('@ceedcv-maya/shared-layout-react', () => ({
  MayaAppShell: ({
    children,
    afterLayout,
    ...props
  }: { children: ReactNode; afterLayout?: ReactNode } & Record<string, unknown>) => {
    Object.assign(capturedShellProps, props, { afterLayout });
    return (
      <div data-testid="maya-app-shell">
        <span>{props.brandName as string}</span>
        {children}
        {afterLayout}
      </div>
    );
  },
}));

// shared-auth-react y shared-profile-react resuelven al shim de src/test/shims
// (alias de vitest.config.ts): useOidcSession devuelve sesión iniciada y
// resolveServiceUrl deriva https://<slug>.maya.test sin env vars.

vi.mock('@ceedcv-maya/shared-ui-react', () => ({
  Spinner: () => <div data-testid="spinner" />,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
  }),
}));

const mockNavigate = vi.fn();

vi.mock('react-router-dom', () => ({
  Navigate: ({ to }: { to: string }) => <div>Navigate to {to}</div>,
  // Route renderiza null: las páginas lazy no llegan a montarse, el cableado
  // de rutas se asierta a nivel de árbol (Routes presente como hijo del shell).
  Route: () => null,
  Routes: ({ children }: { children: ReactNode }) => (
    <div data-testid="app-routes">{children}</div>
  ),
  useNavigate: () => mockNavigate,
  useLocation: () => ({ pathname: '/dashboard' }),
  useParams: () => ({}),
}));

const mockNavItems = [{ id: 'dashboard', label: 'Dashboard', path: '/dashboard' }];

/** Captura las opciones con las que App invoca useNavItems (drawer de procesos). */
const capturedNavItemsOptions: { onOpenProcessesDrawer?: () => void } = {};

vi.mock('./components/layout/navItems', () => ({
  useNavItems: (options: { onOpenProcessesDrawer?: () => void } = {}) => {
    Object.assign(capturedNavItemsOptions, options);
    return mockNavItems;
  },
}));

/** Registra las props del drawer para asserts de apertura/cierre. */
const drawerProps: { open?: boolean; onClose?: () => void } = {};

vi.mock('./components/layout/ProcessesDrawer', () => ({
  ProcessesDrawer: ({ open, onClose }: { open: boolean; onClose: () => void }) => {
    drawerProps.open = open;
    drawerProps.onClose = onClose;
    return <div data-testid="processes-drawer" data-open={String(open)} />;
  },
}));

vi.mock('./features/hierarchy/context/HierarchyContext', () => ({
  HierarchyProvider: ({ children }: { children: ReactNode }) => (
    <div data-testid="hierarchy-provider">{children}</div>
  ),
}));

import App from './App';
import { act } from 'react';

describe('App', () => {
  beforeEach(() => {
    for (const key of Object.keys(capturedShellProps)) {
      delete capturedShellProps[key];
    }
    drawerProps.open = undefined;
    drawerProps.onClose = undefined;
    mockNavigate.mockClear();
  });

  it('renders MayaAppShell with the DocuCEED brand', () => {
    render(<App />);

    expect(screen.getByTestId('maya-app-shell')).toBeTruthy();
    expect(screen.getByText('DocuCEED')).toBeTruthy();
    expect(capturedShellProps.brandVersion).toBe('v1.0');
    expect(capturedShellProps.brandLogoUrl).toBe('/favicon.png');
  });

  it('gates access with the dms.login permission against the dashboard portal', () => {
    render(<App />);

    expect(capturedShellProps.loginPermission).toBe('dms.login');
    expect(capturedShellProps.portalLoginSlug).toBe('dashboard.login');
    // Resueltas vía resolveServiceUrl (env del contenedor o peer origin del shim).
    expect(String(capturedShellProps.dashboardUrl)).toMatch(/^https?:\/\/.+/);
    expect(String(capturedShellProps.dashboardApiUrl)).toMatch(/^https?:\/\/.+/);
  });

  it('passes the nav items from useNavItems', () => {
    render(<App />);

    expect(capturedShellProps.navItems).toBe(mockNavItems);
  });

  it('passes translated loading messages to the shell', () => {
    render(<App />);

    expect(capturedShellProps.loadingInitializingMessage).toBe('auth.initializing');
    expect(capturedShellProps.loadingRedirectingMessage).toBe('auth.redirecting');
    expect(capturedShellProps.loadingProfileMessage).toBe('auth.initializing');
    expect(capturedShellProps.loadingNoPermissionMessage).toBe('signingOutNoPermission');
  });

  it('renders the routes inside HierarchyProvider as shell children', () => {
    render(<App />);

    const hierarchy = screen.getByTestId('hierarchy-provider');
    expect(hierarchy).toBeTruthy();
    expect(hierarchy.querySelector('[data-testid="app-routes"]')).toBeTruthy();
  });

  it('mounts ProcessesDrawer in the afterLayout slot, closed by default', () => {
    render(<App />);

    expect(screen.getByTestId('processes-drawer').getAttribute('data-open')).toBe('false');
  });

  it('opens the drawer via the navItems callback and closes it on route change', () => {
    render(<App />);

    act(() => {
      capturedNavItemsOptions.onOpenProcessesDrawer?.();
    });
    expect(screen.getByTestId('processes-drawer').getAttribute('data-open')).toBe('true');

    act(() => {
      drawerProps.onClose?.();
    });
    expect(screen.getByTestId('processes-drawer').getAttribute('data-open')).toBe('false');
  });
});
