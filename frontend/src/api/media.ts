import { buildApiUrl, getBearerToken } from './http';

export type MediaContext =
  | { type: 'block';    id: string }
  | { type: 'template'; id: string }
  | { type: 'document'; id: string }
  | { type: 'theme';    id: string };

export async function uploadMedia(file: File, context?: MediaContext): Promise<string> {
  const token = await getBearerToken();
  const form = new FormData();
  form.append('image', file);
  if (context) {
    form.append('context_type', context.type);
    form.append('context_id', context.id);
  }

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(buildApiUrl('media'), {
    method: 'POST',
    headers,
    body: form,
  });

  if (!response.ok) {
    const errorBody = await response.json().catch(() => null) as { message?: string } | null;
    throw new Error(errorBody?.message ?? `Upload failed: ${response.status}`);
  }

  const data = (await response.json()) as { url: string };
  return data.url;
}
