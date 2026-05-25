import type { AcademicHierarchy } from '../types/hierarchy';
import { normalizeForSearch } from './normalizeForSearch';
import type { TemplateVisibilityLevel } from '../types/templates';
import { visibilityLabel } from '../features/templates/constants';

export type AcademicContextLinkFields = {
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  /** Objeto `team` tal como lo devuelve el API (p. ej. DocumentResource / TemplateResource). */
  team?: unknown;
};

/** Campos para búsqueda unificada: visibilidad + contexto académico + equipo (nombre e id). */
export type ListRowSearchFields = AcademicContextLinkFields & {
  visibility_level?: TemplateVisibilityLevel | null;
};

function teamDisplayName(team: unknown): string | null {
  if (!team || typeof team !== 'object') return null;
  const name = (team as { name?: unknown }).name;
  return typeof name === 'string' && name.trim() ? name.trim() : null;
}

function resolveStudyTypeName(hierarchy: AcademicHierarchy, studyTypeId: string | null | undefined): string | null {
  if (!studyTypeId) return null;
  const st = hierarchy.find((t) => String(t.id) === String(studyTypeId));
  return st?.name?.trim() ? st.name.trim() : null;
}

function resolveStudyName(hierarchy: AcademicHierarchy, studyId: string | null | undefined): string | null {
  if (!studyId) return null;
  for (const st of hierarchy) {
    const s = (st.studies ?? []).find((x) => String(x.id) === String(studyId));
    if (s?.name?.trim()) return s.name.trim();
  }
  return null;
}

function resolveModuleName(hierarchy: AcademicHierarchy, moduleId: string | null | undefined): string | null {
  if (!moduleId) return null;
  for (const st of hierarchy) {
    for (const s of st.studies ?? []) {
      const m = (s.course_modules ?? []).find((x) => String(x.id) === String(moduleId));
      if (m?.name?.trim()) return m.name.trim();
    }
  }
  return null;
}

/**
 * Texto para columna «Visibilidad»: etiqueta de nivel + solo el vínculo de ese nivel
 * (p. ej. `Módulo · Biología`), sin el resto de la jerarquía académica.
 */
export function formatListRowVisibilityCaption(
  hierarchy: AcademicHierarchy,
  fields: ListRowSearchFields,
): string {
  if (fields.visibility_level == null) {
    return '—';
  }
  const level = fields.visibility_level;
  const label = visibilityLabel(level);
  const teamNm = teamDisplayName(fields.team);

  if (level === 'global' || level === 'personal') {
    return label;
  }
  if (level === 'team') {
    return teamNm ? `${label} · ${teamNm}` : label;
  }
  if (level === 'study_type') {
    const n = resolveStudyTypeName(hierarchy, fields.study_type_id);
    return n ? `${label} · ${n}` : label;
  }
  if (level === 'study') {
    const n = resolveStudyName(hierarchy, fields.study_id);
    return n ? `${label} · ${n}` : label;
  }
  if (level === 'module') {
    const n = resolveModuleName(hierarchy, fields.module_id);
    return n ? `${label} · ${n}` : label;
  }
  return label;
}

/**
 * Texto en minúsculas para búsqueda por subcadena: nombres de jerarquía resueltos + UUIDs + nombre de equipo si existe.
 */
export function academicContextSearchHaystack(
  hierarchy: AcademicHierarchy,
  fields: AcademicContextLinkFields,
): string {
  const parts: string[] = [];
  const stId = fields.study_type_id;
  if (stId) {
    const st = hierarchy.find((t) => String(t.id) === String(stId));
    if (st?.name) parts.push(st.name);
    parts.push(String(stId));
  }
  const sid = fields.study_id;
  if (sid) {
    for (const st of hierarchy) {
      const s = (st.studies ?? []).find((x) => String(x.id) === String(sid));
      if (s?.name) {
        parts.push(s.name);
        break;
      }
    }
    parts.push(String(sid));
  }
  const mid = fields.module_id;
  if (mid) {
    outer: for (const st of hierarchy) {
      for (const s of st.studies ?? []) {
        const m = (s.course_modules ?? []).find((x) => String(x.id) === String(mid));
        if (m?.name) {
          parts.push(m.name);
          break outer;
        }
      }
    }
    parts.push(String(mid));
  }
  if (fields.team_id) {
    const embedded = teamDisplayName(fields.team);
    if (embedded) parts.push(embedded);
    parts.push(String(fields.team_id));
  }
  return parts.join(' ').toLowerCase();
}

function visibilitySearchFragments(level: TemplateVisibilityLevel | null | undefined): string[] {
  if (level == null) return [];
  const resolved = level as TemplateVisibilityLevel;
  return [visibilityLabel(resolved), String(resolved)];
}

/**
 * Texto en minúsculas: etiqueta y slug de visibilidad + contexto académico + nombre/id de equipo cuando aplica.
 */
export function listRowSearchHaystack(hierarchy: AcademicHierarchy, fields: ListRowSearchFields): string {
  const visParts = visibilitySearchFragments(fields.visibility_level);
  const academic = academicContextSearchHaystack(hierarchy, fields);
  return [...visParts, academic].join(' ').toLowerCase();
}

/** Búsqueda insensible a mayúsculas y acentos: global/personal/equipo/…, nombre de equipo, jerarquía académica visible. */
export function listRowSearchMatches(
  hierarchy: AcademicHierarchy,
  fields: ListRowSearchFields,
  needle: string,
): boolean {
  const n = normalizeForSearch(needle.trim());
  if (!n) return true;
  return normalizeForSearch(listRowSearchHaystack(hierarchy, fields)).includes(n);
}

