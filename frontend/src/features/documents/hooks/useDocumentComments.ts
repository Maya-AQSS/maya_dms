import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { apiFetchJson } from '../../../api/http';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';

interface DocumentCommentsResponse {
  data: BlockComment[];
}

export const useDocumentCommentsQuery = createDataHook<
  string,
  DocumentCommentsResponse
>({
  queryKey: (documentId) => ['documents', documentId, 'comments'],
  fetcher: (documentId) =>
    apiFetchJson<DocumentCommentsResponse>(`documents/${documentId}/comments`),
  defaultOptions: { staleTime: 0 },
});
