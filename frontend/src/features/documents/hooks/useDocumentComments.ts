import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchResourceComments, type CommentsListResponse } from '../../../api/comments';

export type DocumentCommentsResponse = CommentsListResponse;

export const useDocumentCommentsQuery = createDataHook<
  string,
  DocumentCommentsResponse
>({
  queryKey: (documentId) => ['documents', documentId, 'comments'],
  fetcher: (documentId) =>
    fetchResourceComments(`documents/${documentId}/comments`),
  defaultOptions: { staleTime: 0 },
});
