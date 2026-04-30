import { apiGetJson } from './http';
/**
 * GET /api/v1/hierarchy — árbol anidado bajo envelope { data }.
 */
export async function fetchAcademicHierarchy() {
    const body = await apiGetJson('hierarchy');
    return body.data;
}
