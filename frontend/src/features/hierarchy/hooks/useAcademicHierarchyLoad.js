import { useEffect, useState } from 'react';
import { fetchAcademicHierarchy } from '../../../api/academicHierarchy';
let hierarchyCache = null;
let hierarchyInFlight = null;
/**
 * Carga la jerarquía académica una vez al montar.
 * La llamada HTTP está en api/; aquí solo estado y efecto.
 */
export function useAcademicHierarchyLoad() {
    const [hierarchy, setHierarchy] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    useEffect(() => {
        let cancelled = false;
        const load = async () => {
            if (hierarchyCache !== null) {
                setHierarchy(hierarchyCache);
                setError(null);
                setLoading(false);
                return;
            }
            try {
                setLoading(true);
                if (hierarchyInFlight === null) {
                    hierarchyInFlight = fetchAcademicHierarchy().then((data) => {
                        hierarchyCache = data;
                        return data;
                    });
                }
                const data = await hierarchyInFlight;
                if (!cancelled) {
                    setHierarchy(data);
                    setError(null);
                }
            }
            catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err : new Error('Unknown error'));
                }
            }
            finally {
                hierarchyInFlight = null;
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };
        void load();
        return () => {
            cancelled = true;
        };
    }, []);
    return { hierarchy, loading, error };
}
