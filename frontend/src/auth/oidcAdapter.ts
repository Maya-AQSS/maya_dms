/**
 * Inicialización del cliente OIDC (Keycloak vía @maya/shared-auth-react).
 * Solo este archivo lee`import.meta.env.VITE_KEYCLOAK_*`; el resto de la app
 * consume el servicio resultante o los hooks (`useOidcSession`,`useAuth`) del paquete.
 *
 * Identidad y permisos de negocio: GET /api/v1/me (feature`user-profile`).
 */
import { AuthService } from'@maya/shared-auth-react';

export const oidcAuthService = new AuthService({
 url: import.meta.env.VITE_KEYCLOAK_URL,
 realm: import.meta.env.VITE_KEYCLOAK_REALM,
 clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID,
});

export const appendBearerAuthorization = (headers: Record<string, string>) =>
 oidcAuthService.appendBearerAuthorization(headers);

export const triggerSignIn = () => oidcAuthService.triggerSignIn();
