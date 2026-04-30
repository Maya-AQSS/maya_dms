import { apiGetJson } from './http';
/** GET /api/v1/dashboard */
export async function fetchDashboard() {
    const response = await apiGetJson('dashboard');
    return response.data;
}
