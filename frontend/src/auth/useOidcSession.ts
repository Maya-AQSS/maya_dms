import { useAuth } from'@maya/shared-auth-react';

/**
 * Estado de la sesión técnica (token OIDC). No sustituye al perfil de negocio (`useUserProfile`).
 */
export function useOidcSession() {
 const { isLoading, isAuthenticated, login, user, logout } = useAuth();

 return {
 isOidcLoading: isLoading,
 isOidcSignedIn: isAuthenticated,
 beginSignIn: login,
 user,
 logout,
 };
}
