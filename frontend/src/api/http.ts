/**
 * Cliente HTTP autenticado — delegado al factory de @maya/shared-auth-react.
 * El Bearer lo añade la instancia Keycloak de {@link ../auth/oidcAdapter}.
 *
 * En tests, `@maya/shared-auth-react` resuelve al shim local que ofrece la
 * misma superficie (`createApiClient`, `ApiHttpError`, `ApiFetchOptions`) pero
 * sin tocar la red.
 */
import { createApiClient, ApiHttpError, type ApiFetchOptions } from '@maya/shared-auth-react'
import { oidcAuthService } from '../auth/oidcAdapter'

const DEFAULT_BASE_URL = 'http://maya-dms-api.maya.test/api/v1'
const baseUrl = (import.meta.env.VITE_API_URL as string | undefined) ?? DEFAULT_BASE_URL

const client = createApiClient(oidcAuthService.keycloak, baseUrl)

export { ApiHttpError, type ApiFetchOptions }
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client
