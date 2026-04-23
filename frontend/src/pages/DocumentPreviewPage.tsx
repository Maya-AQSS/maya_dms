import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { fetchDocument } from '../api/documents';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { Button } from '../ui';

function blockContentForPreview(block: DocumentDisplayBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.content);
  if (fromContent.length > 0) {
    return fromContent;
  }
  return normalizeBlockContentForEditor(block.default_content);
}

/**
 * Previsualización de un documento (listado → clic).
 * Misma estética general que la previsualización de plantillas; el editor wizard llegará en iteraciones siguientes.
 */
export function DocumentPreviewPage() {
  const { documentId } = useParams<{ documentId: string }>();
  const navigate = useNavigate();
  const location = useLocation();
  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!documentId) {
      setLoading(false);
      setError('Identificador de documento no válido.');
      return;
    }

    let cancelled = false;
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        const data = await fetchDocument(documentId);
        if (!cancelled) {
          setDetail(data);
        }
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'No se pudo cargar el documento.');
          setDetail(null);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    void load();
    return () => {
      cancelled = true;
    };
  }, [documentId]);

  const canEdit = detail?.status === 'draft';
  const cameFromSummary = (location.state as { returnToStep?: string } | null)?.returnToStep === 'summary';

  return (
    <div className="min-h-full overflow-y-auto bg-[#ddd9d3] dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-4 px-6 h-[52px]">
        <button
          type="button"
          onClick={() => {
            if (cameFromSummary && documentId) {
              navigate(`/documents/${documentId}/editor`, { state: { step: 'summary' } });
              return;
            }
            navigate('/documents');
          }}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          {cameFromSummary ? '← Volver al resumen' : '← Volver al listado'}
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          {detail?.title ?? 'Programación'} — Previsualización
        </span>
        {canEdit && documentId && (
          <Link to={`/documents/${documentId}/editor`}>
            <Button type="button" size="sm" variant="primary">
              Editar
            </Button>
          </Link>
        )}
      </header>

      <article
        className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content"
        style={{ maxWidth: '960px', minHeight: 'calc(100vh - 52px)', padding: '64px 80px' }}
      >
        {loading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {!loading && !error && detail && (
          <>
            {detail.blocks.length === 0 ? (
              <p className="text-sm text-text-muted dark:text-text-dark-muted italic">Este documento no tiene bloques.</p>
            ) : (
              <div className="space-y-10">
                {detail.blocks.map((block) => {
                  const nodes = blockContentForPreview(block);
                  const hasContent = nodes.length > 0;

                  return (
                    <section key={block.template_block_id}>
                      {hasContent ? (
                        <BlockContentHtml content={nodes as unknown[]} />
                      ) : (
                        <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                          Este bloque no tiene contenido todavía.
                        </p>
                      )}
                    </section>
                  );
                })}
              </div>
            )}
          </>
        )}
      </article>
    </div>
  );
}
