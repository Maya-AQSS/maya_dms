import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router-dom';
import { useUserProfile } from '../../features/user-profile';
import { DMS_PERMISSIONS } from '../../permissions';
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

  const linkClass = (isActive: boolean, disabled: boolean) =>
    [
      'flex items-center gap-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap overflow-hidden flex-1 min-w-0',
      disabled
        ? 'opacity-50 cursor-not-allowed text-text-inverse/50'
        : isActive
          ? 'bg-text-inverse/10 text-text-inverse'
          : 'text-text-inverse/70 hover:bg-text-inverse/8 hover:text-text-inverse',
    ].join(' ');

  const renderProcessLink = (p: Process, className: string | ((isActive: boolean) => string), dot?: ReactNode) => {
    const title = `${p.code} — ${p.name}`;
    const content = (
      <>
        {dot ?? (
          <span className="shrink-0 w-6 h-6 flex items-center justify-center text-text-inverse/60">
            {FOLDER_ICON}
          </span>
        )}
        <span className="truncate">{p.name}</span>
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
              {renderProcessLink(
                p,
                (isActive) => linkClass(isActive, !canShow),
              )}
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
