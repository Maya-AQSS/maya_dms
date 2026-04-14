import { HierarchyProvider } from '../features/hierarchy';
import { TemplatesContent } from '../features/templates';

export function TemplatesPage() {
  return (
    <HierarchyProvider>
      <TemplatesContent />
    </HierarchyProvider>
  );
}
