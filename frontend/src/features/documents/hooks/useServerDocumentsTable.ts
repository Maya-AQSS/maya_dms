import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useServerTable } from '@ceedcv-maya/shared-hooks-react';
import { fetchDocumentsPage, type DocumentsListMeta } from '../../../api/documents';
import { DEFAULT_TABLE_PAGE_SIZE, dropInvalidStoredPageSize } from '../../../lib/dataTablePageSize';
import { NO_MATCH_ID } from '../../../lib/noMatchId';
import { useHierarchy } from '../../../features/hierarchy';
import { useUserProfile } from '../../../features/user-profile';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { Document } from '../../../types/documents';
import {
  clearAcademicFilterClearedInSession,
  countProfileAcademicScopes,
  formatCascadeFilterLabels,
  formatProfileAcademicScopeLabels,
  hasExplicitAcademicUrlFilters,
  isAcademicFilterClearedInSession,
  markAcademicFilterClearedInSession,
  profileToAcademicScope,
  resolveInitialAcademicUrlPatch,
} from '../lib/documentsAcademicListFilter';
import type { CascadeDocumentFilters } from '../types';

/** Columnas ordenables server-side (espejo de la whitelist del backend). */
const SORTABLE_DOCUMENT_COLUMNS = ['title', 'status', 'delivery_deadline', 'created_at', 'updated_at'] as const;

/** Filtros de dominio sincronizados a URL (claves = query params del backend, salvo `favorites`). */
const DOCUMENT_FILTER_DEFAULTS = {
  status: '',
  search: '',
  /** Contexto académico estructurado en cascada (server-side sobre el snapshot del cabezal). */
  study_type_id: '',
  study_id: '',
  module_id: '',
  /** Flag UI: '' = sin default de perfil; '1' = contexto académico del perfil (OR server-side). */
  profile_academic_default: '',
  /** Flag UI: '' = todos; 'favorites' = solo favoritos (se traduce a `favorite_ids` server-side). */
  favorites: '',
} as const;

type DocumentFilterKeys = keyof typeof DOCUMENT_FILTER_DEFAULTS;

/**
 * Listado server-side de documentos: filtros + paginación + ordenación los resuelve
 * el backend (estándar unificado, ver useServerTable). Estado de tabla en URL
 * (filtros/page/sort) y localStorage (per_page).
 *
 * @param processId Filtro `process_id` permanente del contexto de la URL.
 */
export function useServerDocumentsTable(processId?: string) {
  const { hasPermission, profile, loading: profileLoading } = useUserProfile();
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const canIndex = hasPermission(DMS_PERMISSIONS.documentIndex);
  const { documentIds: favoriteDocumentIds } = useFavoritesIds();
  const [academicDefaultApplied, setAcademicDefaultApplied] = useState(false);
  const prevProfileScopeCount = useRef(0);

  dropInvalidStoredPageSize('maya:dms:documents-table');
  const table = useServerTable<Record<DocumentFilterKeys, string>>({
    defaults: { ...DOCUMENT_FILTER_DEFAULTS },
    sortableColumns: SORTABLE_DOCUMENT_COLUMNS,
    storageKey: 'maya:dms:documents-table',
    defaultSort: { columnId: 'created_at', direction: 'desc' },
    defaultPageSize: DEFAULT_TABLE_PAGE_SIZE,
  });

  const [rows, setRows] = useState<Document[]>([]);
  const [meta, setMeta] = useState<DocumentsListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  // Token para forzar refetch tras mutaciones externas (p. ej. crear documento).
  const [refetchToken, setRefetchToken] = useState(0);
  const refetch = useCallback(() => setRefetchToken((n) => n + 1), []);

  const favoriteIdsCsv = useMemo(() => [...favoriteDocumentIds].join(','), [favoriteDocumentIds]);

  const cascadeFilters: CascadeDocumentFilters = useMemo(
    () => ({
      studyTypeId: table.filters.study_type_id,
      studyId: table.filters.study_id,
      moduleId: table.filters.module_id,
    }),
    [table.filters.study_type_id, table.filters.study_id, table.filters.module_id],
  );

  const setCascadeFilters = useCallback(
    (next: CascadeDocumentFilters) => {
      clearAcademicFilterClearedInSession();
      table.setFilters({
        study_type_id: next.studyTypeId,
        study_id: next.studyId,
        module_id: next.moduleId,
        profile_academic_default: '',
      });
    },
    [table],
  );

  const resetFilters = useCallback(() => {
    markAcademicFilterClearedInSession();
    table.resetFilters();
  }, [table]);

  const profileScopeCount = useMemo(() => {
    if (!profile) {
      return 0;
    }
    return countProfileAcademicScopes(profileToAcademicScope(profile));
  }, [profile]);

  // Si /me llega tras caché vacía, permite volver a aplicar el default.
  useEffect(() => {
    const prev = prevProfileScopeCount.current;
    prevProfileScopeCount.current = profileScopeCount;
    if (prev === 0 && profileScopeCount > 0 && academicDefaultApplied) {
      setAcademicDefaultApplied(false);
    }
  }, [profileScopeCount, academicDefaultApplied]);

  // Preselección del contexto académico del perfil al entrar sin filtros en URL.
  useEffect(() => {
    if (profileLoading || academicDefaultApplied) {
      return;
    }
    if (isAcademicFilterClearedInSession()) {
      setAcademicDefaultApplied(true);
      return;
    }
    if (hasExplicitAcademicUrlFilters(table.filters)) {
      setAcademicDefaultApplied(true);
      return;
    }
    if (!profile) {
      return;
    }

    const scope = profileToAcademicScope(profile);
    const total = countProfileAcademicScopes(scope);
    if (total === 0) {
      setAcademicDefaultApplied(true);
      return;
    }
    if (
      total === 1 &&
      (scope.moduleIds.length === 1 || scope.studyIds.length === 1) &&
      hierarchyLoading
    ) {
      return;
    }

    const initial = resolveInitialAcademicUrlPatch(profile, hierarchy);
    if (initial) {
      table.setFilters(initial);
    }
    setAcademicDefaultApplied(true);
  }, [
    academicDefaultApplied,
    hierarchy,
    hierarchyLoading,
    profile,
    profileLoading,
    table.filters,
    table.setFilters,
  ]);

  const isProfileAcademicDefaultActive = table.filters.profile_academic_default === '1';

  const activeAcademicFilterLabels = useMemo(() => {
    if (!hasExplicitAcademicUrlFilters(table.filters)) {
      return [];
    }
    if (isProfileAcademicDefaultActive && profile) {
      return formatProfileAcademicScopeLabels(profile, hierarchy);
    }
    return formatCascadeFilterLabels(cascadeFilters, hierarchy);
  }, [
    cascadeFilters,
    hierarchy,
    isProfileAcademicDefaultActive,
    profile,
    table.filters,
  ]);

  // El flag `favorites` se traduce a `favorite_ids` (ids de documento server-side).
  const apiParams = useMemo(() => {
    const { favorites, profile_academic_default, ...rest } = table.queryParams;
    const params: Record<string, unknown> = { ...rest };
    if (processId) params.process_id = processId;
    if (profile_academic_default === '1') {
      params.profile_academic_default = '1';
    }
    if (favorites === 'favorites') {
      params.favorite_ids = favoriteIdsCsv || NO_MATCH_ID;
    }
    return params;
  }, [table.queryParams, processId, favoriteIdsCsv]);
  const apiParamsKey = JSON.stringify(apiParams);

  useEffect(() => {
    if (!canIndex) {
      setRows([]);
      setMeta(null);
      setLoading(false);
      setError(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetchDocumentsPage(apiParams as Parameters<typeof fetchDocumentsPage>[0])
      .then((res) => {
        if (cancelled) return;
        setRows(Array.isArray(res.data) ? res.data : []);
        setMeta(res.meta ?? null);
      })
      .catch((e) => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
        setError(e instanceof Error ? e : new Error('Error desconocido'));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiParamsKey, canIndex, refetchToken]);

  return {
    ...table,
    resetFilters,
    cascadeFilters,
    setCascadeFilters,
    isProfileAcademicDefaultActive,
    activeAcademicFilterLabels,
    rows,
    meta,
    loading,
    error,
    canIndex,
    refetch,
  };
}
