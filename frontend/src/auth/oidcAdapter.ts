/**
 * Única capa que integra el cliente OIDC (Keycloak vía @maya/shared-auth-react).
 * El resto de la aplicación no debe importar ese paquete: use {@link useOidcSession}
 * para el estado de sesión y {@link appendBearerAuthorization} / {@link triggerSignIn} para HTTP.
 *
 * Identidad y permisos de negocio: GET /api/v1/me (feature `user-profile`).
 */
import { AuthService } from '@maya/shared-auth-react';

export const oidcAuthService = new AuthService({
  url: import.meta.env.VITE_KEYCLOAK_URL,
  realm: import.meta.env.VITE_KEYCLOAK_REALM,
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID,
});

/** Añade Authorization: Bearer si hay sesión OIDC activa (renueva token si hace falta). */
export async function appendBearerAuthorization(headers: Record<string, string>): Promise<void> {
  const kc = oidcAuthService.keycloak;
  if (!kc.authenticated) {
    return;
  }
  await kc.updateToken(30).catch(() => {
    kc.login();
  });
  if (kc.token) {
    headers.Authorization = `Bearer ${kc.token}`;
  }
}

export function triggerSignIn(): void {
  oidcAuthService.keycloak.login();
}
