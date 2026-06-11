import { apiFetchJson } from './http';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';

export type CommentsListMeta = {
  commenting_open?: boolean;
  total?: number;
  per_page?: number;
  current_page?: number;
  last_page?: number;
};

export type CommentsListResponse = {
  data: BlockComment[];
  meta?: CommentsListMeta;
};

const COMMENTS_MAX_PER_PAGE = 200;

/** Carga todas las páginas del listado de comentarios de un recurso. */
export async function fetchResourceComments(
  resourcePath: `documents/${string}/comments` | `templates/${string}/comments`,
): Promise<CommentsListResponse> {
  const query = `per_page=${COMMENTS_MAX_PER_PAGE}`;
  const first = await apiFetchJson<CommentsListResponse>(`${resourcePath}?${query}&page=1`);
  const lastPage = first.meta?.last_page ?? 1;

  if (lastPage <= 1) {
    return first;
  }

  const all = [...first.data];
  for (let page = 2; page <= lastPage; page++) {
    const next = await apiFetchJson<CommentsListResponse>(
      `${resourcePath}?${query}&page=${page}`,
    );
    all.push(...next.data);
  }

  return {
    data: all,
    meta: first.meta ? { ...first.meta, current_page: lastPage } : undefined,
  };
}

export async function markCommentAsRead(commentId: string): Promise<BlockComment> {
  const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}/read`, {
    method: 'POST',
  });
  return res.data;
}

export type MarkBlockCommentsReadResponse = {
  data: BlockComment[];
};

export async function markBlockCommentsAsRead(
  resourcePath: `templates/${string}/comments/mark-block-read` | `documents/${string}/comments/mark-block-read`,
  blockableId: string,
): Promise<MarkBlockCommentsReadResponse> {
  return apiFetchJson<MarkBlockCommentsReadResponse>(resourcePath, {
    method: 'POST',
    body: { blockable_id: blockableId },
  });
}
