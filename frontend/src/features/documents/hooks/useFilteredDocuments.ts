import { useMemo } from'react';
import type { AcademicHierarchy } from'../../../types/hierarchy';
import type { Document } from'../../../types/documents';
import { filterDocumentsByCascade } from'../lib/filterDocumentsByCascade';
import type { CascadeDocumentFilters } from'../types';

/**
 * Hook para filtrar los documentos por los filtros en cascada.
 * 
 * @param documents - Los documentos a filtrar.
 * @param filters - Los filtros en cascada.
 * @param hierarchy - La jerarquía académica.
 * @returns Los documentos filtrados.
 */
export function useFilteredDocuments(documents: Document[],
 filters: CascadeDocumentFilters,
 hierarchy: AcademicHierarchy
): Document[] {
 return useMemo(() => filterDocumentsByCascade(documents, filters, hierarchy),
 [documents, filters, hierarchy]
 );
}
