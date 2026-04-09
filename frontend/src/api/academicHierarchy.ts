import type { AcademicHierarchy } from '../types/hierarchy';
import { apiGetJson } from './http';

type HierarchyApiResponse = {
  data: AcademicHierarchy;
};

/**
 * GET /api/v1/hierarchy — árbol anidado bajo envelope { data }.
 */
export async function fetchAcademicHierarchy(): Promise<AcademicHierarchy> {
  const body = await apiGetJson<HierarchyApiResponse>('hierarchy');
  return body.data;
}
