import { useLocation } from 'react-router-dom';
import { TemplateWizard } from '../features/templates/components/TemplateWizard';

export function TemplateNewPage() {
  const location = useLocation();
  const state = location.state as { processId?: string } | null;
  return <TemplateWizard initialTemplate={null} processId={state?.processId} />;
}
