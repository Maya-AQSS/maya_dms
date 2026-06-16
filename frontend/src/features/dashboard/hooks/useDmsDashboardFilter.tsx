import { createContext, useContext, useState, type ReactNode } from 'react';

type PendingFilter = 'all' | 'template' | 'document';

interface DmsDashboardFilterContextValue {
  filter: PendingFilter;
  setFilter: (filter: PendingFilter) => void;
}

const DmsDashboardFilterContext = createContext<DmsDashboardFilterContextValue | null>(null);

export function DmsDashboardFilterProvider({ children }: { children: ReactNode }) {
  const [filter, setFilter] = useState<PendingFilter>('all');
  return (
    <DmsDashboardFilterContext.Provider value={{ filter, setFilter }}>
      {children}
    </DmsDashboardFilterContext.Provider>
  );
}

export function useDmsDashboardFilter(): DmsDashboardFilterContextValue {
  const ctx = useContext(DmsDashboardFilterContext);
  if (!ctx) throw new Error('useDmsDashboardFilter must be used inside DmsDashboardFilterProvider');
  return ctx;
}
