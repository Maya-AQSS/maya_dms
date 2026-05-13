import { useCallback, useEffect, useState } from 'react';
import { FAVORITE_STAR_FILLED_CHAR, FAVORITE_STAR_OUTLINE_CHAR } from '@maya/shared-ui-react';
import {
  addDocumentFavorite,
  addTemplateFavorite,
  fetchFavorites,
  removeDocumentFavorite,
  removeTemplateFavorite,
} from '../api/favorites';

type Props = {
  entityType: 'template' | 'document';
  entityId: string;
};

export function FavoriteButton({ entityType, entityId }: Props) {
  const [isFavorite, setIsFavorite] = useState(false);
  const [loading, setLoading] = useState(true);
  const [toggling, setToggling] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);

    void (async () => {
      try {
        const { data } = await fetchFavorites();
        if (cancelled) return;
        const ids = entityType === 'template' ? data.template_ids : data.document_ids;
        setIsFavorite(ids.includes(entityId));
      } catch {
        if (!cancelled) setIsFavorite(false);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [entityType, entityId]);

  const onClick = useCallback(async () => {
    if (toggling || loading) return;
    setToggling(true);
    const next = !isFavorite;
    setIsFavorite(next);
    try {
      if (entityType === 'template') {
        if (next) await addTemplateFavorite(entityId);
        else await removeTemplateFavorite(entityId);
      } else {
        if (next) await addDocumentFavorite(entityId);
        else await removeDocumentFavorite(entityId);
      }
    } catch {
      setIsFavorite(!next);
    } finally {
      setToggling(false);
    }
  }, [entityType, entityId, isFavorite, loading, toggling]);

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
