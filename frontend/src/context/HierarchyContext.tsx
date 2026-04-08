import React, { createContext, useContext, useEffect, useState } from 'react';
import { AcademicHierarchy } from '../types/hierarchy';

interface HierarchyContextState {
  hierarchy: AcademicHierarchy;
  loading: boolean;
  error: Error | null;
}

const HierarchyContext = createContext<HierarchyContextState | undefined>(undefined);

export function HierarchyProvider({ children }: { children: React.ReactNode }) {
  const [hierarchy, setHierarchy] = useState<AcademicHierarchy>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    // Only loads once per session
    const fetchHierarchy = async () => {
      try {
        setLoading(true);
        // Emulating token if JWT setup isn't finished locally yet, usually handled by interceptors
        // In this workspace, F-01.1 is assumed implemented soon or already.
        // We use the Vite environment variable VITE_API_URL or fallback.
        const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8001/api/v1';
        
        const response = await fetch(`${apiUrl}/hierarchy`, {
          headers: {
            'Accept': 'application/json',
            // 'Authorization': `Bearer ${localStorage.getItem('token')}` // Uncomment when auth is ready
          }
        });
        
        if (!response.ok) {
          throw new Error('Failed to fetch academic hierarchy');
        }
        
        const data: AcademicHierarchy = await response.json();
        setHierarchy(data);
      } catch (err) {
        setError(err instanceof Error ? err : new Error('Unknown error'));
      } finally {
        setLoading(false);
      }
    };

    fetchHierarchy();
  }, []);

  return (
    <HierarchyContext.Provider value={{ hierarchy, loading, error }}>
      {children}
    </HierarchyContext.Provider>
  );
}

export function useHierarchy() {
  const context = useContext(HierarchyContext);
  if (context === undefined) {
    throw new Error('useHierarchy must be used within a HierarchyProvider');
  }
  return context;
}
