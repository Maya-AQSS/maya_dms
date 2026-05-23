import { createContext, useContext, type ReactNode } from 'react';
import type { AcademicHierarchy } from '../../../types/hierarchy';
import type { UserTeam } from '../../../api/users';
import { useAcademicHierarchyLoad } from '../hooks/useAcademicHierarchyLoad';

interface HierarchyContextState {
  hierarchy: AcademicHierarchy;
  teams: UserTeam[];
  loading: boolean;
  error: Error | null;
}

const HierarchyContext = createContext<HierarchyContextState | undefined>(undefined);

export function HierarchyProvider({ children }: { children: ReactNode }) {
  const { hierarchy, teams, loading, error } = useAcademicHierarchyLoad();

  return (
    <HierarchyContext.Provider value={{ hierarchy, teams, loading, error }}>
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
