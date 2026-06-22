import { useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import type { User, UsersSearchResponse } from '../../../types/users';

export interface DebouncedUserSearch {
  results: User[];
  searching: boolean;
  error: string | null;
}

/**
 * Búsqueda de usuarios con debounce (300ms), cancelación y manejo de error.
 *
 * Extraído de los dos bloques idénticos de WizardStep3Users (candidatos de
 * plantilla vs. documento), que solo diferían en la función de búsqueda y en
 * los setters de estado. Behavior-preserving: misma ventana de 300ms, mismo
 * umbral de 2 caracteres y el mismo mensaje de error por defecto.
 */
export function useDebouncedUserSearch(
  query: string,
  searchFn: (q: string) => Promise<UsersSearchResponse>,
  enabled: boolean,
): DebouncedUserSearch {
  const [results, setResults] = useState<User[]>([]);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!enabled) {
      setResults([]);
      return;
    }
    const q = query.trim();
    if (q.length < 2) {
      setResults([]);
      setError(null);
      return;
    }
    let cancelled = false;
    const timer = setTimeout(() => {
      setSearching(true);
      setError(null);
      searchFn(q)
        .then((res) => {
          if (!cancelled) setResults(res.data);
        })
        .catch((e) => {
          if (!cancelled) {
            setError(
              e instanceof ApiHttpError ? e.message : 'No se pudo completar la búsqueda. Inténtalo de nuevo.',
            );
          }
        })
        .finally(() => {
          if (!cancelled) setSearching(false);
        });
    }, 300);
    return () => {
      clearTimeout(timer);
      cancelled = true;
    };
  }, [query, enabled, searchFn]);

  return { results, searching, error };
}
