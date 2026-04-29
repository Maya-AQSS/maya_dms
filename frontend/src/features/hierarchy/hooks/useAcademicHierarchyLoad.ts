import { useEffect, useState } from'react';
import { fetchAcademicHierarchy } from'../../../api/academicHierarchy';
import type { AcademicHierarchy } from'../../../types/hierarchy';

let hierarchyCache: AcademicHierarchy | null = null;
let hierarchyInFlight: Promise<AcademicHierarchy> | null = null;

/**
 * Carga la jerarquía académica una vez al montar.
 * La llamada HTTP está en api/; aquí solo estado y efecto.
 */
export function useAcademicHierarchyLoad(): {
 hierarchy: AcademicHierarchy;
 loading: boolean;
 error: Error | null;
} {
 const [hierarchy, setHierarchy] = useState<AcademicHierarchy>([]);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState<Error | null>(null);

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
 } catch (err) {
 if (!cancelled) {
 setError(err instanceof Error ? err : new Error('Unknown error'));
 }
 } finally {
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
