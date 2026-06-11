import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
import { BackButton } from '@ceedcv-maya/shared-ui-react';
import { fetchTemplate, type Template } from '../api/templates';
import { TemplateReviewView } from '../features/templates';
import { useUserProfile } from '../features/user-profile';
import { DMS_PERMISSIONS } from '../permissions';

export function TemplateReviewPage() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('common');
  const { goBack } = useBackNavigation({ fallback: '/processes' });
  const { profile, hasPermission } = useUserProfile();
  const [template, setTemplate] = useState<Template | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!id || !profile) return;

    async function loadTemplate() {
      try {
        const t = await fetchTemplate(id!);

        const isReviewer = t.reviewers?.some((r) => r.user_id === profile?.id);
        const isCreator = t.created_by === profile?.id;
        const canReview = hasPermission(DMS_PERMISSIONS.templateReview);

        if (!isReviewer && !isCreator) {
          setError('No tienes permisos de validación sobre esta plantilla.');
          return;
        }
        if (isReviewer && !canReview && !isCreator) {
          setError('No tienes permiso para revisar plantillas.');
          return;
        }

        setTemplate(t);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Error al cargar la plantilla');
      } finally {
        setLoading(false);
      }
    }

    void loadTemplate();
  }, [id, profile, hasPermission]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-text-muted">
        Cargando plantilla para revisión…
      </div>
    );
  }

  if (error || !template) {
    return (
      <div className="flex flex-col items-center justify-center h-full p-6 text-center space-y-4">
        <p role="alert" aria-live="assertive" className="text-sm text-danger-dark font-bold">⚠️ {error || 'No se pudo encontrar la plantilla'}</p>
        <BackButton variant="ghost" onClick={() => goBack()} label={t('navigation.backToProcesses')} />
      </div>
    );
  }

  return <TemplateReviewView template={template} />;
}
