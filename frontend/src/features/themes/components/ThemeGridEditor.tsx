import { useCallback, useMemo, useRef, useState } from 'react';
import { Responsive, WidthProvider, type Layout } from 'react-grid-layout';
import { useTranslation } from 'react-i18next';
import { Button, FieldLabel, Select, TextInput } from '@ceedcv-maya/shared-ui-react';
import type { Theme, ThemeBlockType, ThemeLayoutRegion } from '../../../types/themes';
import { uploadThemeImage, ingestThemeImageUrl } from '../../../api/themes';
import './theme-grid.css';

const ResponsiveGridLayout = WidthProvider(Responsive);

/* ─── Constantes de rejilla ────────────────────────────────────────────────
 * Página A4 con márgenes de 1.5 cm → ~18×26 cm útiles. La rejilla la
 * dividimos en 12 columnas (~1.5 cm/col) y filas de 0.5 cm cada una para
 * dar precisión sin sobrecargar el snap. El editor por defecto reserva
 * 52 filas (26 cm); el usuario puede crecer hacia abajo sin tope.
 */
const COLS = 12;
/* Para que el editor sea pixel-perfect WYSIWYG con el PDF (29.7 cm de alto a
 * 96 dpi = 1123 px), `ROW_HEIGHT * GRID_ROWS` debe sumar ese alto. Como además
 * react-grid-layout añade `marginY` entre filas, ponemos margin a 0 y elegimos
 * un ROW_HEIGHT que divida 1123 entre 52 filas. */
const ROW_HEIGHT = Math.round(1123 / 52); // ≈ 21.6 → 22 px en pantalla
const GRID_ROWS = 52;
const GRID_MARGIN: [number, number] = [0, 0];
const ASPECT_A4 = 297 / 210; // alto/ancho

interface ThemeGridEditorProps {
  theme: Theme;
  /** Persiste el layout (`regions[]`). Se llama tras cada drag/resize/edit. */
  onSave: (regions: ThemeLayoutRegion[]) => Promise<void>;
  /** Si está embebido en el wizard, suprime la barra de cabecera duplicada. */
  embedded?: boolean;
  /** Click en cerrar (vuelve al paso anterior). */
  onClose?: () => void;
}

/** Catálogo de bloques disponibles desde el toolbar. */
const BLOCK_CATALOG: Array<{
  type: ThemeBlockType;
  label: string;
  defaultSize: { w: number; h: number };
  defaultProps: Record<string, unknown>;
}> = [
  {
    type: 'content_slot',
    label: 'Contenido del documento',
    defaultSize: { w: 12, h: 30 },
    defaultProps: { label: 'Aquí se carga el cuerpo del documento' },
  },
  { type: 'text', label: 'Texto', defaultSize: { w: 4, h: 2 }, defaultProps: { text: 'Texto', size: 9, color: '#333333', align: 'left' } },
  { type: 'image', label: 'Imagen', defaultSize: { w: 4, h: 4 }, defaultProps: { alt: '', opacity: 1, objectFit: 'contain' } },
  { type: 'page_number', label: 'Nº de página', defaultSize: { w: 3, h: 2 }, defaultProps: { format: 'page-of-pages', align: 'right' } },
  { type: 'date', label: 'Fecha', defaultSize: { w: 3, h: 2 }, defaultProps: { format: 'short', align: 'left' } },
];

function newRegion(type: ThemeBlockType, occupiedZ: number): ThemeLayoutRegion {
  const def = BLOCK_CATALOG.find((b) => b.type === type)!;
  return {
    id: `r-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
    type,
    grid: { x: 0, y: 0, w: def.defaultSize.w, h: def.defaultSize.h, z: occupiedZ + 1 },
    props: { ...def.defaultProps },
  };
}

/** Toma el subset de regions con `grid` y las devuelve listas para react-grid-layout. */
function regionsToLayout(regions: ThemeLayoutRegion[]): Layout[] {
  return regions
    .filter((r) => r.grid)
    .map((r) => ({
      i: r.id,
      x: r.grid!.x,
      y: r.grid!.y,
      w: r.grid!.w,
      h: r.grid!.h,
      // El z-index lo aplicamos vía CSS por estilo inline, no por la prop del
      // grid (react-grid-layout no lo soporta nativamente).
    }));
}

export function ThemeGridEditor({ theme, onSave, embedded, onClose }: ThemeGridEditorProps) {
  const { t } = useTranslation(['themes', 'common']);
  // Sólo trabajamos sobre regions del nuevo modelo (con `grid`). El resto
  // (regions legacy de Puck) se mantienen aparte y se reescriben tal cual al
  // guardar — no se pierden, simplemente no son editables visualmente aquí.
  const initialRegions = theme.layout.regions ?? [];
  const [editableRegions, setEditableRegions] = useState<ThemeLayoutRegion[]>(
    initialRegions.filter((r) => r.grid),
  );
  const legacyRegions = useMemo(
    () => initialRegions.filter((r) => !r.grid),
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
          setError(e instanceof Error ? e.message : 'Error guardando el layout');
        } finally {
          setSaving(false);
        }
      }, 700);
    },
    [legacyRegions, onSave],
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

  const handleAddBlock = (type: ThemeBlockType) => {
    const maxZ = editableRegions.reduce((m, r) => Math.max(m, r.grid?.z ?? 0), 0);
    const r = newRegion(type, maxZ);
    updateRegions((prev) => [...prev, r]);
    setSelectedId(r.id);
  };

  const handleLayoutChange = (current: Layout[]) => {
    // El callback dispara también durante el primer mount; sólo aplicamos si
    // hay diferencias reales para evitar un save inicial sin cambios.
    let changed = false;
    const byI = Object.fromEntries(current.map((l) => [l.i, l]));
    const next = editableRegions.map((r) => {
      const pos = byI[r.id];
      if (!pos || !r.grid) return r;
      if (
        pos.x === r.grid.x &&
        pos.y === r.grid.y &&
        pos.w === r.grid.w &&
        pos.h === r.grid.h
      )
        return r;
      changed = true;
      return { ...r, grid: { ...r.grid, x: pos.x, y: pos.y, w: pos.w, h: pos.h } };
    });
    if (changed) updateRegions(() => next);
  };

  const handleRemove = (id: string) => {
    updateRegions((prev) => prev.filter((r) => r.id !== id));
    if (selectedId === id) setSelectedId(null);
  };

  const handleZUp = (id: string) => {
    updateRegions((prev) => {
      const maxZ = prev.reduce((m, r) => Math.max(m, r.grid?.z ?? 0), 0);
      return prev.map((r) =>
        r.id === id && r.grid ? { ...r, grid: { ...r.grid, z: maxZ + 1 } } : r,
      );
    });
  };

  const handleZDown = (id: string) => {
    updateRegions((prev) =>
      prev.map((r) =>
        r.id === id && r.grid
          ? { ...r, grid: { ...r.grid, z: Math.max(0, (r.grid.z ?? 0) - 1) } }
          : r,
      ),
    );
  };

  const handleUpdateProps = (id: string, propsPatch: Record<string, unknown>) => {
    updateRegions((prev) =>
      prev.map((r) =>
        r.id === id ? { ...r, props: { ...(r.props ?? {}), ...propsPatch } } : r,
      ),
    );
  };

  const handleUpdateGrid = (id: string, patch: { w?: number; h?: number; x?: number; y?: number }) => {
    updateRegions((prev) =>
      prev.map((r) =>
        r.id === id && r.grid ? { ...r, grid: { ...r.grid, ...patch } } : r,
      ),
    );
  };

  const layout = useMemo(() => regionsToLayout(editableRegions), [editableRegions]);

  return (
    <div className="flex h-full min-h-0 flex-col">
      {!embedded && (
        <div className="flex shrink-0 items-center justify-between border-b border-ui-border bg-ui-body px-4 py-2">
          <div className="text-sm">
            <strong>Editor de layout</strong> — {theme.name}
          </div>
          {onClose && (
            <Button type="button" variant="ghost" size="sm" onClick={onClose}>
              Cerrar
            </Button>
          )}
        </div>
      )}

      <Toolbar
        onAddBlock={handleAddBlock}
        saving={saving}
        dirty={dirty}
        error={error}
      />

      <div className="flex flex-1 min-h-0 overflow-hidden">
        {/* Canvas central — scrollable verticalmente */}
        <div className="flex-1 min-w-0 overflow-auto bg-ui-body p-6 dark:bg-ui-dark-bg">
          <div
            className="theme-grid-page mx-auto bg-white shadow"
            style={{
              // Ancho real de un A4 a 96dpi (21cm = 794px). Antes usábamos
              // 900px que daba ~13% más de ancho que el PDF final, haciendo
              // que un texto que cabe en una línea en el editor pueda
              // saltar a dos en el PDF. A 794px el wrap del editor coincide
              // 1:1 con el del PDF.
              maxWidth: '794px',
              aspectRatio: `1 / ${ASPECT_A4}`,
              /* 52 filas × ROW_HEIGHT = 1144 px ≈ 29.7 cm. Sin padding
                 extra: el alto del canvas refleja exactamente el alto del
                 A4 — un bloque en y=49 aparece visualmente al 94% en el
                 editor igual que en el PDF. */
              minHeight: `${ROW_HEIGHT * GRID_ROWS}px`,
              // Inyectamos la tipografía del theme en la canvas para que el
              // editor sea WYSIWYG: los bloques de texto (`.theme-grid-slot`)
              // usan `var(--font-body)` — sin esto verían system-ui mientras
              // que el PDF real usa la fuente del theme, lo que cambia el
              // ancho del texto y rompe la previsualización fiel.
              ['--font-body' as never]: theme.typography?.body_font ?? 'system-ui, sans-serif',
              ['--font-heading' as never]: theme.typography?.heading_font ?? 'system-ui, sans-serif',
            }}
            onClick={(e) => {
              if (e.target === e.currentTarget) setSelectedId(null);
            }}
          >
            <ResponsiveGridLayout
              className="theme-grid"
              layouts={{ lg: layout, md: layout, sm: layout, xs: layout, xxs: layout }}
              breakpoints={{ lg: 0, md: 0, sm: 0, xs: 0, xxs: 0 }}
              cols={{ lg: COLS, md: COLS, sm: COLS, xs: COLS, xxs: COLS }}
              rowHeight={ROW_HEIGHT}
              margin={GRID_MARGIN}
              isDraggable
              isResizable
              compactType={null}
              preventCollision={false}
              allowOverlap
              onLayoutChange={handleLayoutChange}
              draggableCancel=".theme-grid-block-controls,.theme-grid-block-controls *"
            >
              {editableRegions.map((r) => {
                const isSelected = r.id === selectedId;
                return (
                  <div
                    key={r.id}
                    className={`theme-grid-block ${isSelected ? 'is-selected' : ''}`}
                    style={{ zIndex: r.grid?.z ?? 1 }}
                    onMouseDown={() => setSelectedId(r.id)}
                  >
                    <BlockPreview region={r} />
                    <div className="theme-grid-block-controls">
                      <button
                        type="button"
                        title={t('themes:layerUp')}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleZUp(r.id);
                        }}
                      >
                        ↑
                      </button>
                      <button
                        type="button"
                        title={t('themes:layerDown')}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleZDown(r.id);
                        }}
                      >
                        ↓
                      </button>
                      <button
                        type="button"
                        title={t('common:actions.delete')}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleRemove(r.id);
                        }}
                      >
                        ✕
                      </button>
                    </div>
                  </div>
                );
              })}
            </ResponsiveGridLayout>
          </div>
        </div>

        {/* Inspector lateral — overflow propio */}
        <aside className="w-72 shrink-0 overflow-y-auto border-l border-ui-border bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
          {selected ? (
            <BlockInspector
              region={selected}
              themeId={theme.id}
              onUpdateProps={(p) => handleUpdateProps(selected.id, p)}
              onUpdateGrid={(p) => handleUpdateGrid(selected.id, p)}
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
}

function Toolbar({ onAddBlock, saving, dirty, error }: ToolbarProps) {
  const [open, setOpen] = useState(false);
  return (
    <div className="flex shrink-0 items-center gap-2 border-b border-ui-border bg-white px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-card">
      <div className="relative">
        <Button
          type="button"
          variant="primary"
          size="sm"
          onClick={() => setOpen((o) => !o)}
        >
          + Añadir bloque ▾
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
                  {b.label}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <span className="text-xs text-text-muted">
        Rejilla de {COLS} columnas. Arrastra para colocar, redimensiona desde la esquina.
      </span>

      <div className="ml-auto flex items-center gap-3 text-xs">
        {saving ? (
          <span className="text-text-muted">Guardando…</span>
        ) : dirty ? (
          <span className="text-warning-dark">Cambios pendientes</span>
        ) : (
          <span className="text-success-dark">Guardado</span>
        )}
        {error && <span className="text-danger-dark">⚠ {error}</span>}
      </div>
    </div>
  );
}

/* ─── Preview de cada bloque dentro de la rejilla ─────────────────────── */

function BlockPreview({ region }: { region: ThemeLayoutRegion }) {
  const { t } = useTranslation('themes');
  const p = region.props ?? {};
  switch (region.type) {
    case 'content_slot':
      return (
        <div className="theme-grid-slot theme-grid-slot--content">
          <span>{(p.label as string) ?? 'Contenido del documento'}</span>
          <small>{t('editor.blocksPlaceholder')}</small>
        </div>
      );
    case 'text':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--text"
          style={{
            fontSize: `${(p.size as number) ?? 9}pt`,
            color: (p.color as string) ?? '#333',
            textAlign: ((p.align as string) ?? 'left') as React.CSSProperties['textAlign'],
          }}
        >
          {(p.text as string) ?? 'Texto'}
        </div>
      );
    case 'image': {
      const url = (p.srcUrl as string) || null;
      return (
        <div
          className="theme-grid-slot theme-grid-slot--image"
          style={{
            opacity: (p.opacity as number) ?? 1,
            transform: `rotate(${(p.rotate as number) ?? 0}deg)`,
          }}
        >
          {url ? (
            <img
              src={url}
              alt={(p.alt as string) ?? ''}
              style={{
                width: '100%',
                height: '100%',
                objectFit: ((p.objectFit as string) ?? 'contain') as React.CSSProperties['objectFit'],
              }}
            />
          ) : (
            <span className="text-text-muted">{t('editor.imageEmpty')}</span>
          )}
        </div>
      );
    }
    case 'page_number':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--meta"
          style={{ textAlign: ((p.align as string) ?? 'right') as React.CSSProperties['textAlign'] }}
        >
          {(p.format as string) === 'page-of-pages' ? t('editor.pageNumberOfTotal') : t('editor.pageNumber')}
        </div>
      );
    case 'date':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--meta"
          style={{ textAlign: ((p.align as string) ?? 'left') as React.CSSProperties['textAlign'] }}
        >
          {(p.format as string) === 'long' ? '1 de enero de 2026' : '01/01/2026'}
        </div>
      );
    default:
      return (
        <div className="theme-grid-slot theme-grid-slot--legacy">
          <span>{region.type}</span>
          <small>(bloque legacy)</small>
        </div>
      );
  }
}

/* ─── Inspector lateral ───────────────────────────────────────────────── */

function EmptyInspector() {
  const { t } = useTranslation('themes');
  return (
    <div className="space-y-3 text-sm text-text-muted">
      <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
        Inspector
      </h3>
      <p>
        Selecciona un bloque del lienzo para editar sus propiedades, o añade uno nuevo
        desde el botón <strong>{t('editor.addBlock')}</strong>.
      </p>
      <ul className="list-disc space-y-1 pl-4">
        <li>Arrastra cualquier bloque para moverlo.</li>
        <li>Redimensiona desde la esquina inferior derecha.</li>
        <li>Usa <strong>↑ / ↓</strong> en el bloque para cambiar de capa (z-index).</li>
        <li>Los bloques pueden solaparse.</li>
      </ul>
    </div>
  );
}

interface BlockInspectorProps {
  region: ThemeLayoutRegion;
  themeId: string;
  onUpdateProps: (patch: Record<string, unknown>) => void;
  onUpdateGrid: (patch: { w?: number; h?: number; x?: number; y?: number }) => void;
}

function BlockInspector({ region, themeId, onUpdateProps, onUpdateGrid }: BlockInspectorProps) {
  const { t } = useTranslation('themes');
  const p = region.props ?? {};
  const grid = region.grid;

  return (
    <div className="space-y-4 text-sm">
      <header>
        <h3 className="text-base font-semibold">{labelForType(region.type)}</h3>
        <p className="text-xs text-text-muted">ID: {region.id}</p>
      </header>

      {grid && (
        <section className="space-y-2">
          <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">
            Posición y tamaño
          </h4>
          <div className="grid grid-cols-2 gap-2">
            <NumField label="Columna (x)" value={grid.x} min={0} max={COLS - 1} onChange={(v) => onUpdateGrid({ x: v })} />
            <NumField label="Fila (y)" value={grid.y} min={0} onChange={(v) => onUpdateGrid({ y: v })} />
            <NumField label="Ancho (cols)" value={grid.w} min={1} max={COLS} onChange={(v) => onUpdateGrid({ w: v })} />
            <NumField label="Alto (filas)" value={grid.h} min={1} onChange={(v) => onUpdateGrid({ h: v })} />
          </div>
          <p className="text-xs text-text-muted">
            Capa: <strong>{grid.z ?? 1}</strong> (cambia desde los botones ↑/↓ del bloque)
          </p>
        </section>
      )}

      <section className="space-y-2">
        <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">
          Propiedades
        </h4>
        {renderTypeFields(region.type, p, onUpdateProps, t, themeId)}
      </section>
    </div>
  );
}

function renderTypeFields(
  type: ThemeBlockType,
  p: Record<string, unknown>,
  onChange: (patch: Record<string, unknown>) => void,
  t: (key: string) => string,
  themeId?: string,
): React.ReactNode {
  switch (type) {
    case 'text':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-text">Contenido</FieldLabel>
            <TextInput
              id="blk-text"
              value={(p.text as string) ?? ''}
              onChange={(e) => onChange({ text: e.target.value })}
            />
          </div>
          <NumField label="Tamaño (pt)" value={(p.size as number) ?? 9} min={6} max={48} onChange={(v) => onChange({ size: v })} />
          <div>
            <FieldLabel htmlFor="blk-color">Color</FieldLabel>
            <input
              id="blk-color"
              type="color"
              value={(p.color as string) ?? '#333333'}
              onChange={(e) => onChange({ color: e.target.value })}
              className="h-8 w-full cursor-pointer rounded border border-ui-border"
            />
          </div>
          <AlignField value={(p.align as string) ?? 'left'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'image':
      return themeId ? (
        <ImageBlockEditor
          props={p}
          themeId={themeId}
          onChange={onChange}
        />
      ) : (
        <p className="text-xs text-text-muted">ID de theme no disponible</p>
      );
    case 'page_number':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-pn-fmt">Formato</FieldLabel>
            <Select
              id="blk-pn-fmt"
              value={(p.format as string) ?? 'page-of-pages'}
              onChange={(e) => onChange({ format: e.target.value })}
            >
              <option value="page">{t('editor.pageNumber')}</option>
              <option value="page-of-pages">{t('editor.pageNumberOfTotal')}</option>
            </Select>
          </div>
          <AlignField value={(p.align as string) ?? 'right'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'date':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-date-fmt">Formato</FieldLabel>
            <Select
              id="blk-date-fmt"
              value={(p.format as string) ?? 'short'}
              onChange={(e) => onChange({ format: e.target.value })}
            >
              <option value="short">Corto (01/01/2026)</option>
              <option value="long">Largo (1 de enero de 2026)</option>
            </Select>
          </div>
          <AlignField value={(p.align as string) ?? 'left'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'content_slot':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-label">Etiqueta visible en el editor</FieldLabel>
            <TextInput
              id="blk-label"
              value={(p.label as string) ?? ''}
              onChange={(e) => onChange({ label: e.target.value })}
            />
          </div>
          <p className="text-xs text-text-muted">
            Este bloque marca el área donde el render del documento inserta su contenido.
            Sólo debería existir uno por theme.
          </p>
        </>
      );
    default:
      return <p className="text-xs text-text-muted">Sin propiedades editables.</p>;
  }
}

interface NumFieldProps {
  label: string;
  value: number;
  min?: number;
  max?: number;
  step?: number;
  onChange: (v: number) => void;
}

function NumField({ label, value, min, max, step, onChange }: NumFieldProps) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
      <TextInput
        type="number"
        value={String(value)}
        min={min}
        max={max}
        step={step}
        onChange={(e) => {
          const v = step ? Number.parseFloat(e.target.value) : Number.parseInt(e.target.value, 10);
          if (!Number.isNaN(v)) onChange(v);
        }}
      />
    </div>
  );
}

interface AlignFieldProps {
  value: string;
  onChange: (v: string) => void;
}

function AlignField({ value, onChange }: AlignFieldProps) {
  const { t } = useTranslation('themes');
  return (
    <div>
      <FieldLabel htmlFor="blk-align">{t('editor.alignment')}</FieldLabel>
      <Select id="blk-align" value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="left">Izquierda</option>
        <option value="center">Centro</option>
        <option value="right">Derecha</option>
      </Select>
    </div>
  );
}

/* ─── Editor de bloques de imagen ───────────────────────────────────── */

interface ImageBlockEditorProps {
  props: Record<string, unknown>;
  themeId: string;
  onChange: (patch: Record<string, unknown>) => void;
}

function ImageBlockEditor({ props, themeId, onChange }: ImageBlockEditorProps) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [urlInput, setUrlInput] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const srcUrl = (props.srcUrl as string) || null;

  const handleFileUpload = async (file: File) => {
    setUploading(true);
    setError(null);
    try {
      const response = await uploadThemeImage(themeId, file);
      onChange({ src: response.data.src, srcUrl: response.data.url });
      // Reset input
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error subiendo imagen');
    } finally {
      setUploading(false);
    }
  };

  const handleUrlIngest = async () => {
    if (!urlInput.trim()) return;
    setUploading(true);
    setError(null);
    try {
      const response = await ingestThemeImageUrl(themeId, urlInput);
      onChange({ src: response.data.src, srcUrl: response.data.url });
      setUrlInput('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error ingiriendo imagen desde URL');
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-3">
      {/* Miniatura si hay imagen */}
      {srcUrl && (
        <div>
          <FieldLabel>Vista previa</FieldLabel>
          <div className="h-24 w-full rounded border border-ui-border overflow-hidden bg-ui-body">
            <img src={srcUrl} alt="Preview" className="w-full h-full object-cover" />
          </div>
        </div>
      )}

      {/* Origen: Archivo */}
      <div>
        <FieldLabel htmlFor="blk-img-file">Subir imagen</FieldLabel>
        <input
          ref={fileInputRef}
          id="blk-img-file"
          type="file"
          accept="image/*"
          disabled={uploading}
          onChange={(e) => {
            const file = e.currentTarget.files?.[0];
            if (file) handleFileUpload(file);
          }}
          className="block w-full text-xs file:mr-2 file:rounded file:border-0 file:bg-primary file:px-2 file:py-1 file:text-white file:text-xs file:cursor-pointer file:disabled:bg-text-muted disabled:opacity-50"
        />
      </div>

      {/* Origen: URL */}
      <div>
        <FieldLabel htmlFor="blk-img-url">O usar URL</FieldLabel>
        <div className="flex gap-1">
          <input
            id="blk-img-url"
            type="url"
            value={urlInput}
            onChange={(e) => setUrlInput(e.target.value)}
            disabled={uploading}
            placeholder="https://ejemplo.com/imagen.jpg"
            className="flex-1 rounded border border-ui-border px-2 py-1 text-xs disabled:opacity-50"
          />
          <Button
            type="button"
            variant="primary"
            size="sm"
            loading={uploading}
            disabled={!urlInput.trim() || uploading}
            onClick={handleUrlIngest}
            className="shrink-0"
          >
            Usar
          </Button>
        </div>
      </div>

      {/* Errores */}
      {error && (
        <p className="rounded bg-danger/10 p-2 text-xs text-danger-dark">
          {error}
        </p>
      )}

      {/* Texto alternativo */}
      <div>
        <FieldLabel htmlFor="blk-img-alt">Texto alternativo</FieldLabel>
        <TextInput
          id="blk-img-alt"
          value={(props.alt as string) ?? ''}
          onChange={(e) => onChange({ alt: e.target.value })}
        />
      </div>

      {/* Propiedades visuales */}
      <NumField
        label="Opacidad (0–1)"
        value={(props.opacity as number) ?? 1}
        min={0}
        max={1}
        step={0.05}
        onChange={(v) => onChange({ opacity: v })}
      />

      <NumField
        label="Rotación (grados)"
        value={(props.rotate as number) ?? 0}
        min={-180}
        max={180}
        onChange={(v) => onChange({ rotate: v })}
      />

      <div>
        <FieldLabel htmlFor="blk-img-fit">Ajuste de imagen</FieldLabel>
        <Select
          id="blk-img-fit"
          value={(props.objectFit as string) ?? 'contain'}
          onChange={(e) => onChange({ objectFit: e.target.value })}
        >
          <option value="contain">Contener (espacio vacío)</option>
          <option value="cover">Cubrir (recorte)</option>
          <option value="stretch">Estirar</option>
        </Select>
      </div>
    </div>
  );
}

function labelForType(t: ThemeBlockType): string {
  const def = BLOCK_CATALOG.find((b) => b.type === t);
  return def?.label ?? t;
}
