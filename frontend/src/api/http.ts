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

/**
 * Construye un `ApiHttpError` a partir de una `Response` fallida de un fetch
 * directo (multipart/blob, donde no aplica `apiFetchJson`): intenta extraer
 * `message` del body JSON y cae a `statusText` si no existe o no es JSON.
 *
 * Sustituye a las ~5 copias inline del mismo parseo (uploads multipart y
 * descargas blob de templates/themes/documents/media).
 */
export async function apiErrorFromResponse(response: Response): Promise<ApiHttpError> {
  let message = response.statusText
  try {
    const body = (await response.json()) as { message?: string }
    if (body?.message) message = body.message
  } catch {
    /* keep statusText */
  }
  return new ApiHttpError(message, response.status)
}
