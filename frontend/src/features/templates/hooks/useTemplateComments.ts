import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import { fetchResourceComments, type CommentsListResponse } from '../../../api/comments';
import type { BlockComment } from '../components/BlockCommentsCard';

export type TemplateCommentsResponse = CommentsListResponse;

export const templateCommentsKey = (templateId: string) =>
  ['templates', templateId, 'comments'] as const;

export const useTemplateCommentsQuery = createDataHook<
  string,
  TemplateCommentsResponse
>({
  queryKey: (templateId) => templateCommentsKey(templateId),
  fetcher: (templateId) =>
    fetchResourceComments(`templates/${templateId}/comments`),
  defaultOptions: { staleTime: 0 },
});
