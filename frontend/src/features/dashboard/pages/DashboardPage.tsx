import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  DashboardEditToggleButton,
  DashboardEditToolbar,
  DashboardSkeleton,
  WidgetGrid,
  useDashboardLayoutLocal,
  type LayoutItem,
  type SkeletonBlock,
} from '@ceedcv-maya/shared-dashboard-react';
import { Alert, PageTitle } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { WIDGET_REGISTRY, DEFAULT_LAYOUT } from '../widgets/registry';

const STORAGE_KEY = 'maya:dms:dashboard-layout';

const SKELETON_BLOCKS: SkeletonBlock[] = [
  { colSpanClasses: 'col-span-12 sm:col-span-8', heightClass: 'h-64' },
  { colSpanClasses: 'col-span-12 sm:col-span-4', heightClass: 'h-32' },
];

/** Dashboard principal con grid de widgets drag-and-drop persistido en localStorage. */
export function DashboardPage() {
  const { t } = useTranslation('common');
  const { hasPermission, loading: profileLoading } = useUserProfile();
  const canViewDashboard = hasPermission(DMS_PERMISSIONS.index);
  const canEditDashboard = hasPermission(DMS_PERMISSIONS.dashboardUpdate);
  const { layout, loading, saveLayout, resetToDefault } = useDashboardLayoutLocal({
    storageKey: STORAGE_KEY,
    defaultLayout: DEFAULT_LAYOUT,
  });
  const [editable, setEditable] = useState(false);
  const [draftLayout, setDraftLayout] = useState<LayoutItem[] | null>(null);

  const activeLayout = editable ? (draftLayout ?? layout) : layout;

  const handleToggleEdit = useCallback(() => {
    if (!canEditDashboard) return;
    setEditable((prev) => {
      if (prev) {
        setDraftLayout(null);
        return false;
      }
      setDraftLayout(layout);
      return true;
    });
  }, [canEditDashboard, layout]);

  const handleSave = useCallback(async () => {
    await saveLayout(draftLayout ?? layout);
    setEditable(false);
    setDraftLayout(null);
  }, [saveLayout, draftLayout, layout]);

  const handleCancel = useCallback(() => {
    setDraftLayout(null);
    setEditable(false);
  }, []);

  const handleLayoutChange = useCallback(
    (next: LayoutItem[]) => {
      if (!editable) return;
      setDraftLayout(next);
    },
    [editable],
  );

  const handleRemoveWidget = useCallback(
    (widgetId: string) => {
      setDraftLayout((prev) => (prev ?? layout).filter((item) => item.i !== widgetId));
    },
    [layout],
  );

  const handleAddWidget = useCallback(
    (widgetId: string) => {
      const def = WIDGET_REGISTRY[widgetId];
      if (!def) return;
      const current = draftLayout ?? layout;
      const maxY = current.reduce((m, item) => Math.max(m, item.y + item.h), 0);
      setDraftLayout([
        ...current,
        {
          i: widgetId,
          x: 0,
          y: maxY,
          w: def.defaultSize.w,
          h: def.defaultSize.h,
          minW: def.minSize.w,
          minH: def.minSize.h,
        },
      ]);
    },
    [draftLayout, layout],
  );

  const handleReset = useCallback(async () => {
    await resetToDefault();
    setDraftLayout(null);
    setEditable(false);
  }, [resetToDefault]);

  if (profileLoading || loading) {
    return <DashboardSkeleton blocks={SKELETON_BLOCKS} />;
  }

  return (
    <>
      <PageTitle
        title={t('nav.dashboard', { defaultValue: 'Panel' })}
        image={{ src: '/dashboard-header.png', alt: t('nav.dashboard', { defaultValue: 'Panel' }) }}
        actions={
          canEditDashboard && editable ? (
            <DashboardEditToolbar
              layout={activeLayout}
              registry={WIDGET_REGISTRY}
              t={t}
              onSave={handleSave}
              onCancel={handleCancel}
              onReset={handleReset}
              onAddWidget={handleAddWidget}
              labels={{
                save: t('actions.save'),
                cancel: t('actions.cancel'),
                reset: t('actions.reset', { defaultValue: 'Restablecer' }),
                addWidget: t('dashboard.addWidget', { defaultValue: 'Añadir widget' }),
              }}
            />
          ) : canEditDashboard ? (
            <DashboardEditToggleButton editable={editable} onToggle={handleToggleEdit} />
          ) : null
        }
      />

      {!canViewDashboard && (
        <Alert tone="warning" className="mb-4">
          {t('dashboard.noIndexPermission', {
            defaultValue:
              'Tienes acceso a DocuCEED (dms.login) pero no permiso para ver el listado del panel (dms.index). Pide dms.index a un administrador.',
          })}
        </Alert>
      )}

      <WidgetGrid
        registry={WIDGET_REGISTRY}
        layout={activeLayout}
        onLayoutChange={handleLayoutChange}
        editable={editable}
        onRemoveWidget={handleRemoveWidget}
        t={t}
        emptyKey="dashboard.noWidgets"
        removeAriaLabel={t('actions.delete')}
      />
    </>
  );
}

export default DashboardPage;
