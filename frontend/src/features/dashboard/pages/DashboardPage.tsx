import { useCallback, useState } from'react';
import { useTranslation } from'react-i18next';
import {
 DashboardEditToggleButton,
 WidgetGrid,
 useDashboardLayoutLocal,
 type LayoutItem,
} from'@maya/shared-dashboard-react';
import { PageTitle } from'@maya/shared-ui-react';
import { WIDGET_REGISTRY, DEFAULT_LAYOUT } from'../widgets/registry';

const STORAGE_KEY ='maya:dms:dashboard-layout';

function DashboardSkeleton() {
 return (<div className="p-4 sm:p-6 grid grid-cols-12 gap-4 animate-pulse">
 <div className="col-span-12 sm:col-span-8 h-64 bg-outline-variant rounded-2xl" />
 <div className="col-span-12 sm:col-span-4 h-32 bg-outline-variant rounded-2xl" />
 </div>
 );
}

/** Dashboard principal con grid de widgets drag-and-drop persistido en localStorage. */
export function DashboardPage() {
 const { t } = useTranslation('common');
 const { layout, loading, saveLayout, resetToDefault } = useDashboardLayoutLocal({
 storageKey: STORAGE_KEY,
 defaultLayout: DEFAULT_LAYOUT,
 });
 const [editable, setEditable] = useState(false);
 const [draftLayout, setDraftLayout] = useState<LayoutItem[] | null>(null);

 const activeLayout = editable ? (draftLayout ?? layout) : layout;

 const handleToggleEdit = useCallback(() => {
 setEditable((prev) => {
 if (prev) {
 setDraftLayout(null);
 return false;
 }
 setDraftLayout(layout);
 return true;
 });
 }, [layout]);

 const handleSave = useCallback(async () => {
 await saveLayout(draftLayout ?? layout);
 setEditable(false);
 setDraftLayout(null);
 }, [saveLayout, draftLayout, layout]);

 const handleCancel = useCallback(() => {
 setDraftLayout(null);
 setEditable(false);
 }, []);

 const handleLayoutChange = useCallback((next: LayoutItem[]) => {
 if (!editable) return;
 setDraftLayout(next);
 },
 [editable],
 );

 const handleRemoveWidget = useCallback((widgetId: string) => {
 setDraftLayout((prev) => (prev ?? layout).filter((item) => item.i !== widgetId));
 },
 [layout],
 );

 const handleReset = useCallback(async () => {
 await resetToDefault();
 setDraftLayout(null);
 setEditable(false);
 }, [resetToDefault]);

 if (loading) {
 return <DashboardSkeleton />;
 }

 return (<div className="p-4 sm:p-6">
 <div className="flex items-center justify-between mb-4">
 <PageTitle title={t('nav.dashboard', { defaultValue:'Panel' })} />
 <div className="flex items-center gap-2">
 {editable && (<>
 <button
 type="button"
 onClick={handleReset}
 className="text-xs px-3 py-1.5 rounded-md border border-outline text-on-surface-variant hover:bg-surface transition-colors"
 >
 {t('dashboard.reset', { defaultValue:'Restablecer' })}
 </button>
 <button
 type="button"
 onClick={handleCancel}
 className="text-xs px-3 py-1.5 rounded-md border border-outline text-on-surface-variant hover:bg-surface transition-colors"
 >
 {t('actions.cancel')}
 </button>
 <button
 type="button"
 onClick={handleSave}
 className="text-xs px-3 py-1.5 rounded-md bg-primary text-white hover:opacity-90 transition-opacity"
 >
 {t('actions.save')}
 </button>
 </>
 )}
 {!editable && (<DashboardEditToggleButton editable={editable} onToggle={handleToggleEdit} />
 )}
 </div>
 </div>
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
 </div>
 );
}

export default DashboardPage;
