import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import { Button, PageTitle } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { ProcessesTable } from '../components/ProcessesTable';

export function ProcessesManagePage() {
  const { t } = useTranslation(['processes', 'common']);
  const navigate = useNavigate();
  const location = useLocation();
  const { hasPermission } = useUserProfile();
  const canCreate = hasPermission(DMS_PERMISSIONS.processCreate);

  return (
    <>
      <PageTitle
        title={t('processes:manage.title')}
        subtitle={t('processes:manage.subtitle')}
        actions={
          canCreate ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/admin/processes/new', { state: buildBackState(location) })}
            >
              {t('common:actions.create')}
            </Button>
          ) : undefined
        }
      />
      <ProcessesTable />
    </>
  );
}
