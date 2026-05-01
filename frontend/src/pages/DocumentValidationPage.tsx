import { Link, useParams } from 'react-router-dom';
import { DocumentWizard } from '../features/documents/components/DocumentWizard';
import { Button } from '@maya/shared-ui-react';

/**
 * Validación de programación: misma vista de resumen que el editor, con acciones Aprobar / Rechazar.
 */
export function DocumentValidationPage() {
  const { documentId } = useParams<{ documentId: string }>();

  if (!documentId) {
    return (
      <div className="p-6">
        <p className="text-sm text-warning-dark dark:text-warning-light mb-4">Identificador de documento no válido.</p>
        <Link to="/procesos" state={{ tab: 'documents' }}>
          <Button variant="secondary">Volver a Procesos</Button>
        </Link>
      </div>
    );
  }

  return <DocumentWizard documentId={documentId} mode="validate" />;
}
