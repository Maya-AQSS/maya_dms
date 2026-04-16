import { HierarchyProvider } from '../features/hierarchy';
import { TemplateWizard } from '../features/templates/components/TemplateWizard';

export function TemplateNewPage() {
  return (
    <HierarchyProvider>
      <TemplateWizard initialTemplate={null} />
    </HierarchyProvider>
  );
}
