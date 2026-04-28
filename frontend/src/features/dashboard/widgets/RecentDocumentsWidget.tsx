import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchDocuments } from '../../../api/documents';
import type { Document } from '../../../types/documents';

/** Widget compacto: últimos 5 documentos ordenados por updated_at desc. */
export default function RecentDocumentsWidget() {
  const [documents, setDocuments] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    let mounted = true;
    fetchDocuments()
      .then((data) => {
        if (!mounted) return;
        const sorted = [...data].sort((a, b) => {
          const ta = a.updated_at ? new Date(a.updated_at).getTime() : 0;
          const tb = b.updated_at ? new Date(b.updated_at).getTime() : 0;
          return tb - ta;
        });
        setDocuments(sorted.slice(0, 5));
        setError(null);
      })
      .catch((err) => {
        if (!mounted) return;
        setError(err instanceof Error ? err : new Error('Unknown error'));
      })
      .finally(() => {
        if (!mounted) return;
        setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, []);

  const formatRelative = (iso?: string | null) => {
    if (!iso) return '—';
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const days = Math.floor(diffMs / 86_400_000);
    if (days <= 0) return 'hoy';
    if (days === 1) return 'ayer';
    if (days < 7) return `hace ${days} días`;
    if (days < 30) return `hace ${Math.floor(days / 7)} sem.`;
    return date.toLocaleDateString('es-ES');
  };

  if (loading) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        Cargando…
      </p>
    );
  }

  if (error) {
    return (
      <p className="text-sm text-danger py-4 text-center">
        No se pudieron cargar los documentos.
      </p>
    );
  }

  if (documents.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        No hay documentos recientes.
      </p>
    );
  }

  return (
    <ul className="divide-y divide-ui-border-l dark:divide-ui-dark-border">
      {documents.map((doc) => (
        <li key={doc.id}>
          <Link
            to={`/documents/${doc.id}`}
            className="flex items-center justify-between gap-3 py-2 px-1 hover:bg-ui-body dark:hover:bg-ui-dark-bg rounded transition-colors"
          >
            <span className="text-sm font-medium text-text-primary dark:text-text-dark-primary truncate">
              {doc.title?.trim() || 'Sin título'}
            </span>
            <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0 tabular-nums">
              {formatRelative(doc.updated_at)}
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
