/**
 * Identidad visual reutilizable que una plantilla puede aplicar a sus documentos.
 * Espejo del DTO backend `App\DTOs\Themes\ThemeDto`.
 */

export type ThemeStatus = 'draft' | 'published' | 'archived';

export interface ThemePalette {
  primary: string;
  secondary: string;
  text: string;
  background: string;
  accent?: string;
}

export interface ThemeTypography {
  heading_font: string;
  body_font: string;
  base_size_pt: number;
  line_height: number;
}

export interface ThemeLayoutPage {
  size: 'A4' | 'Letter' | 'A3' | string;
  margin_cm: {
    top: number;
    right: number;
    bottom: number;
    left: number;
  };
}

/** Una región del layout (header / footer / sidebar / content slot). */
export interface ThemeLayoutRegion {
  id: string;
  type: 'header' | 'footer' | 'sidebar' | 'content_slot' | 'logo' | 'watermark';
  /** Coordenadas en porcentajes 0-100 sobre la página. */
  position?: { x: number; y: number; width: number; height: number };
  /** Configuración serializada por Puck (cuando aplique). */
  puck?: unknown;
}

export interface ThemeLayout {
  regions: ThemeLayoutRegion[];
  page: ThemeLayoutPage;
}

export interface ThemeAssets {
  logo_path: string | null;
  background_image_path: string | null;
  watermark_path: string | null;
}

export interface ThemeAccessibility {
  language: string;
  title: string | null;
  subject: string | null;
  author: string;
}

export interface Theme {
  id: string;
  name: string;
  description: string | null;
  status: ThemeStatus;
  created_by: string;
  team_id: string | null;
  palette: ThemePalette;
  typography: ThemeTypography;
  layout: ThemeLayout;
  assets: ThemeAssets;
  accessibility: ThemeAccessibility;
  cloned_from_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface ThemeListFilters {
  status?: ThemeStatus;
  team_id?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface ThemesListResponse {
  data: Theme[];
  links?: { first?: string; last?: string; prev?: string | null; next?: string | null };
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

/** Whitelist real de tipografías servidas por el backend. */
export interface ThemeFontOption {
  /** Valor CSS literal para `font-family` (incluye fallbacks). */
  value: string;
  /** Nombre legible que se muestra en el editor. */
  label: string;
  /** Descripción opcional (uso recomendado). */
  note?: string;
}

export interface ThemeFontsCatalog {
  sans: ThemeFontOption[];
  serif: ThemeFontOption[];
  mono: ThemeFontOption[];
}
