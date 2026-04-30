import { useState } from 'react';
// TODO: conectar con endpoint de favoritos cuando exista (persistencia en BD)
export function FavoriteButton({ entityType: _entityType, entityId: _entityId }) {
    const [isFavorite, setIsFavorite] = useState(false);
    return (<button type="button" onClick={() => setIsFavorite((v) => !v)} title={isFavorite ? 'Quitar de favoritos' : 'Añadir a favoritos'} className="flex items-center justify-center w-7 h-7 rounded-md text-text-muted dark:text-text-dark-muted hover:text-warning-dark dark:hover:text-warning-light transition-colors cursor-pointer" aria-pressed={isFavorite}>
      {isFavorite ? (<svg viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4 text-warning-dark dark:text-warning-light">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>) : (<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-4 h-4">
          <path strokeLinecap="round" strokeLinejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>)}
    </button>);
}
