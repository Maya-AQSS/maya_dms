/**
 * Inicialización del cliente OIDC — delegada al factory compartido
 * `createOidcAdapter` de @ceedcv-maya/shared-auth-react (0.16).
 * Solo este archivo lee `import.meta.env.VITE_KEYCLOAK_*`; el resto de la app
 * consume el servicio resultante o los hooks (`useOidcSession`, `useAuth`) del paquete.
 *
 * Identidad y permisos de negocio: GET /api/v1/me (feature `user-profile`).
 */
import { createOidcAdapter } from '@ceedcv-maya/shared-auth-react';

export const { oidcAuthService, appendBearerAuthorization, triggerSignIn } = createOidcAdapter({
  url: import.meta.env.VITE_KEYCLOAK_URL,
  realm: import.meta.env.VITE_KEYCLOAK_REALM,
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID,
});
