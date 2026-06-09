import type { CSSProperties, ReactNode } from 'react';
import { PX_PER_MM, type BoxPatch, type CanvasRegion, type PageMm } from './canvasModel';
import { useCanvasInteraction } from './useCanvasInteraction';
import { CanvasRuler, RULER_SIZE } from './CanvasRuler';
import './canvas.css';

export interface AbsoluteCanvasLabels {
  layerUp: string;
  layerDown: string;
  remove: string;
}

const DEFAULT_LABELS: AbsoluteCanvasLabels = {
  layerUp: 'Capa arriba',
  layerDown: 'Capa abajo',
  remove: 'Eliminar',
};

interface AbsoluteCanvasProps {
  /** Dimensiones físicas de la página en mm. */
  pageMm: PageMm;
  /** Regiones posicionadas (solo se pintan las que tienen `box`). */
  regions: CanvasRegion[];
  selectedId: string | null;
  onSelect: (id: string | null) => void;
  onChangeBox: (id: string, patch: BoxPatch) => void;
  onZUp: (id: string) => void;
  onZDown: (id: string) => void;
  onRemove: (id: string) => void;
  /** Render del contenido de cada región (lo aporta el dominio). */
  renderRegion: (region: CanvasRegion) => ReactNode;
  /** Estilos extra de la superficie (p. ej. variables de tipografía del tema). */
  surfaceStyle?: CSSProperties;
  /**
   * Si `false`, desactiva arrastre/redimensionado/controles y selección — útil
   * para un futuro modo "relleno" donde la geometría queda bloqueada. Por
   * defecto `true` (modo diseño).
   */
  editable?: boolean;
  labels?: Partial<AbsoluteCanvasLabels>;
}

/**
 * Lienzo genérico de posicionamiento absoluto en milímetros: superficie de
 * página a escala 96 dpi + reglas + render de regiones con controles de capa /
 * borrado y tirador de redimensionado. La mecánica de drag/resize vive en
 * `useCanvasInteraction`; el contenido de cada bloque lo aporta `renderRegion`.
 */
export function AbsoluteCanvas({
  pageMm,
  regions,
  selectedId,
  onSelect,
  onChangeBox,
  onZUp,
  onZDown,
  onRemove,
  renderRegion,
  surfaceStyle,
  editable = true,
  labels,
}: AbsoluteCanvasProps) {
  const lbl = { ...DEFAULT_LABELS, ...labels };
  const beginInteraction = useCanvasInteraction(pageMm, onChangeBox, onSelect);

  const pageWidthPx = pageMm.width * PX_PER_MM;
  const pageHeightPx = pageMm.height * PX_PER_MM;

  return (
    <div className="min-w-0 flex-1 overflow-auto bg-ui-body p-6 dark:bg-ui-dark-bg">
      <div style={{ width: RULER_SIZE + pageWidthPx }}>
        {/* Fila de regla superior (con esquina) */}
        <div className="flex">
          <div
            style={{ width: RULER_SIZE, height: RULER_SIZE }}
            className="shrink-0 bg-ui-body dark:bg-ui-dark-bg"
          />
          <CanvasRuler orientation="horizontal" lengthMm={pageMm.width} scale={PX_PER_MM} />
        </div>
        {/* Fila de regla lateral + página */}
        <div className="flex">
          <CanvasRuler orientation="vertical" lengthMm={pageMm.height} scale={PX_PER_MM} />
          <div
            className="canvas-page relative bg-white shadow"
            style={{ width: pageWidthPx, height: pageHeightPx, ...surfaceStyle }}
            onPointerDown={(e) => {
              if (editable && e.target === e.currentTarget) onSelect(null);
            }}
          >
            {regions.map((r) => {
              if (!r.box) return null;
              const isSelected = r.id === selectedId;
              return (
                <div
                  key={r.id}
                  className={`canvas-block ${editable ? 'is-interactive' : ''} ${isSelected ? 'is-selected' : ''}`}
                  style={{
                    position: 'absolute',
                    left: r.box.x * PX_PER_MM,
                    top: r.box.y * PX_PER_MM,
                    width: r.box.w * PX_PER_MM,
                    height: r.box.h * PX_PER_MM,
                    zIndex: r.box.z ?? 1,
                    touchAction: 'none',
                    cursor: editable ? 'move' : 'default',
                  }}
                  onPointerDown={editable ? (e) => beginInteraction(e, r, 'move') : undefined}
                >
                  {renderRegion(r)}

                  {editable && (
                    <>
                      <div
                        className="canvas-block-controls"
                        onPointerDown={(e) => e.stopPropagation()}
                      >
                        <button type="button" title={lbl.layerUp} onClick={() => onZUp(r.id)}>
                          ↑
                        </button>
                        <button type="button" title={lbl.layerDown} onClick={() => onZDown(r.id)}>
                          ↓
                        </button>
                        <button type="button" title={lbl.remove} onClick={() => onRemove(r.id)}>
                          ✕
                        </button>
                      </div>

                      {/* Tirador de redimensionado (esquina inferior derecha) */}
                      <div
                        className="canvas-resize"
                        title="Redimensionar"
                        onPointerDown={(e) => beginInteraction(e, r, 'resize')}
                      />
                    </>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
