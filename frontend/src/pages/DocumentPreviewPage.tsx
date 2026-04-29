import { useEffect, useState } from'react';
import { Link, useLocation, useNavigate, useParams } from'react-router-dom';
import { PageTitle } from'@maya/shared-ui-react';
import { fetchDocument } from'../api/documents';
import { normalizeBlockContentForEditor } from'../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from'../features/templates/components/BlockContentHtml';
import type { DocumentDetail, DocumentDisplayBlock } from'../types/documents';
import { Button } from'../ui';

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
 setError(e instanceof Error ? e.message :'No se pudo cargar el documento.');
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

 const canEdit = detail?.status ==='draft';
 const previewState = location.state as { returnToStep?: string; returnToValidate?: boolean } | null;
 const cameFromSummary = previewState?.returnToStep ==='summary';
 const cameFromValidate = previewState?.returnToValidate === true;

 return (<div className="min-h-full overflow-y-auto bg-ui-preview-bg">
 <div className="sticky top-0 z-10 bg-surface-container-low border-b border-outline px-6 py-3">
 <PageTitle
 title={detail?.title ??'Vista previa'}
 subtitle="Previsualización"
 onBack={() => {
 if (cameFromSummary && documentId) {
 if (cameFromValidate) {
 navigate(`/documents/${documentId}/validate`);
 } else {
 navigate(`/documents/${documentId}/editor`, { state: { step:'summary' } });
 }
 return;
 }
 navigate('/documents');
 }}
 backLabel={
 cameFromSummary
 ? cameFromValidate
 ?'Volver a validar'
 :'Volver al resumen'
 :'Volver al listado'
 }
 className="!mb-0"
 actions={
 canEdit && documentId ? (<Link to={`/documents/${documentId}/editor`}>
 <Button type="button" size="sm" variant="primary">
 Editar
 </Button>
 </Link>
 ) : undefined
 }
 />
 </div>

 <article
 className="mx-auto bg-surface-container-low shadow-xl preview-content"
 style={{ maxWidth:'960px', minHeight:'calc(100vh - 52px)', padding:'64px 80px' }}
 >
 {loading && (<p className="text-sm text-on-surface-muted">Cargando documento…</p>
 )}
 {error && !loading && (<p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
 )}
 {!loading && !error && detail && (<>
 {detail.blocks.length === 0 ? (<p className="text-sm text-on-surface-muted italic">Este documento no tiene bloques.</p>
 ) : (<div className="space-y-10">
 {detail.blocks.map((block) => {
 const nodes = blockContentForPreview(block);
 const hasContent = nodes.length > 0;

 return (<section key={block.template_block_id}>
 {hasContent ? (<BlockContentHtml content={nodes as unknown[]} />
 ) : (<p className="text-sm text-on-surface-muted italic">
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
