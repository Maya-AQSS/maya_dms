import { createDataHook } from '@maya/shared-auth-react';
import { apiFetchJson } from '../../../api/http';
import type { BlockComment } from '../components/BlockCommentsCard';

export interface TemplateCommentsResponse {
  data: BlockComment[];
  meta?: { commenting_open?: boolean };
}

export const templateCommentsKey = (templateId: string) =>
  ['templates', templateId, 'comments'] as const;

export const useTemplateCommentsQuery = createDataHook<
  string,
  TemplateCommentsResponse
>({
  queryKey: (templateId) => templateCommentsKey(templateId),
  fetcher: (templateId) =>
    apiFetchJson<TemplateCommentsResponse>(`templates/${templateId}/comments`),
  defaultOptions: { staleTime: 0 },
});
