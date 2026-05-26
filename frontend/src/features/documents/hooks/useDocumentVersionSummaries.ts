import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import {
  fetchDocumentVersionSummaries,
  type DocumentVersionSummary,
} from '../../../api/documents';

export const useDocumentVersionSummariesQuery = createDataHook<
  string,
  DocumentVersionSummary[]
>({
  queryKey: (documentId) => ['documents', documentId, 'versions'],
  fetcher: (documentId) => fetchDocumentVersionSummaries(documentId),
  defaultOptions: { staleTime: 30_000 },
});
