/**
 * Inicialización del cliente OIDC — delegada al factory compartido
 * `createOidcAdapter` de @ceedcv-maya/shared-auth-react (0.16).
 * Solo este archivo lee la configuración de Keycloak (`KEYCLOAK_*`); el resto de la app
 * consume el servicio resultante o los hooks (`useOidcSession`, `useAuth`) del paquete.
 *
 * Configuración en ejecución (D26 R6): `publicConfig` lee `window.__MAYA_CONFIG__`
 * (el `/config.js` que genera la imagen del frontend a partir de `MAYA_PUBLIC_*`) y,
 * si falta, `import.meta.env.VITE_*` (desarrollo con Vite).
 *
 * Identidad y permisos de negocio: GET /api/v1/me (feature `user-profile`).
 */
import { createOidcAdapter, publicConfig } from '@ceedcv-maya/shared-auth-react';

// Fail-fast si falta cualquier clave KEYCLOAK_*: arrancar con un OIDC mal
// configurado dejaría toda la app en un loop de login con errores opacos.
function requireConfig(key: string): string {
  const value = publicConfig(key);
  if (value === undefined) {
    throw new Error(`Missing required config: ${key} (MAYA_PUBLIC_${key} en /config.js o VITE_${key})`);
  }
  return value;
}

export const { oidcAuthService, appendBearerAuthorization, triggerSignIn } = createOidcAdapter({
  url: requireConfig('KEYCLOAK_URL'),
  realm: requireConfig('KEYCLOAK_REALM'),
  clientId: requireConfig('KEYCLOAK_CLIENT_ID'),
});
