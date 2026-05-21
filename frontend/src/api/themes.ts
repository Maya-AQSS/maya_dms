import type {
  Theme,
  ThemeAccessibility,
  ThemeAssets,
  ThemeLayout,
  ThemeListFilters,
  ThemePalette,
  ThemeStatus,
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
  assets?: Partial<ThemeAssets>;
  accessibility?: Partial<ThemeAccessibility>;
}

export interface UpdateThemePayload {
  name?: string;
  description?: string | null;
  status?: ThemeStatus;
  palette?: Partial<ThemePalette>;
  typography?: Partial<ThemeTypography>;
  layout?: Partial<ThemeLayout>;
  assets?: Partial<ThemeAssets>;
  accessibility?: Partial<ThemeAccessibility>;
}

export interface CloneThemePayload {
  name?: string;
  palette?: Partial<ThemePalette>;
  typography?: Partial<ThemeTypography>;
  layout?: Partial<ThemeLayout>;
  assets?: Partial<ThemeAssets>;
  accessibility?: Partial<ThemeAccessibility>;
}

function buildListQuery(filters: ThemeListFilters): string {
  const q = new URLSearchParams();
  if (filters.status) q.set('status', filters.status);
  if (filters.team_id) q.set('team_id', filters.team_id);
  if (filters.search) q.set('search', filters.search);
  if (filters.page) q.set('page', String(filters.page));
  if (filters.per_page) q.set('per_page', String(filters.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

/** GET /api/v1/themes */
export async function fetchThemes(filters: ThemeListFilters = {}): Promise<ThemesListResponse> {
  return apiGetJson<ThemesListResponse>(`themes${buildListQuery(filters)}`);
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

export type ThemeAssetKind = 'logo' | 'background' | 'watermark';

/**
 * POST /api/v1/themes/{id}/assets — multipart upload.
 * Hace fetch directo porque apiFetchJson serializa siempre el body como JSON
 * (no detecta FormData). El boundary lo fija el browser.
 */
export async function uploadThemeAsset(
  themeId: string,
  kind: ThemeAssetKind,
  file: File,
): Promise<{ data: Theme }> {
  const form = new FormData();
  form.append('kind', kind);
  form.append('file', file);

  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`themes/${themeId}/assets`), {
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
    throw new ApiHttpError(response.status, message);
  }

  return (await response.json()) as { data: Theme };
}

/**
 * URL absoluta para servir un asset del theme. El JWT del cliente la autoriza.
 * Útil como `src` de <img> o como background-image CSS.
 */
export function themeAssetUrl(themeId: string, kind: ThemeAssetKind): string {
  // buildApiUrl resuelve `baseUrl + path`, garantizando que vamos al mismo origen.
  return buildApiUrl(`themes/${themeId}/assets/${kind}`);
}
