import type { LayoutItem, WidgetRegistry } from '@maya/shared-dashboard-react';
import RecentDocumentsWidget from './RecentDocumentsWidget';
import PendingValidationsWidget from './PendingValidationsWidget';

/** Definiciones de widgets disponibles en el dashboard del DMS. */
export const WIDGET_REGISTRY: WidgetRegistry = {
  'recent-documents': {
    id: 'recent-documents',
    titleKey: 'dashboard.widgets.recentDocuments',
    defaultSize: { w: 6, h: 4 },
    minSize: { w: 4, h: 3 },
    component: RecentDocumentsWidget,
  },
  'pending-validations': {
    id: 'pending-validations',
    titleKey: 'dashboard.widgets.pendingValidations',
    hideTitle: true,
    defaultSize: { w: 4, h: 2 },
    minSize: { w: 3, h: 2 },
    component: PendingValidationsWidget,
  },
};

/** Layout por defecto al primer arranque. */
export const DEFAULT_LAYOUT: LayoutItem[] = [
  { i: 'recent-documents', x: 0, y: 0, w: 6, h: 4, minW: 4, minH: 3 },
  { i: 'pending-validations', x: 6, y: 0, w: 4, h: 2, minW: 3, minH: 2 },
];
