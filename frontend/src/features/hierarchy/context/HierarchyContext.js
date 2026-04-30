import { createContext, useContext } from 'react';
import { useAcademicHierarchyLoad } from '../hooks/useAcademicHierarchyLoad';
const HierarchyContext = createContext(undefined);
export function HierarchyProvider({ children }) {
    const { hierarchy, loading, error } = useAcademicHierarchyLoad();
    return (<HierarchyContext.Provider value={{ hierarchy, loading, error }}>
      {children}
    </HierarchyContext.Provider>);
}
export function useHierarchy() {
    const context = useContext(HierarchyContext);
    if (context === undefined) {
        throw new Error('useHierarchy must be used within a HierarchyProvider');
    }
    return context;
}
