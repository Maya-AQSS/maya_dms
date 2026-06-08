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

/** Tipo de bloque colocable en el layout del theme. */
export type ThemeBlockType =
  | 'content_slot'   // marca el área donde se renderiza el cuerpo del documento
  | 'text'           // texto estático (cabecera, pie, etc.)
  | 'image'          // imagen auto-contenida con origen archivo-local O URL
  | 'page_number'    // contador de páginas
  | 'date';          // fecha

/** Una región / bloque del layout del theme. */
export interface ThemeLayoutRegion {
  id: string;
  type: ThemeBlockType;
  /**
   * Posición en la rejilla del editor (12 columnas). `z` define la capa
   * (mayor valor = más arriba) y permite solapar bloques.
   */
  grid?: { x: number; y: number; w: number; h: number; z?: number };
  /**
   * Posición legacy en porcentajes (no usada por el editor actual; se
   * mantiene para no romper datos serializados previos).
   */
  position?: { x: number; y: number; width: number; height: number };
  /**
   * Props específicas del bloque (texto, color, formato, etc.).
   * Para bloques de tipo 'image', esperados:
   *   - src: string — path interno canónico, ej. "themes/{themeId}/{uuid}"
   *   - srcUrl: string — URL firmada lista para <img src> (SOLO LECTURA, devuelta por el backend)
   *   - alt?: string — texto alternativo
   *   - opacity?: number (0..1) — opacidad de la imagen
   *   - rotate?: number — rotación en grados (-180..180)
   *   - objectFit?: 'cover' | 'contain' | 'stretch' — ajuste de la imagen en el bloque
   */
  props?: Record<string, unknown>;
  /** @deprecated Datos del editor Puck anterior — sólo backward-compat. */
  puck?: unknown;
}

export interface ThemeLayout {
  regions: ThemeLayoutRegion[];
  page: ThemeLayoutPage;
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
