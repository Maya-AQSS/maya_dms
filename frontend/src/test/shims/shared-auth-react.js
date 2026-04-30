export class AuthService {
    keycloak = {
        authenticated: true,
        token: 'vitest-token',
        updateToken: async () => true,
        login: () => undefined,
    };
    constructor(_opts) {
        void _opts;
    }
}
export function AuthProvider({ children }) {
    return children;
}
export function useAuth() {
    return {
        isLoading: false,
        isAuthenticated: true,
        login: () => undefined,
        logout: () => undefined,
        user: null,
    };
}
