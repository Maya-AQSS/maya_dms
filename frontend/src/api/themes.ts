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
import { apiFetchJson, apiGetJson, apiErrorFromResponse, buildApiUrl, getBearerToken } from './http';
import { normalizePaginatedResponse } from './paginatedList';
// buildQueryString canónico compartido (0.16): misma semántica que el builder
// local eliminado (omite null/undefined/''/false/0, true→'1'); añade soporte de
// arrays (join ','), que estos call sites no usan.
import { buildQueryString } from '@ceedcv-maya/shared-auth-react';

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

/**
 * GET /api/v1/themes — una página del listado. Pasa por
 * `normalizePaginatedResponse` (API-6) para tolerar tanto el envelope plano de
 * Laravel como la paginación anidada bajo `meta`, igual que el resto de listados.
 */
export async function fetchThemes(filters: ThemeListFilters = {}): Promise<ThemesListResponse> {
  const body = await apiGetJson<unknown>(`themes${buildQueryString({ ...filters })}`);
  const page = normalizePaginatedResponse<Theme>(body);
  return {
    data: page.data,
    meta: {
      current_page: page.current_page,
      last_page: page.last_page,
      per_page: page.per_page,
      total: page.total,
    },
  };
}

/** GET /api/v1/themes/fonts — whitelist real de tipografías instaladas. */
export async function fetchThemeFonts(): Promise<ThemeFontsCatalog> {
  const body = await apiGetJson<{ data: ThemeFontsCatalog }>('themes/fonts');
  return body.data;
}

/** GET /api/v1/themes/{id} */
export async function fetchTheme(id: string): Promise<Theme> {
  const body = await apiGetJson<{ data: Theme }>(`themes/${id}`);
  return body.data;
}

/** POST /api/v1/themes */
export async function createTheme(payload: CreateThemePayload): Promise<Theme> {
  const body = await apiFetchJson<{ data: Theme }>('themes', { method: 'POST', body: payload });
  return body.data;
}

/** PATCH /api/v1/themes/{id} */
export async function updateTheme(
  id: string,
  payload: UpdateThemePayload,
): Promise<Theme> {
  const body = await apiFetchJson<{ data: Theme }>(`themes/${id}`, { method: 'PATCH', body: payload });
  return body.data;
}

/** DELETE /api/v1/themes/{id} */
export async function deleteTheme(id: string): Promise<void> {
  await apiFetchJson<undefined>(`themes/${id}`, { method: 'DELETE' });
}

/** POST /api/v1/themes/{id}/clone */
export async function cloneTheme(id: string, payload: CloneThemePayload = {}): Promise<Theme> {
  const body = await apiFetchJson<{ data: Theme }>(`themes/${id}/clone`, { method: 'POST', body: payload });
  return body.data;
}

/** POST /api/v1/themes/{id}/publish — transición draft → published. */
export async function publishTheme(id: string): Promise<Theme> {
  const body = await apiFetchJson<{ data: Theme }>(`themes/${id}/publish`, { method: 'POST' });
  return body.data;
}

/** POST /api/v1/themes/{id}/archive — transición published → archived. */
export async function archiveTheme(id: string): Promise<Theme> {
  const body = await apiFetchJson<{ data: Theme }>(`themes/${id}/archive`, { method: 'POST' });
  return body.data;
}

/**
 * POST /api/v1/themes/{id}/images — multipart upload de archivo de imagen.
 * Hace fetch directo porque apiFetchJson serializa siempre el body como JSON
 * (no detecta FormData). El boundary lo fija el browser.
 */
export async function uploadThemeImage(
  themeId: string,
  file: File,
): Promise<{ src: string; url: string }> {
  const form = new FormData();
  form.append('file', file);

  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`themes/${themeId}/images`), {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    body: form,
  });

  if (!response.ok) {
    throw await apiErrorFromResponse(response);
  }

  const body = (await response.json()) as { data: { src: string; url: string } };
  return body.data;
}

/**
 * POST /api/v1/themes/{id}/images — ingesta de imagen desde URL remota.
 * Envía { url: "https://..." } al servidor para que descargue y procese.
 */
export async function ingestThemeImageUrl(
  themeId: string,
  url: string,
): Promise<{ src: string; url: string }> {
  const body = await apiFetchJson<{ data: { src: string; url: string } }>(
    `themes/${themeId}/images`,
    { method: 'POST', body: { url } },
  );
  return body.data;
}
