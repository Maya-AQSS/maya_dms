import type { TemplateVisibilityLevel } from '../../types/templates';

type TFn = (key: string, opts?: Record<string, unknown>) => string;

/**
 * Niveles de visibilidad y su clave i18n (namespace `templates`). El `label`
 * en español se conserva sólo como respaldo de búsqueda/normalización (utils
 * que no disponen de `t`); la UI debe resolver `labelKey` vía `t`.
 */
export const VISIBILITY_OPTIONS: { value: TemplateVisibilityLevel; label: string; labelKey: string }[] = [
  { value: 'personal', label: 'Personal', labelKey: 'visibility.personal' },
  { value: 'global', label: 'Global', labelKey: 'visibility.global' },
  { value: 'study_type', label: 'Tipo de Estudio', labelKey: 'visibility.studyType' },
  { value: 'study', label: 'Estudio', labelKey: 'visibility.study' },
  { value: 'module', label: 'Módulo', labelKey: 'visibility.module' },
  { value: 'team', label: 'Equipo', labelKey: 'visibility.team' },
];

export const STATUS_OPTIONS = [
  { value: '', labelKey: 'status.all' },
  { value: 'draft', labelKey: 'status.draft' },
  { value: 'in_review', labelKey: 'status.in_review' },
  { value: 'rejected', labelKey: 'status.rejected' },
  { value: 'published', labelKey: 'status.published' },
  { value: 'archived', labelKey: 'status.archived' },
] as const;

/** Valor '' = todos; 'favorites' = solo favoritos del usuario (listados DMS). */
export const FAVORITES_FILTER_OPTIONS: { value: string; labelKey: string }[] = [
  { value: '', labelKey: 'favoritesFilter.all' },
  { value: 'favorites', labelKey: 'favoritesFilter.onlyFavorites' },
];

/** Clave i18n del nivel de visibilidad (namespace `templates`). */
export function visibilityLabelKey(v: TemplateVisibilityLevel): string {
  return VISIBILITY_OPTIONS.find((o) => o.value === v)?.labelKey ?? '';
}

/**
 * Etiqueta del nivel de visibilidad. Si se pasa `t`, devuelve la traducción;
 * si no (utils de búsqueda/normalización), devuelve el respaldo en español.
 */
export function visibilityLabel(v: TemplateVisibilityLevel, t?: TFn): string {
  const opt = VISIBILITY_OPTIONS.find((o) => o.value === v);
  if (!opt) return v;
  return t ? t(`templates:${opt.labelKey}`) : opt.label;
}

