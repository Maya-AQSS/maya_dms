import { Link, useLocation, useParams } from 'react-router-dom';
import { DocumentWizard } from '../features/documents/components/DocumentWizard';
import { Button, ErrorBoundary } from '@ceedcv-maya/shared-ui-react';

/**
 * Editor de programación: asistente de 3 pasos (propiedades, bloques, resumen), sin paso de usuarios.
 * Al crear continuando un documento previo (state.sourceDocumentId) y si su plantilla tiene una
 * versión nueva, el asistente inserta un paso extra de migración de contenido.
 */
export function DocumentEditorPage() {
  const { documentId, templateId } = useParams<{ documentId?: string; templateId?: string }>();
  const location = useLocation();
  const navState = location.state as { sourceDocumentId?: string; migrationMode?: 'clone' | 'upgrade' } | null;
  const sourceDocumentId = navState?.sourceDocumentId ?? null;
  const migrationMode = navState?.migrationMode ?? 'clone';

  if (!documentId && !templateId) {
    return (
      <div className="p-6">
        <p className="text-sm text-warning-dark dark:text-warning-light mb-4">Identificador de documento o plantilla no válido.</p>
        <Link to="/procesos" state={{ tab: 'documents' }}>
          <Button variant="secondary">Volver a Procesos</Button>
        </Link>
      </div>
    );
  }

  return (
    <ErrorBoundary>
      <DocumentWizard
        documentId={documentId}
        templateId={templateId}
        mode="edit"
        sourceDocumentId={sourceDocumentId}
        migrationMode={migrationMode}
      />
    </ErrorBoundary>
  );
}
