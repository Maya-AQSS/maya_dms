import { useEffect, useMemo, useState } from 'react';
import { NavLink } from 'react-router-dom';
import { fetchProcesses } from '../../api/processes';
import type { Process } from '../../types/processes';

const FOLDER_ICON = (
  <svg
    width="16"
    height="16"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
  >
    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
  </svg>
);

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

/**
 * Agrupa procesos planos por `parent_id`. Procesos top-level conservan el orden
 * de entrada (que viene ordenado por código desde el backend) y los hijos se
 * ordenan también por código.
 */
function buildTree(processes: Process[]): ProcessNode[] {
  const byId = new Map<string, ProcessNode>();
  const roots: ProcessNode[] = [];

  // Pre-instancia todos como nodos
  for (const p of processes) {
    byId.set(p.id, { ...p, children: [] });
  }

  for (const p of processes) {
    const node = byId.get(p.id)!;
    if (p.parent_id) {
      const parent = byId.get(p.parent_id);
      if (parent) parent.children.push(node);
      else roots.push(node); // Huérfano: tratar como top-level por seguridad
    } else {
      roots.push(node);
    }
  }

  return roots;
}

/**
 * Sección del aside con el listado dinámico de procesos del catálogo CEEDCV.
 * Renderiza top-level (PE0X / PC0X / PS0X) y sus sub-procesos tabulados debajo.
 *
 * Mientras carga muestra skeleton; si no hay procesos, no renderiza nada
 * (evita reservar espacio en vacío, mismo patrón que SidebarFavorites).
 */
export function SidebarProcesos({ label = 'Procesos' }: { label?: string }) {
  const [processes, setProcesses] = useState<Process[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [openId, setOpenId] = useState<string | null>(null);

  useEffect(() => {
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
  }, []);

  const tree = useMemo(() => buildTree(processes ?? []), [processes]);

  if (loading) {
    return (
      <div className="px-1 mt-4 pt-3 border-t border-text-inverse/8">
        <p className="text-xs font-semibold text-text-inverse/40 uppercase tracking-wider px-2 mb-1">
          {label}
        </p>
        <div className="space-y-1.5 px-2 py-1">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-8 rounded-lg bg-text-inverse/5 animate-pulse" />
          ))}
        </div>
      </div>
    );
  }

  if (!processes || processes.length === 0) return null;

  return (
    <div className="px-1 mt-4 pt-3 border-t border-text-inverse/8">
      <p className="text-xs font-semibold text-text-inverse/40 uppercase tracking-wider px-2 mb-1">
        {label}
      </p>
      {tree.map((p) => {
        const hasChildren = p.children.length > 0;
        const isOpen = openId === p.id;

        return (
          <div key={p.id}>
            <div className="group flex items-center gap-1">
              <NavLink
                to={`/procesos/${p.id}`}
                title={`${p.code} — ${p.name}`}
                className={({ isActive }: { isActive: boolean }) =>
                  [
                    'flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap overflow-hidden flex-1 min-w-0',
                    isActive
                      ? 'bg-text-inverse/10 text-text-inverse'
                      : 'text-text-inverse/70 hover:bg-text-inverse/8 hover:text-text-inverse',
                  ].join(' ')
                }
              >
                <span className="shrink-0 w-6 h-6 flex items-center justify-center text-text-inverse/60">
                  {FOLDER_ICON}
                </span>
                <span className="truncate">{p.name}</span>
              </NavLink>
              {hasChildren && (
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

            {hasChildren && isOpen && (
              <div className="ml-5 border-l border-text-inverse/10 pl-1 my-0.5 space-y-0.5">
                {p.children.map((child) => (
                  <NavLink
                    key={child.id}
                    to={`/procesos/${child.id}`}
                    title={`${child.code} — ${child.name}`}
                    className={({ isActive }: { isActive: boolean }) =>
                      [
                        'flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs transition-colors whitespace-nowrap overflow-hidden',
                        isActive
                          ? 'bg-text-inverse/10 text-text-inverse'
                          : 'text-text-inverse/55 hover:bg-text-inverse/8 hover:text-text-inverse/90',
                      ].join(' ')
                    }
                  >
                    <span className="shrink-0 w-3 flex items-center justify-center text-text-inverse/40">
                      {SUB_DOT}
                    </span>
                    <span className="truncate">{child.name}</span>
                  </NavLink>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
