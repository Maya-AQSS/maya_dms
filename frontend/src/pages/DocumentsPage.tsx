import { HierarchyProvider } from '../features/hierarchy';
import { DocumentsContent } from '../components/DocumentsContent';

export function DocumentsPage() {
  return (
    <HierarchyProvider>
      <DocumentsContent />
    </HierarchyProvider>
  );
}
