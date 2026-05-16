import { createDataHook } from '@maya/shared-auth-react';
import { fetchTemplate } from '../../../api/templates';
import type { Template } from '../../../types/templates';

export const useTemplateQuery = createDataHook<string, { data: Template }>({
  queryKey: (templateId) => ['templates', templateId],
  fetcher: (templateId) => fetchTemplate(templateId),
  defaultOptions: { staleTime: 60_000 },
});
