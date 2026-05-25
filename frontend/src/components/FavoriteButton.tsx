import { useCallback, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { FAVORITE_STAR_FILLED_CHAR, FAVORITE_STAR_OUTLINE_CHAR } from '@ceedcv-maya/shared-ui-react';
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

const FAVORITES_QUERY_KEY = ['favorites', 'ids'];

interface FavoritesIds {
  template_ids: string[];
  document_ids: string[];
}

export function FavoriteButton({ entityType, entityId }: Props) {
  const queryClient = useQueryClient();
  const { templateIds, documentIds, loading } = useFavoritesIds();
  const [toggling, setToggling] = useState(false);

  const isFavorite = entityType === 'template'
    ? templateIds.has(entityId)
    : documentIds.has(entityId);

  const onClick = useCallback(async () => {
    if (toggling || loading) return;
    setToggling(true);

    const previous = queryClient.getQueryData<FavoritesIds>(FAVORITES_QUERY_KEY);

    queryClient.setQueryData<FavoritesIds>(FAVORITES_QUERY_KEY, (old) => {
      if (!old) return old;
      if (entityType === 'template') {
        return {
          ...old,
          template_ids: isFavorite
            ? old.template_ids.filter((id) => id !== entityId)
            : [...old.template_ids, entityId],
        };
      }
      return {
        ...old,
        document_ids: isFavorite
          ? old.document_ids.filter((id) => id !== entityId)
          : [...old.document_ids, entityId],
      };
    });

    try {
      if (entityType === 'template') {
        if (!isFavorite) await addTemplateFavorite(entityId);
        else await removeTemplateFavorite(entityId);
      } else {
        if (!isFavorite) await addDocumentFavorite(entityId);
        else await removeDocumentFavorite(entityId);
      }
      await queryClient.invalidateQueries({ queryKey: FAVORITES_QUERY_KEY });
    } catch {
      queryClient.setQueryData(FAVORITES_QUERY_KEY, previous);
    } finally {
      setToggling(false);
    }
  }, [entityType, entityId, isFavorite, loading, toggling, queryClient]);

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
