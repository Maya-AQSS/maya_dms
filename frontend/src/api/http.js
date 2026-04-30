import { oidcAuthService } from '../auth/oidcAdapter';
export class ApiHttpError extends Error {
    status;
    constructor(message, status) {
        super(message);
        this.name = 'ApiHttpError';
        this.status = status;
    }
}
function createApiClient(keycloak, baseUrl) {
    function normalizeBase(url) {
        return (url ?? '').replace(/\/$/, '');
    }
    function buildApiUrl(path) {
        if (path.startsWith('http'))
            return path;
        const base = normalizeBase(baseUrl);
        return `${base}/${path.replace(/^\//, '')}`;
    }
    async function appendBearer(headers) {
        if (!keycloak.authenticated)
            return;
        await keycloak.updateToken(30).catch(() => keycloak.login());
        if (keycloak.token) {
            headers.Authorization = `Bearer ${keycloak.token}`;
        }
    }
    async function authHeaders(jsonBody) {
        const headers = { Accept: 'application/json' };
        if (jsonBody)
            headers['Content-Type'] = 'application/json';
        await appendBearer(headers);
        return headers;
    }
    async function parseErrorMessage(response) {
        const ct = response.headers.get('content-type') ?? '';
        if (ct.includes('application/json')) {
            try {
                const body = (await response.json());
                return body.message ?? body.error ?? response.statusText;
            }
            catch {
                return response.statusText;
            }
        }
        return response.statusText;
    }
    async function apiFetchJson(path, options = {}) {
        const method = options.method ?? 'GET';
        const hasBody = options.body !== undefined && method !== 'GET';
        const url = buildApiUrl(path);
        const response = await fetch(url, {
            method,
            headers: await authHeaders(hasBody),
            body: hasBody ? JSON.stringify(options.body) : undefined,
        });
        if (!response.ok) {
            if (response.status === 401)
                keycloak.login();
            const msg = await parseErrorMessage(response);
            throw new ApiHttpError(msg || `HTTP ${response.status}`, response.status);
        }
        if (response.status === 204)
            return undefined;
        const text = await response.text();
        if (text === '')
            return undefined;
        return JSON.parse(text);
    }
    async function apiGetJson(path) {
        return apiFetchJson(path, { method: 'GET' });
    }
    async function getBearerToken() {
        const headers = {};
        await appendBearer(headers);
        if (!headers.Authorization)
            return null;
        return headers.Authorization.replace(/^Bearer\s+/i, '');
    }
    return { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken };
}
const DEFAULT_BASE_URL = 'http://maya-dms-api.localhost/api/v1';
const baseUrl = import.meta.env.VITE_API_URL ?? DEFAULT_BASE_URL;
const client = createApiClient(oidcAuthService.keycloak, baseUrl);
export const { apiFetchJson, apiGetJson, buildApiUrl } = client;
