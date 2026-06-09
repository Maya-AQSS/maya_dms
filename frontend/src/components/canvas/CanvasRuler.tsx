/** Grosor de la regla en px (alto si horizontal, ancho si vertical). */
export const RULER_SIZE = 16;

interface RulerProps {
  orientation: 'horizontal' | 'vertical';
  /** Longitud útil en mm (ancho o alto de página). */
  lengthMm: number;
  /** Px por mm del lienzo. */
  scale: number;
}

/**
 * Regla graduada en cm para el lienzo de posicionamiento. Marca un tick por cm
 * con etiqueta y un tick menor cada 5 mm. Genérica: solo depende de longitud y
 * escala. (Extraída de `ThemeCanvasRuler`.)
 */
export function CanvasRuler({ orientation, lengthMm, scale }: RulerProps) {
  const horizontal = orientation === 'horizontal';
  const lengthPx = lengthMm * scale;
  const ticks: React.ReactNode[] = [];

  for (let mm = 0; mm <= lengthMm; mm += 5) {
    const posPx = mm * scale;
    const isCm = mm % 10 === 0;
    const tickLen = isCm ? RULER_SIZE * 0.6 : RULER_SIZE * 0.35;
    ticks.push(
      <div
        key={mm}
        className="absolute bg-text-muted/60"
        style={
          horizontal
            ? { left: posPx, bottom: 0, width: 1, height: tickLen }
            : { top: posPx, right: 0, height: 1, width: tickLen }
        }
      />,
    );
    if (isCm && mm > 0) {
      ticks.push(
        <span
          key={`l-${mm}`}
          className="absolute select-none text-[8px] leading-none text-text-muted"
          style={horizontal ? { left: posPx + 1, top: 1 } : { top: posPx + 1, left: 1 }}
        >
          {mm / 10}
        </span>,
      );
    }
  }

  return (
    <div
      className="relative shrink-0 bg-ui-body dark:bg-ui-dark-bg"
      style={horizontal ? { height: RULER_SIZE, width: lengthPx } : { width: RULER_SIZE, height: lengthPx }}
      aria-hidden="true"
    >
      {ticks}
    </div>
  );
}
