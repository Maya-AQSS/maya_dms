/**
 * Inicialización del cliente OIDC — delegada al factory compartido
 * `createOidcAdapter` de @ceedcv-maya/shared-auth-react (0.16).
 * Solo este archivo lee `import.meta.env.VITE_KEYCLOAK_*`; el resto de la app
 * consume el servicio resultante o los hooks (`useOidcSession`, `useAuth`) del paquete.
 *
 * Identidad y permisos de negocio: GET /api/v1/me (feature `user-profile`).
 */
import { createOidcAdapter } from '@ceedcv-maya/shared-auth-react';

// Fail-fast si falta cualquier variable VITE_KEYCLOAK_*: arrancar con un OIDC mal
// configurado dejaría toda la app en un loop de login con errores opacos. En
// build de prod, las variables se hornean desde docker build --build-arg.
function requireEnv(key: string): string {
  const value = import.meta.env[key];
  if (typeof value !== 'string' || value === '') {
    throw new Error(`Missing required env var: ${key}`);
  }
  return value;
}

export const { oidcAuthService, appendBearerAuthorization, triggerSignIn } = createOidcAdapter({
  url: requireEnv('VITE_KEYCLOAK_URL'),
  realm: requireEnv('VITE_KEYCLOAK_REALM'),
  clientId: requireEnv('VITE_KEYCLOAK_CLIENT_ID'),
});
