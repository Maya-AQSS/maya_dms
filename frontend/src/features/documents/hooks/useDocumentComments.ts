import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchResourceComments, type CommentsListResponse } from '../../../api/comments';

export type DocumentCommentsResponse = CommentsListResponse;

/** Query key canónica de los comentarios de un documento (única fuente de verdad). */
export const documentCommentsKey = (documentId: string) =>
  ['documents', documentId, 'comments'] as const;

export const useDocumentCommentsQuery = createDataHook<
  string,
  DocumentCommentsResponse
>({
  queryKey: (documentId) => documentCommentsKey(documentId),
  fetcher: (documentId) =>
    fetchResourceComments(`documents/${documentId}/comments`),
  defaultOptions: { staleTime: 0 },
});
