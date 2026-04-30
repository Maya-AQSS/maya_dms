/**
 * Filtrado en cliente: sin I/O; O(n) respecto al número de documentos.
 */
export function filterDocumentsByCascade(documents, filters, hierarchy) {
    const { studyTypeId, studyId, moduleId } = filters;
    if (!studyTypeId && !studyId && !moduleId) {
        return documents;
    }
    return documents.filter((doc) => {
        if (moduleId) {
            return doc.module_id === moduleId;
        }
        if (studyId) {
            return doc.study_id === studyId;
        }
        const type = hierarchy.find((t) => t.id === studyTypeId);
        if (!type) {
            return false;
        }
        const ids = new Set(type.studies.map((s) => s.id));
        return doc.study_id !== null && ids.has(doc.study_id);
    });
}
