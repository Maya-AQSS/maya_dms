import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router-dom';
import { useUserProfile } from '../../features/user-profile';
import { DMS_PERMISSIONS } from '../../permissions';
import { fetchProcesses } from '../../api/processes';
import type { Process } from '../../types/processes';
import { getProcessIcon } from './processIcons';

interface ProcessesDrawerProps {
  open: boolean;
  onClose: () => void;
}

interface ProcessRowProps {
  process: Process;
  variant: 'parent' | 'child';
  canShow: boolean;
  onNavigate: () => void;
}

/**
 * Una fila del drawer: padre (visual completo) o hijo (compacto, icono más
 * pequeño, indentado por la barra del `<ul>` exterior).
 */
function ProcessRow({ process, variant, canShow, onNavigate }: ProcessRowProps) {
  const { t } = useTranslation('common');
  const label = process.alias?.trim() || process.name;
  const title = `${process.code} — ${process.name}`;
  const circleStyle = process.color
    ? { backgroundColor: withAlpha(process.color, 0x33) }
    : { backgroundColor: 'rgba(0,0,0,0.06)' };
  const iconStyle = process.color ? { color: process.color } : undefined;

  const isChild = variant === 'child';
  const circleSize = isChild
    ? 'w-5 h-5 [&>span>svg]:w-[12px] [&>span>svg]:h-[12px]'
    : 'w-7 h-7 [&>span>svg]:w-[18px] [&>span>svg]:h-[18px]';
  const baseClass = isChild
    ? 'flex items-center gap-2 px-2 py-1.5 rounded-md transition-colors'
    : 'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors';
  const enabledClass = 'hover:bg-ui-body dark:hover:bg-ui-dark-bg cursor-pointer';
  const disabledClass = 'opacity-60 cursor-not-allowed';
  const labelClass = isChild
    ? 'block text-xs font-medium text-text-secondary dark:text-text-dark-secondary truncate'
    : 'block text-sm font-medium text-text-primary dark:text-text-dark-primary truncate';

  const content = (
    <>
      <span
        className={`shrink-0 flex items-center justify-center rounded-full ${circleSize}`}
        style={circleStyle}
        aria-hidden="true"
      >
        <span
          style={iconStyle}
          className={iconStyle ? '' : 'text-text-secondary dark:text-text-dark-secondary'}
        >
          {getProcessIcon(process.icon)}
        </span>
      </span>
      <span className="flex-1 min-w-0">
        <span className={labelClass}>{label}</span>
        <span className="block text-2xs uppercase tracking-wider text-text-muted dark:text-text-dark-muted">
          {process.code}
        </span>
      </span>
    </>
  );

  if (!canShow) {
    return (
      <span
        title={t('processes.noShowPermission', { defaultValue: 'Sin permiso' })}
        className={`${baseClass} ${disabledClass}`}
      >
        {content}
      </span>
    );
  }

  return (
    <NavLink
      to={`/procesos/${process.id}`}
      title={title}
      onClick={onNavigate}
      className={({ isActive }) =>
        [baseClass, enabledClass, isActive ? 'bg-odoo-purple/10' : ''].join(' ').trim()
      }
    >
      {content}
    </NavLink>
  );
}

/** Hex `#RRGGBB` + alpha → `#RRGGBBAA`. */
function withAlpha(hex: string, alpha = 0x33): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return hex;
  return `#${m[1]}${alpha.toString(16).padStart(2, '0').toUpperCase()}`;
}

/**
 * Drawer flotante para navegar el catálogo de procesos del CEEDCV sin
 * sobrecargar el sidebar principal. Sale por la izquierda (desde el borde
 * derecho del sidebar) cubriendo el contenido con un velo translúcido.
 *
 * - Lista plana ordenada alfabéticamente por alias (locale es-ES).
 * - Buscador arriba (filtra por alias, name o code).
 * - `Esc` cierra el drawer; el overlay también es clickable para cerrar.
 * - Click en un proceso navega a `/procesos/:id` y cierra el drawer.
 *
 * El consumer es responsable de abrir/cerrar (estado externo) — esto lo
 * mantiene flexible para activarlo desde un NavLink, un atajo, etc.
 */
export function ProcessesDrawer({ open, onClose }: ProcessesDrawerProps) {
  const { t } = useTranslation('common');
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);
  const canShow = hasPermission(DMS_PERMISSIONS.processShow);
  const [processes, setProcesses] = useState<Process[]>([]);
  const [loading, setLoading] = useState(false);
  const [query, setQuery] = useState('');
  const inputRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    if (!open || !canIndex) return;

    let cancelled = false;
    setLoading(true);
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
  }, [open, canIndex]);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKeyDown);
    const timer = window.setTimeout(() => inputRef.current?.focus(), 80);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      window.clearTimeout(timer);
    };
  }, [open, onClose]);

  useEffect(() => {
    if (!open) setQuery('');
  }, [open]);

  type TreeNode = { process: Process; children: Process[] };

  const tree = useMemo<TreeNode[]>(() => {
    const compareByLabel = (a: Process, b: Process): number => {
      const la = (a.alias?.trim() || a.name).toLocaleLowerCase('es-ES');
      const lb = (b.alias?.trim() || b.name).toLocaleLowerCase('es-ES');
      return la.localeCompare(lb, 'es-ES');
    };

    const byId = new Map<string, TreeNode>();
    for (const p of processes) {
      byId.set(p.id, { process: p, children: [] });
    }

    const roots: TreeNode[] = [];
    const orphans: TreeNode[] = [];
    for (const p of processes) {
      const node = byId.get(p.id)!;
      if (p.process_parent_id) {
        const parent = byId.get(p.process_parent_id);
        if (parent) {
          parent.children.push(node.process);
        } else {
          orphans.push(node);
        }
      } else {
        roots.push(node);
      }
    }

    for (const node of roots) {
      node.children.sort(compareByLabel);
    }
    roots.sort((a, b) => compareByLabel(a.process, b.process));

    if (orphans.length) {
      orphans.sort((a, b) => compareByLabel(a.process, b.process));
      roots.push(...orphans);
    }

    return roots;
  }, [processes]);

  const filtered = useMemo<TreeNode[]>(() => {
    const q = query.trim().toLocaleLowerCase('es-ES');
    if (!q) return tree;
    const matches = (p: Process): boolean => {
      const haystack = `${p.alias ?? ''} ${p.name ?? ''} ${p.code ?? ''}`.toLocaleLowerCase('es-ES');
      return haystack.includes(q);
    };

    const result: TreeNode[] = [];
    for (const node of tree) {
      const parentMatches = matches(node.process);
      const matchedChildren = node.children.filter(matches);
      if (parentMatches) {
        // Parent hit: keep the whole branch so the user sees its subprocesses.
        result.push(node);
      } else if (matchedChildren.length) {
        // Child hit: keep parent as context, but show only the matching subprocesses.
        result.push({ process: node.process, children: matchedChildren });
      }
    }
    return result;
  }, [tree, query]);

  if (!open) return null;

  return (
    <>
      {/* Velo: cubre el contenido principal (no el sidebar) para enfocar el
          drawer sin oscurecer la navegación. */}
      <button
        type="button"
        aria-label={t('processes.drawer.closeOverlayAria', { defaultValue: 'Cerrar panel de procesos' })}
        onClick={onClose}
        className="fixed inset-0 z-[150] bg-black/30 backdrop-blur-[1px] animate-in fade-in cursor-default md:left-[var(--sidebar-w,17rem)]"
      />

      <aside
        role="dialog"
        aria-label={t('processes.drawer.title', { defaultValue: 'Procesos' })}
        className="fixed inset-y-0 z-[151] left-[var(--sidebar-w,17rem)] w-[min(360px,90vw)] bg-ui-card dark:bg-ui-dark-card border-r border-ui-border dark:border-ui-dark-border shadow-card-glass flex flex-col animate-in slide-in-from-left-2"
      >
        <header className="shrink-0 px-4 py-3 border-b border-ui-border-l dark:border-ui-dark-border flex items-center gap-3">
          <h2 className="text-base font-semibold font-display text-text-primary dark:text-text-dark-primary flex-1 min-w-0 truncate">
            {t('processes.drawer.title', { defaultValue: 'Procesos' })}
          </h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('processes.drawer.closeAria', { defaultValue: 'Cerrar' })}
            className="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-md text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:text-text-primary dark:hover:text-text-dark-primary transition-colors"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </header>

        <div className="shrink-0 px-4 py-3 border-b border-ui-border-l dark:border-ui-dark-border">
          <div className="relative">
            <span aria-hidden="true" className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted dark:text-text-dark-muted">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg>
            </span>
            <input
              ref={inputRef}
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t('processes.drawer.searchPlaceholder', { defaultValue: 'Buscar proceso o subproceso…' })}
              className="w-full pl-9 pr-3 py-2 text-sm bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border rounded-md text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/35"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto px-2 py-2">
          {!canIndex && (
            <p className="px-3 py-6 text-sm text-text-muted dark:text-text-dark-muted text-center">
              {t('processes.noShowPermission', { defaultValue: 'Sin permiso para listar procesos' })}
            </p>
          )}

          {canIndex && loading && (
            <ul className="space-y-1.5 px-1">
              {[0, 1, 2, 3, 4, 5].map((i) => (
                <li key={i} className="h-9 rounded-lg bg-ui-body dark:bg-ui-dark-bg animate-pulse" />
              ))}
            </ul>
          )}

          {canIndex && !loading && filtered.length === 0 && (
            <p className="px-3 py-6 text-sm text-text-muted dark:text-text-dark-muted text-center">
              {t('processes.drawer.empty', { defaultValue: 'No hay procesos que coincidan con la búsqueda' })}
            </p>
          )}

          {canIndex && !loading && filtered.length > 0 && (
            <ul className="space-y-2">
              {filtered.map((node) => (
                <li key={node.process.id}>
                  <ProcessRow process={node.process} variant="parent" canShow={canShow} onNavigate={onClose} />
                  {node.children.length > 0 && (
                    <ul className="mt-0.5 ml-4 border-l border-ui-border dark:border-ui-dark-border pl-2 space-y-0.5">
                      {node.children.map((child) => (
                        <li key={child.id}>
                          <ProcessRow
                            process={child}
                            variant="child"
                            canShow={canShow}
                            onNavigate={onClose}
                          />
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </aside>
    </>
  );
}
