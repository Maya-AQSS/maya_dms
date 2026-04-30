import { useMemo } from 'react';
import { filterDocumentsByCascade } from '../lib/filterDocumentsByCascade';
/**
 * Hook para filtrar los documentos por los filtros en cascada.
 *
 * @param documents - Los documentos a filtrar.
 * @param filters - Los filtros en cascada.
 * @param hierarchy - La jerarquía académica.
 * @returns Los documentos filtrados.
 */
export function useFilteredDocuments(documents, filters, hierarchy) {
    return useMemo(() => filterDocumentsByCascade(documents, filters, hierarchy), [documents, filters, hierarchy]);
}
