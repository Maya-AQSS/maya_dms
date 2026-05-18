import { FAVORITE_STAR_FILLED_CHAR } from '@maya/shared-ui-react';

/** Marca de favorito en listados DMS; misma estética que maya_dashboard (glifo ★). */
export function FavoriteInlineMark() {
  return (
    <span
      title="En favoritos"
      aria-label="En favoritos"
      className="text-warning"
    >
      {FAVORITE_STAR_FILLED_CHAR}
    </span>
  );
}
