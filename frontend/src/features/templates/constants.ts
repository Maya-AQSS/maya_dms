import type { TemplateVisibilityLevel } from '../../types/templates';

export const VISIBILITY_OPTIONS: { value: TemplateVisibilityLevel; label: string }[] = [
  { value: 'personal', label: 'Personal' },
  { value: 'global', label: 'Global' },
  { value: 'study_type', label: 'Tipo de Estudio' },
  { value: 'study', label: 'Estudio' },
  { value: 'module', label: 'Módulo' },
  { value: 'team', label: 'Equipo' },
];

export const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'published', label: 'Publicada' },
  { value: 'archived', label: 'Archivada' },
] as const;

export function visibilityLabel(v: TemplateVisibilityLevel): string {
  return VISIBILITY_OPTIONS.find((o) => o.value === v)?.label ?? v;
}
