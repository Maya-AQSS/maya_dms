/**
 * Cliente HTTP autenticado — delegado al factory de @ceedcv-maya/shared-auth-react.
 * El Bearer lo añade la instancia Keycloak de {@link ../auth/oidcAdapter}.
 *
 * En tests, `@ceedcv-maya/shared-auth-react` resuelve al shim local que ofrece la
 * misma superficie (`createApiClient`, `ApiHttpError`, `ApiFetchOptions`) pero
 * sin tocar la red.
 */
import { createApiClient, ApiHttpError, type ApiFetchOptions } from '@ceedcv-maya/shared-auth-react'
import { oidcAuthService } from '../auth/oidcAdapter'
import { peerOrigin } from '../lib/peerService'

// Si `VITE_API_URL` no está definida, derivamos el origen del hostname actual
// (convención Maya: `<slot>-<service>.<domain>` ↔ `<slot>-<service>-api.<domain>`).
const baseUrl = ((import.meta.env.VITE_API_URL as string | undefined)?.trim())
  || `${peerOrigin('dms-api')}/api/v1`

const client = createApiClient(oidcAuthService.keycloak, baseUrl)

export { ApiHttpError, type ApiFetchOptions }
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client
