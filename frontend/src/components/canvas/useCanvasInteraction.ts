import { useCallback } from 'react';
import {
  PX_PER_MM,
  MIN_SIZE_MM,
  snap,
  clamp,
  type BoxPatch,
  type CanvasRegion,
  type PageMm,
} from './canvasModel';

export type InteractionMode = 'move' | 'resize';

/**
 * Hook con la mecánica de arrastrar (mover) y redimensionar un bloque sobre el
 * lienzo en mm. Usa listeners en `window` para seguir el puntero aunque salga
 * del bloque, convierte el desplazamiento px→mm dividiendo por la escala, y
 * aplica snap + clamp a los límites de la página.
 *
 * Devuelve `beginInteraction`, pensado para `onPointerDown` del bloque (modo
 * 'move') y de su tirador de redimensionado (modo 'resize').
 */
export function useCanvasInteraction(
  pageMm: PageMm,
  onUpdateBox: (id: string, patch: BoxPatch) => void,
  onSelect?: (id: string) => void,
) {
  return useCallback(
    (e: React.PointerEvent, region: CanvasRegion, mode: InteractionMode) => {
      if (!region.box) return;
      e.preventDefault();
      e.stopPropagation();
      onSelect?.(region.id);
      const startBox = { ...region.box };
      const startX = e.clientX;
      const startY = e.clientY;

      const onMove = (ev: PointerEvent) => {
        const dxMm = (ev.clientX - startX) / PX_PER_MM;
        const dyMm = (ev.clientY - startY) / PX_PER_MM;
        if (mode === 'move') {
          onUpdateBox(region.id, {
            x: clamp(snap(startBox.x + dxMm), 0, Math.max(0, pageMm.width - startBox.w)),
            y: clamp(snap(startBox.y + dyMm), 0, Math.max(0, pageMm.height - startBox.h)),
          });
        } else {
          onUpdateBox(region.id, {
            w: clamp(snap(startBox.w + dxMm), MIN_SIZE_MM, pageMm.width - startBox.x),
            h: clamp(snap(startBox.h + dyMm), MIN_SIZE_MM, pageMm.height - startBox.y),
          });
        }
      };
      const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
      };
      window.addEventListener('pointermove', onMove);
      window.addEventListener('pointerup', onUp);
    },
    [pageMm.width, pageMm.height, onUpdateBox, onSelect],
  );
}
