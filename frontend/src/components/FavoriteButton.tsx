import { useCallback, useState } from 'react';
import { FAVORITE_STAR_FILLED_CHAR, FAVORITE_STAR_OUTLINE_CHAR } from '@maya/shared-ui-react';
import {
  addDocumentFavorite,
  addTemplateFavorite,
  removeDocumentFavorite,
  removeTemplateFavorite,
} from '../api/favorites';
import { useFavoritesIds } from '../hooks/useFavoritesIds';

type Props = {
  entityType: 'template' | 'document';
  entityId: string;
};

export function FavoriteButton({ entityType, entityId }: Props) {
  const { templateIds, documentIds, loading, refetch } = useFavoritesIds();
  const [toggling, setToggling] = useState(false);

  const isFavorite = entityType === 'template'
    ? templateIds.has(entityId)
    : documentIds.has(entityId);

  const onClick = useCallback(async () => {
    if (toggling || loading) return;
    setToggling(true);
    try {
      if (entityType === 'template') {
        if (!isFavorite) await addTemplateFavorite(entityId);
        else await removeTemplateFavorite(entityId);
      } else {
        if (!isFavorite) await addDocumentFavorite(entityId);
        else await removeDocumentFavorite(entityId);
      }
    } finally {
      await refetch();
      setToggling(false);
    }
  }, [entityType, entityId, isFavorite, loading, toggling, refetch]);

  return (
    <button
      type="button"
      disabled={loading || toggling}
      onClick={() => void onClick()}
      title={isFavorite ? 'Quitar de favoritos' : 'Añadir a favoritos'}
      className={`flex items-center justify-center w-7 h-7 rounded-lg text-lg leading-none transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-ui-dark-card ${
        isFavorite
          ? 'bg-warning-light dark:bg-warning-dark/40 text-warning-dark dark:text-warning shadow-sm hover:bg-danger-light/40 dark:hover:bg-danger-dark/25 hover:ring-2 hover:ring-inset hover:ring-danger/30 hover:text-danger-dark dark:hover:text-danger hover:shadow-md focus-visible:ring-danger/50'
          : 'border border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:text-warning-dark dark:hover:text-warning hover:border-warning/60 hover:bg-warning-light/50 dark:hover:bg-warning-dark/20 focus-visible:ring-ui-border'
      }`}
      aria-pressed={isFavorite}
      aria-busy={loading || toggling}
    >
      {isFavorite ? FAVORITE_STAR_FILLED_CHAR : FAVORITE_STAR_OUTLINE_CHAR}
    </button>
  );
}
