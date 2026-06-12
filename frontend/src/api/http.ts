/**
 * Cliente HTTP autenticado — delegado al factory compartido
 * `createServiceApiClient` de @ceedcv-maya/shared-auth-react (0.16): resuelve
 * la baseUrl vía `peerOrigin('dms-api')/api/v1` o el override `VITE_API_URL`,
 * y delega en `createApiClient`. El Bearer lo añade la instancia Keycloak de
 * {@link ../auth/oidcAdapter}.
 *
 * En tests, `@ceedcv-maya/shared-auth-react` resuelve al shim local que ofrece la
 * misma superficie (`createServiceApiClient`, `ApiHttpError`, `ApiFetchOptions`)
 * pero sin tocar la red.
 */
import {
  ApiHttpError,
  createServiceApiClient,
  type ApiFetchOptions,
} from '@ceedcv-maya/shared-auth-react'
import { oidcAuthService } from '../auth/oidcAdapter'

const client = createServiceApiClient(
  'dms-api',
  oidcAuthService.keycloak,
  (import.meta.env.VITE_API_URL as string | undefined)?.trim(),
)

export { ApiHttpError, type ApiFetchOptions }
export const { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken } = client

/**
 * Construye un `ApiHttpError` a partir de una `Response` fallida de un fetch
 * directo (multipart/blob, donde no aplica `apiFetchJson`): intenta extraer
 * `message` del body JSON y cae a `statusText` si no existe o no es JSON.
 *
 * Sustituye a las ~5 copias inline del mismo parseo (uploads multipart y
 * descargas blob de templates/themes/documents/media). No existe equivalente
 * en el paquete compartido (0.16) — se mantiene local.
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
