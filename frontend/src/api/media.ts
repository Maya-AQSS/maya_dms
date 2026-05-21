import { buildApiUrl, getBearerToken } from './http';

export async function uploadMedia(file: File): Promise<string> {
  const token = await getBearerToken();
  const form = new FormData();
  form.append('image', file);

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(buildApiUrl('media'), {
    method: 'POST',
    headers,
    body: form,
  });

  if (!response.ok) {
    throw new Error(`Upload failed: HTTP ${response.status}`);
  }

  const data = (await response.json()) as { url: string };
  return data.url;
}
