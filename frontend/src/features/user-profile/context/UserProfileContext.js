import { createContext, useCallback, useContext, useEffect, useMemo, useState, } from 'react';
import { useOidcSession } from '@maya/shared-auth-react';
import { fetchMe } from '../../../api/users';
const UserProfileContext = createContext(undefined);
export function UserProfileProvider({ children }) {
    const { isOidcSignedIn } = useOidcSession();
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const load = useCallback(async () => {
        if (!isOidcSignedIn) {
            setProfile(null);
            setError(null);
            return;
        }
        setLoading(true);
        setError(null);
        try {
            const res = await fetchMe();
            setProfile(res.data);
        }
        catch (e) {
            setProfile(null);
            setError(e instanceof Error ? e : new Error(String(e)));
        }
        finally {
            setLoading(false);
        }
    }, [isOidcSignedIn]);
    useEffect(() => {
        if (!isOidcSignedIn) {
            setProfile(null);
            setError(null);
            setLoading(false);
            return;
        }
        void load();
    }, [isOidcSignedIn, load]);
    const hasPermission = useCallback((code) => profile?.permissions.includes(code) ?? false, [profile]);
    const value = useMemo(() => ({
        profile,
        loading,
        error,
        reload: load,
        hasPermission,
    }), [profile, loading, error, load, hasPermission]);
    return <UserProfileContext.Provider value={value}>{children}</UserProfileContext.Provider>;
}
export function useUserProfile() {
    const ctx = useContext(UserProfileContext);
    if (ctx === undefined) {
        throw new Error('useUserProfile debe usarse dentro de UserProfileProvider');
    }
    return ctx;
}
/** Iniciales para avatar (nombre o email). */
export function profileDisplayInitials(profile) {
    if (!profile)
        return 'U';
    const base = profile.name?.trim() || profile.email?.trim() || '';
    if (!base)
        return 'U';
    const parts = base.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase().slice(0, 2) || 'U';
    }
    return base.slice(0, 2).toUpperCase();
}
