import { Link, useParams } from 'react-router-dom';
import { DocumentPreviewPage } from './DocumentPreviewPage';
import { Button } from '@maya/shared-ui-react';
import { useUserProfile } from '../features/user-profile';
import { DMS_PERMISSIONS } from '../permissions';

/**
 * Validación de programación: misma vista de resumen que el editor, con acciones Aprobar / Rechazar.
 */
export function DocumentValidationPage() {
  const { documentId } = useParams<{ documentId: string }>();
  const { hasPermission, loading: profileLoading } = useUserProfile();
  const canReview = hasPermission(DMS_PERMISSIONS.documentReview);

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

  if (profileLoading) {
    return (
      <div className="p-6">
        <p className="text-sm text-text-secondary dark:text-text-dark-secondary">Cargando permisos…</p>
      </div>
    );
  }

  if (!canReview) {
    return (
      <div className="p-6">
        <p className="text-sm text-warning-dark dark:text-warning-light mb-4">
          No tienes permiso para validar documentos (document.review).
        </p>
        <Link to={`/documents/${documentId}`}>
          <Button variant="secondary">Ver documento</Button>
        </Link>
      </div>
    );
  }

  return <DocumentPreviewPage mode="validate" />;
}
