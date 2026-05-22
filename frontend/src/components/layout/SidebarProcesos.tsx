import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router-dom';
import { useSidebarCollapsed } from '@maya/shared-layout-react';
import { useUserProfile } from '../../features/user-profile';
import { DMS_PERMISSIONS } from '../../permissions';
import { fetchProcesses } from '../../api/processes';
import type { Process } from '../../types/processes';
import { getProcessIcon } from './processIcons';

const SUB_DOT = (
  <svg width="6" height="6" viewBox="0 0 6 6" aria-hidden="true">
    <circle cx="3" cy="3" r="2" fill="currentColor" />
  </svg>
);

const CHEVRON = (
  <svg
    width="14"
    height="14"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <polyline points="9 18 15 12 9 6" />
  </svg>
);

type ProcessNode = Process & { children: Process[] };

/** Comparador locale-aware (ES) para ordenar por alias o name fallback. */
const compareByLabel = (a: Process, b: Process): number => {
  const la = (a.alias?.trim() || a.name).toLocaleLowerCase('es-ES');
  const lb = (b.alias?.trim() || b.name).toLocaleLowerCase('es-ES');
  return la.localeCompare(lb, 'es-ES');
};

/**
 * Oscurece un color hex (#RRGGBB) por un factor (0..1). Usado para
 * generar el segundo stop del gradient diagonal del círculo del proceso.
 * No usa CSS `color-mix` para mantener compatibilidad con navegadores
 * antiguos — la operación es determinista y se ejecuta una vez por render.
 */
function darkenHex(hex: string, amount = 0.28): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return hex;
  const v = parseInt(m[1], 16);
  const r = Math.max(0, Math.floor(((v >> 16) & 0xff) * (1 - amount)));
  const g = Math.max(0, Math.floor(((v >> 8) & 0xff) * (1 - amount)));
  const b = Math.max(0, Math.floor((v & 0xff) * (1 - amount)));
  return `#${[r, g, b].map((c) => c.toString(16).padStart(2, '0')).join('')}`;
}

/** Construye el linear-gradient diagonal del círculo del proceso. */
function circleGradient(color: string | null | undefined): string | undefined {
  if (!color) return undefined;
  return `linear-gradient(135deg, ${color} 0%, ${darkenHex(color, 0.28)} 100%)`;
}

function buildTree(processes: Process[]): ProcessNode[] {
  const byId = new Map<string, ProcessNode>();
  const roots: ProcessNode[] = [];

  for (const p of processes) {
    byId.set(p.id, { ...p, children: [] });
  }

  for (const p of processes) {
    const node = byId.get(p.id)!;
    if (p.process_parent_id) {
      const parent = byId.get(p.process_parent_id);
      if (parent) {
        parent.children.push(node);
      } else {
        roots.push(node);
      }
    } else {
      roots.push(node);
    }
  }

  // Orden alfabético en raíces y en cada nivel de hijos (locale-aware).
  roots.sort(compareByLabel);
  for (const root of roots) {
    root.children.sort(compareByLabel);
  }

  return roots;
}

/**
 * Sección del aside con el listado dinámico de procesos del catálogo CEEDCV.
 */
export function SidebarProcesos({ label = 'Procesos' }: { label?: string }) {
  const { t } = useTranslation('common');
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);
  const canShow = hasPermission(DMS_PERMISSIONS.processShow);
  const collapsed = useSidebarCollapsed();
  const [processes, setProcesses] = useState<Process[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [openId, setOpenId] = useState<string | null>(null);

  useEffect(() => {
    if (!canIndex) {
      setLoading(false);
      setProcesses([]);
      return;
    }

    let cancelled = false;
    fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        setProcesses(res.data ?? []);
      })
      .catch(() => {
        if (cancelled) return;
        setProcesses([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [canIndex]);

  const tree = useMemo(() => buildTree(processes ?? []), [processes]);

  if (!canIndex) {
    return null;
  }

  if (loading) {
    return (
      <div className={['mt-4 pt-3 border-t border-text-inverse/8', collapsed ? 'px-0' : 'px-1'].join(' ')}>
        {!collapsed && (
          <p className="text-xs font-semibold text-text-inverse/40 uppercase tracking-wider px-2 mb-1">
            {label}
          </p>
        )}
        <div className={['space-y-1.5 py-1', collapsed ? 'px-0' : 'px-2'].join(' ')}>
          {[0, 1, 2].map((i) => (
            <div
              key={i}
              className={['rounded-lg bg-text-inverse/5 animate-pulse', collapsed ? 'h-10 w-10 mx-auto rounded-xl' : 'h-8'].join(' ')}
            />
          ))}
        </div>
      </div>
    );
  }

  if (!processes || processes.length === 0) return null;

  // En modo expandido: py-2.5 + px-3.5 = misma altura que los NavLink principales.
  // En modo colapsado: justify-center sin label.
  const linkClass = (isActive: boolean, disabled: boolean) =>
    [
      'flex items-center rounded-xl text-sm font-medium transition-colors whitespace-nowrap overflow-hidden',
      collapsed ? 'justify-center w-10 h-10 mx-auto' : 'gap-3 px-3.5 py-2.5 flex-1 min-w-0',
      disabled
        ? 'opacity-50 cursor-not-allowed text-text-inverse/50'
        : isActive
          ? 'bg-text-inverse/10 text-text-inverse'
          : 'text-text-inverse/70 hover:bg-text-inverse/8 hover:text-text-inverse',
    ].join(' ');

  const renderProcessLink = (p: Process, className: string | ((isActive: boolean) => string), dot?: ReactNode) => {
    // `alias` es el texto user-facing (≤25 chars). `name` queda como nombre
    // completo de uso administrativo y como tooltip. El icono y color salen
    // de la BD — fallback a folder + inverse/60 si no hay datos.
    const displayLabel = p.alias?.trim() || p.name;
    const title = `${p.code} — ${p.name}`;
    // Círculo con degradado diagonal del color del proceso (base → 28% más
    // oscuro) y el icono blanco-translúcido encima. Mantiene el efecto de
    // profundidad que se perdió al cambiar de color directo del icono a
    // fondo plano. Fallback: tinte translúcido si no hay color en BD.
    const circleStyle = p.color
      ? { backgroundImage: circleGradient(p.color) }
      : { backgroundColor: 'rgba(255,255,255,0.10)' };
    const content = (
      <>
        {dot ?? (
          <span
            className="shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-text-inverse/95 [&>svg]:w-3.5 [&>svg]:h-3.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.18)]"
            style={circleStyle}
            aria-hidden="true"
          >
            {getProcessIcon(p.icon)}
          </span>
        )}
        {!collapsed && <span className="truncate">{displayLabel}</span>}
      </>
    );

    if (!canShow) {
      const disabledClass =
        typeof className === 'function' ? className(false) : className;

      return (
        <span className={disabledClass} title={t('processes.noShowPermission')}>
          {content}
        </span>
      );
    }

    const resolvedClass =
      typeof className === 'function'
        ? ({ isActive }: { isActive: boolean }) => className(isActive)
        : className;

    return (
      <NavLink to={`/procesos/${p.id}`} title={title} className={resolvedClass}>
        {content}
      </NavLink>
    );
  };

  return (
    <div className={['mt-4 pt-3 border-t border-text-inverse/8', collapsed ? 'px-0' : 'px-1'].join(' ')}>
      {!collapsed && (
        <p className="text-xs font-semibold text-text-inverse/40 uppercase tracking-wider px-2 mb-1">
          {label}
        </p>
      )}
      {tree.map((p) => {
        const hasChildren = p.children.length > 0;
        const isOpen = openId === p.id;

        return (
          <div key={p.id}>
            <div className={collapsed ? '' : 'group flex items-center gap-1'}>
              {renderProcessLink(
                p,
                (isActive) => linkClass(isActive, !canShow),
              )}
              {hasChildren && !collapsed && (
                <button
                  type="button"
                  onClick={() => setOpenId(isOpen ? null : p.id)}
                  aria-expanded={isOpen}
                  aria-label={isOpen ? 'Colapsar subprocesos' : 'Expandir subprocesos'}
                  className="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-text-inverse/50 hover:bg-text-inverse/8 hover:text-text-inverse transition-colors"
                >
                  <span
                    className={[
                      'flex items-center justify-center transition-transform',
                      isOpen ? 'rotate-90' : '',
                    ].join(' ')}
                  >
                    {CHEVRON}
                  </span>
                </button>
              )}
            </div>

            {hasChildren && isOpen && !collapsed && (
              <div className="ml-5 border-l border-text-inverse/10 pl-1 my-0.5 space-y-0.5">
                {p.children.map((child) => (
                  <div key={child.id}>
                    {renderProcessLink(
                      child,
                      (isActive) =>
                        [
                          'flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs transition-colors whitespace-nowrap overflow-hidden w-full',
                          !canShow
                            ? 'opacity-50 cursor-not-allowed text-text-inverse/50'
                            : isActive
                              ? 'bg-text-inverse/10 text-text-inverse'
                              : 'text-text-inverse/55 hover:bg-text-inverse/8 hover:text-text-inverse/90',
                        ].join(' '),
                      <span className="shrink-0 w-3 flex items-center justify-center text-text-inverse/40">
                        {SUB_DOT}
                      </span>,
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
