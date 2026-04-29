import type { ReactNode } from'react';

export class AuthService {
 keycloak = {
 authenticated: true,
 token:'vitest-token',
 updateToken: async () => true,
 login: () => undefined,
 };

 constructor(_opts: unknown) {
 void _opts;
 }
}

export function AuthProvider({ children }: { children: ReactNode }) {
 return children;
}

export function useAuth() {
 return {
 isLoading: false,
 isAuthenticated: true,
 login: () => undefined,
 logout: () => undefined,
 user: null as { sub: string; email: string; name: string; preferred_username: string } | null,
 };
}
