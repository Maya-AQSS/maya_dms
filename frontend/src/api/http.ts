/**
 * Cliente HTTP autenticado — delegado al factory de @maya/shared-auth-react.
 * El Bearer lo añade la instancia Keycloak de {@link ../auth/oidcAdapter}.
 */
import { createApiClient, ApiHttpError, type ApiFetchOptions } from '@maya/shared-auth-react'
import { oidcAuthService } from '../auth/oidcAdapter'

const DEFAULT_BASE_URL = 'http://maya-dms-api.localhost/api/v1'
const baseUrl = (import.meta.env.VITE_API_URL as string | undefined) ?? DEFAULT_BASE_URL

const client = createApiClient(oidcAuthService.keycloak, baseUrl)

export { ApiHttpError, type ApiFetchOptions }
export const { apiFetchJson, apiGetJson, buildApiUrl } = client
