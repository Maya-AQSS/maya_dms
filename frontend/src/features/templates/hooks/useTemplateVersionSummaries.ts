import { createDataHook } from '@ceedcv-maya/shared-auth-react';
import {
  fetchTemplateVersionSummaries,
  type TemplateVersionSummary,
} from '../../../api/templates';

export const useTemplateVersionSummariesQuery = createDataHook<
  string,
  TemplateVersionSummary[]
>({
  queryKey: (templateId) => ['templates', templateId, 'versions'],
  fetcher: (templateId) => fetchTemplateVersionSummaries(templateId),
  defaultOptions: { staleTime: 30_000 },
});
