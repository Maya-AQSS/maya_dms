import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { DocumentWizard } from '../features/documents/components/DocumentWizard';
import { BackButton } from '@ceedcv-maya/shared-ui-react';
import { ErrorBoundaryWrapper as ErrorBoundary } from '../components/ErrorBoundaryWrapper';

/**
 * Editor de programación: asistente de 3 pasos (propiedades, bloques, resumen), sin paso de usuarios.
 * Al crear continuando un documento previo (state.sourceDocumentId) y si su plantilla tiene una
 * versión nueva, el asistente inserta un paso extra de migración de contenido.
 */
export function DocumentEditorPage() {
  const { documentId, templateId } = useParams<{ documentId?: string; templateId?: string }>();
  const { t } = useTranslation('common');
  const location = useLocation();
  const navigate = useNavigate();
  const navState = location.state as { sourceDocumentId?: string; migrationMode?: 'clone' | 'upgrade' } | null;
  const sourceDocumentId = navState?.sourceDocumentId ?? null;
  const migrationMode = navState?.migrationMode ?? 'clone';

  if (!documentId && !templateId) {
    return (
      <div className="p-6">
        <p className="text-sm text-warning-dark dark:text-warning-light mb-4">{t('documents:errors.invalidIdOrTemplate')}</p>
        <BackButton
          variant="outline"
          label={t('navigation.backToProcesses')}
          onClick={() => navigate('/processes', { state: { tab: 'documents' } })}
        />
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
