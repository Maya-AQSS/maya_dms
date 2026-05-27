import { PageTitle } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { ProcessesTable } from '../components/ProcessesTable';

export function ProcessesManagePage() {
  const { hasPermission } = useUserProfile();
  const canCreate = hasPermission(DMS_PERMISSIONS.processCreate);

  return (
    <>
      <PageTitle
        title="Gestión de Procesos"
        subtitle="Catálogo de procesos del sistema"
      />
      <ProcessesTable canCreate={canCreate} />
    </>
  );
}
