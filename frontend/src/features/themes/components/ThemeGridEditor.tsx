import { useCallback, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@ceedcv-maya/shared-ui-react';
import type { Theme, ThemeBlockType, ThemeLayoutRegion } from '../../../types/themes';
import { pageDimsMm } from '../pageSizes';
import { BLOCK_CATALOG, newRegion } from '../themeBlocks';
import { ThemeBlockPreview } from './ThemeBlockPreview';
import { BlockInspector, EmptyInspector, type BoxPatch } from './ThemeBlockInspector';
import { AbsoluteCanvas } from '../../../components/canvas/AbsoluteCanvas';
import './theme-grid.css';

interface ThemeGridEditorProps {
  theme: Theme;
  /** Persiste el layout (`regions[]`). Se llama tras cada drag/resize/edit. */
  onSave: (regions: ThemeLayoutRegion[]) => Promise<void>;
  /** Si está embebido en el wizard, suprime la barra de cabecera duplicada. */
  embedded?: boolean;
  /** Click en cerrar (vuelve al paso anterior). */
  onClose?: () => void;
}

/**
 * Normaliza una region al modelo `box` (mm). Si ya tiene `box`, la devuelve tal
 * cual; si sólo tiene `grid` legacy (celdas 12×52), la convierte usando las
 * dimensiones de la página. Devuelve `null` si no es posicionable.
 */
function toBoxRegion(
  region: ThemeLayoutRegion,
  page: { width: number; height: number },
): ThemeLayoutRegion | null {
  if (region.box) return region;
  if (region.grid) {
    const colW = page.width / 12;
    const rowH = page.height / 52;
    const g = region.grid;
    const { grid: _drop, ...rest } = region;
    return {
      ...rest,
      box: {
        x: Math.round(g.x * colW),
        y: Math.round(g.y * rowH),
        w: Math.round(g.w * colW),
        h: Math.round(g.h * rowH),
        z: g.z ?? 1,
      },
    };
  }
  return null;
}

export function ThemeGridEditor({ theme, onSave, embedded, onClose }: ThemeGridEditorProps) {
  const { t } = useTranslation(['themes', 'common']);
  const page = useMemo(() => pageDimsMm(theme.layout?.page?.size), [theme.layout?.page?.size]);

  // Separamos las regions posicionables (box/grid) de las legacy puras
  // (position/puck) que se preservan al guardar sin ser editables aquí.
  const initialRegions = theme.layout.regions ?? [];
  const [editableRegions, setEditableRegions] = useState<ThemeLayoutRegion[]>(
    () => initialRegions.map((r) => toBoxRegion(r, page)).filter((r): r is ThemeLayoutRegion => r !== null),
  );
  const legacyRegions = useMemo(
    () => initialRegions.filter((r) => !r.box && !r.grid),
    [initialRegions],
  );

  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [dirty, setDirty] = useState(false);

  const selected = useMemo(
    () => editableRegions.find((r) => r.id === selectedId) ?? null,
    [editableRegions, selectedId],
  );

  // Auto-save tras 700 ms sin cambios. Evita guardar en cada tick del drag.
  const saveTimerRef = useRef<number | null>(null);
  const scheduleSave = useCallback(
    (next: ThemeLayoutRegion[]) => {
      if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
      saveTimerRef.current = window.setTimeout(async () => {
        setSaving(true);
        setError(null);
        try {
          await onSave([...legacyRegions, ...next]);
          setDirty(false);
        } catch (e) {
          setError(e instanceof Error ? e.message : t('themes:layout.saveError'));
        } finally {
          setSaving(false);
        }
      }, 700);
    },
    [legacyRegions, onSave, t],
  );

  const updateRegions = useCallback(
    (mutator: (current: ThemeLayoutRegion[]) => ThemeLayoutRegion[]) => {
      setEditableRegions((current) => {
        const next = mutator(current);
        setDirty(true);
        scheduleSave(next);
        return next;
      });
    },
    [scheduleSave],
  );

  const updateBox = useCallback(
    (id: string, patch: BoxPatch) => {
      updateRegions((prev) =>
        prev.map((r) => (r.id === id && r.box ? { ...r, box: { ...r.box, ...patch } } : r)),
      );
    },
    [updateRegions],
  );

  const handleAddBlock = (type: ThemeBlockType) => {
    const maxZ = editableRegions.reduce((m, r) => Math.max(m, r.box?.z ?? 0), 0);
    const r = newRegion(type, maxZ, t);
    updateRegions((prev) => [...prev, r]);
    setSelectedId(r.id);
  };

  const handleRemove = (id: string) => {
    updateRegions((prev) => prev.filter((r) => r.id !== id));
    if (selectedId === id) setSelectedId(null);
  };

  const handleZUp = (id: string) => {
    updateRegions((prev) => {
      const maxZ = prev.reduce((m, r) => Math.max(m, r.box?.z ?? 0), 0);
      return prev.map((r) => (r.id === id && r.box ? { ...r, box: { ...r.box, z: maxZ + 1 } } : r));
    });
  };

  const handleZDown = (id: string) => {
    updateRegions((prev) =>
      prev.map((r) =>
        r.id === id && r.box ? { ...r, box: { ...r.box, z: Math.max(0, (r.box.z ?? 0) - 1) } } : r,
      ),
    );
  };

  const handleUpdateProps = (id: string, propsPatch: Record<string, unknown>) => {
    updateRegions((prev) =>
      prev.map((r) => (r.id === id ? { ...r, props: { ...(r.props ?? {}), ...propsPatch } } : r)),
    );
  };

  return (
    <div className="flex h-full min-h-0 flex-col">
      {!embedded && (
        <div className="flex shrink-0 items-center justify-between border-b border-ui-border bg-ui-body px-4 py-2">
          <div className="text-sm">
            <strong>{t('themes:layout.editorTitle')}</strong> — {theme.name}
          </div>
          {onClose && (
            <Button type="button" variant="ghost" size="sm" onClick={onClose}>
              {t('common:actions.close')}
            </Button>
          )}
        </div>
      )}

      <Toolbar onAddBlock={handleAddBlock} saving={saving} dirty={dirty} error={error} pageSize={theme.layout?.page?.size ?? 'A4'} />

      <div className="flex min-h-0 flex-1 overflow-hidden">
        {/* Canvas central — scrollable (mecánica genérica en AbsoluteCanvas) */}
        <AbsoluteCanvas
          pageMm={page}
          regions={editableRegions}
          selectedId={selectedId}
          onSelect={setSelectedId}
          onChangeBox={updateBox}
          onZUp={handleZUp}
          onZDown={handleZDown}
          onRemove={handleRemove}
          renderRegion={(r) => <ThemeBlockPreview region={r as ThemeLayoutRegion} />}
          surfaceStyle={{
            ['--font-body' as never]: theme.typography?.body_font ?? 'system-ui, sans-serif',
            ['--font-heading' as never]: theme.typography?.heading_font ?? 'system-ui, sans-serif',
          }}
          labels={{
            layerUp: t('themes:layerUp'),
            layerDown: t('themes:layerDown'),
            remove: t('common:actions.delete'),
            resize: t('common:actions.resize'),
          }}
        />

        {/* Inspector lateral */}
        <aside className="w-72 shrink-0 overflow-y-auto border-l border-ui-border bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
          {selected ? (
            <BlockInspector
              region={selected}
              themeId={theme.id}
              page={page}
              onUpdateProps={(p) => handleUpdateProps(selected.id, p)}
              onUpdateBox={(p) => updateBox(selected.id, p)}
            />
          ) : (
            <EmptyInspector />
          )}
        </aside>
      </div>
    </div>
  );
}

/* ─── Toolbar ──────────────────────────────────────────────────────────── */

interface ToolbarProps {
  onAddBlock: (type: ThemeBlockType) => void;
  saving: boolean;
  dirty: boolean;
  error: string | null;
  pageSize: string;
}

function Toolbar({ onAddBlock, saving, dirty, error, pageSize }: ToolbarProps) {
  const { t } = useTranslation(['themes', 'common']);
  const [open, setOpen] = useState(false);
  return (
    <div className="flex shrink-0 items-center gap-2 border-b border-ui-border bg-white px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-card">
      <div className="relative">
        <Button type="button" variant="primary" size="sm" onClick={() => setOpen((o) => !o)}>
          {t('themes:layout.addBlock')}
        </Button>
        {open && (
          <ul
            role="menu"
            className="absolute left-0 top-full z-50 mt-1 w-64 rounded border border-ui-border bg-white py-1 shadow-lg dark:border-ui-dark-border dark:bg-ui-dark-card"
            onMouseLeave={() => setOpen(false)}
          >
            {BLOCK_CATALOG.map((b) => (
              <li key={b.type}>
                <button
                  type="button"
                  role="menuitem"
                  className="block w-full px-3 py-1.5 text-left text-sm hover:bg-ui-body dark:hover:bg-ui-dark-card"
                  onClick={() => {
                    onAddBlock(b.type);
                    setOpen(false);
                  }}
                >
                  {t(`themes:${b.labelKey}`)}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <span className="text-xs text-text-muted">
        {t('themes:layout.pageHint', { pageSize })}
      </span>

      <div className="ml-auto flex items-center gap-3 text-xs">
        {saving ? (
          <span className="text-text-muted">{t('common:saving')}</span>
        ) : dirty ? (
          <span className="text-warning-dark">{t('common:status.pendingChanges')}</span>
        ) : (
          <span className="text-success-dark">{t('common:status.saved')}</span>
        )}
        {error && <span className="text-danger-dark">⚠ {error}</span>}
      </div>
    </div>
  );
}
