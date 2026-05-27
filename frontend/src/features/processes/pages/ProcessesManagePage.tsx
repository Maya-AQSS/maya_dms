import { useNavigate } from 'react-router-dom';
import { Button, PageTitle } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { ProcessesTable } from '../components/ProcessesTable';

export function ProcessesManagePage() {
  const navigate = useNavigate();
  const { hasPermission } = useUserProfile();
  const canCreate = hasPermission(DMS_PERMISSIONS.processCreate);

  return (
    <>
      <PageTitle
        title="Gestión de Procesos"
        subtitle="Catálogo de procesos del sistema"
        actions={
          canCreate ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/admin/procesos/new')}
            >
              + Crear
            </Button>
          ) : undefined
        }
      />
      <ProcessesTable />
    </>
  );
}
