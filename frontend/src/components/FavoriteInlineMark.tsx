import { FAVORITE_STAR_FILLED_CHAR } from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';

/** Marca de favorito en listados DMS; misma estética que maya_dashboard (glifo ★). */
export function FavoriteInlineMark() {
  const { t } = useTranslation('common');
  const label = t('favorites.marked');
  return (
    <span
      title={label}
      aria-label={label}
      className="text-warning"
    >
      {FAVORITE_STAR_FILLED_CHAR}
    </span>
  );
}
