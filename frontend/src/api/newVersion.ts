import { ApiHttpError, buildApiUrl, getBearerToken } from './http';

export type WorkingRevisionConflict = {
  code: 'working_revision_in_progress';
  draftAuthor: string | null;
  startedAt: string | null;
};

export class NewVersionConflictError extends Error {
  readonly status = 409;
  readonly conflict: WorkingRevisionConflict;

  constructor(conflict: WorkingRevisionConflict) {
    super('Working revision in progress');
    this.name = 'NewVersionConflictError';
    this.conflict = conflict;
  }
}

function parseConflictBody(body: unknown): WorkingRevisionConflict | null {
  if (!body || typeof body !== 'object') return null;
  const record = body as Record<string, unknown>;
  if (record.code !== 'working_revision_in_progress') return null;

  return {
    code: 'working_revision_in_progress',
    draftAuthor: typeof record.draft_author === 'string' ? record.draft_author : null,
    startedAt: typeof record.started_at === 'string' ? record.started_at : null,
  };
}

function extractErrorMessage(body: unknown, fallback: string): string {
  if (!body || typeof body !== 'object') return fallback;
  const message = (body as Record<string, unknown>).message;
  return typeof message === 'string' && message !== '' ? message : fallback;
}

/** POST de nueva versión con cuerpo estructurado en 409 (working_revision_in_progress). */
export async function postNewVersion<T>(path: string): Promise<T> {
  const url = buildApiUrl(path);
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
  const token = await getBearerToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(url, {
    method: 'POST',
    headers,
    body: '{}',
  });

  const text = await response.text();
  let body: unknown = null;
  if (text !== '') {
    try {
      body = JSON.parse(text) as unknown;
    } catch {
      body = null;
    }
  }

  if (response.status === 409) {
    const conflict = parseConflictBody(body);
    if (conflict) {
      throw new NewVersionConflictError(conflict);
    }
  }

  if (!response.ok) {
    throw new ApiHttpError(
      extractErrorMessage(body, response.statusText || `HTTP ${response.status}`),
      response.status,
    );
  }

  if (body === null) {
    return undefined as T;
  }

  return body as T;
}
