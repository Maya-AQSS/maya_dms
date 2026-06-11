import type {
  Theme,
  ThemeAccessibility,
  ThemeFontsCatalog,
  ThemeLayout,
  ThemeListFilters,
  ThemePalette,
  ThemeTypography,
  ThemesListResponse,
} from '../types/themes';
import { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken, ApiHttpError } from './http';

export type { Theme, ThemeListFilters, ThemesListResponse } from '../types/themes';

export interface CreateThemePayload {
  name: string;
  description?: string | null;
  team_id?: string | null;
  palette?: Partial<ThemePalette>;
  typography?: Partial<ThemeTypography>;
  layout?: Partial<ThemeLayout>;
  accessibility?: Partial<ThemeAccessibility>;
}

export interface UpdateThemePayload {
  name?: string;
  description?: string | null;
  // El estado no se cambia por PATCH: usar publishTheme()/archiveTheme().
  palette?: Partial<ThemePalette>;
  typography?: Partial<ThemeTypography>;
  layout?: Partial<ThemeLayout>;
  accessibility?: Partial<ThemeAccessibility>;
}

export interface CloneThemePayload {
  name?: string;
  palette?: Partial<ThemePalette>;
  typography?: Partial<ThemeTypography>;
  layout?: Partial<ThemeLayout>;
  accessibility?: Partial<ThemeAccessibility>;
}

function buildListQuery(filters: ThemeListFilters): string {
  const q = new URLSearchParams();
  if (filters.status) q.set('status', filters.status);
  if (filters.team_id) q.set('team_id', filters.team_id);
  if (filters.search) q.set('search', filters.search);
  if (filters.page) q.set('page', String(filters.page));
  if (filters.per_page) q.set('per_page', String(filters.per_page));
  if (filters.sort_by) q.set('sort_by', filters.sort_by);
  if (filters.sort_dir) q.set('sort_dir', filters.sort_dir);
  const s = q.toString();
  return s ? `?${s}` : '';
}

/** GET /api/v1/themes */
export async function fetchThemes(filters: ThemeListFilters = {}): Promise<ThemesListResponse> {
  return apiGetJson<ThemesListResponse>(`themes${buildListQuery(filters)}`);
}

/** GET /api/v1/themes/fonts — whitelist real de tipografías instaladas. */
export async function fetchThemeFonts(): Promise<{ data: ThemeFontsCatalog }> {
  return apiGetJson<{ data: ThemeFontsCatalog }>('themes/fonts');
}

/** GET /api/v1/themes/{id} */
export async function fetchTheme(id: string): Promise<{ data: Theme }> {
  return apiGetJson<{ data: Theme }>(`themes/${id}`);
}

/** POST /api/v1/themes */
export async function createTheme(payload: CreateThemePayload): Promise<{ data: Theme }> {
  return apiFetchJson<{ data: Theme }>('themes', { method: 'POST', body: payload });
}

/** PATCH /api/v1/themes/{id} */
export async function updateTheme(
  id: string,
  payload: UpdateThemePayload,
): Promise<{ data: Theme }> {
  return apiFetchJson<{ data: Theme }>(`themes/${id}`, { method: 'PATCH', body: payload });
}

/** DELETE /api/v1/themes/{id} */
export async function deleteTheme(id: string): Promise<void> {
  await apiFetchJson<undefined>(`themes/${id}`, { method: 'DELETE' });
}

/** POST /api/v1/themes/{id}/clone */
export async function cloneTheme(id: string, payload: CloneThemePayload = {}): Promise<{ data: Theme }> {
  return apiFetchJson<{ data: Theme }>(`themes/${id}/clone`, { method: 'POST', body: payload });
}

/** POST /api/v1/themes/{id}/publish — transición draft → published. */
export async function publishTheme(id: string): Promise<{ data: Theme }> {
  return apiFetchJson<{ data: Theme }>(`themes/${id}/publish`, { method: 'POST' });
}

/** POST /api/v1/themes/{id}/archive — transición published → archived. */
export async function archiveTheme(id: string): Promise<{ data: Theme }> {
  return apiFetchJson<{ data: Theme }>(`themes/${id}/archive`, { method: 'POST' });
}

/**
 * POST /api/v1/themes/{id}/images — multipart upload de archivo de imagen.
 * Hace fetch directo porque apiFetchJson serializa siempre el body como JSON
 * (no detecta FormData). El boundary lo fija el browser.
 */
export async function uploadThemeImage(
  themeId: string,
  file: File,
): Promise<{ data: { src: string; url: string } }> {
  const form = new FormData();
  form.append('file', file);

  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`themes/${themeId}/images`), {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    body: form,
  });

  if (!response.ok) {
    let message = response.statusText;
    try {
      const body = (await response.json()) as { message?: string };
      if (body?.message) message = body.message;
    } catch {
      /* keep statusText */
    }
    throw new ApiHttpError(message, response.status);
  }

  return (await response.json()) as { data: { src: string; url: string } };
}

/**
 * POST /api/v1/themes/{id}/images — ingesta de imagen desde URL remota.
 * Envía { url: "https://..." } al servidor para que descargue y procese.
 */
export async function ingestThemeImageUrl(
  themeId: string,
  url: string,
): Promise<{ data: { src: string; url: string } }> {
  return apiFetchJson<{ data: { src: string; url: string } }>(
    `themes/${themeId}/images`,
    { method: 'POST', body: { url } },
  );
}

