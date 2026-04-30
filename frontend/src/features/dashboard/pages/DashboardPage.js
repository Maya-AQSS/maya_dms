import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DashboardEditToggleButton, DashboardEditToolbar, WidgetGrid, useDashboardLayoutLocal, } from '@maya/shared-dashboard-react';
import { PageTitle } from '@maya/shared-ui-react';
import { WIDGET_REGISTRY, DEFAULT_LAYOUT } from '../widgets/registry';
const STORAGE_KEY = 'maya:dms:dashboard-layout';
function DashboardSkeleton() {
    return (<div className="p-4 sm:p-6 grid grid-cols-12 gap-4 animate-pulse">
      <div className="col-span-12 sm:col-span-8 h-64 bg-ui-border-l dark:bg-ui-dark-border rounded-2xl"/>
      <div className="col-span-12 sm:col-span-4 h-32 bg-ui-border-l dark:bg-ui-dark-border rounded-2xl"/>
    </div>);
}
/** Dashboard principal con grid de widgets drag-and-drop persistido en localStorage. */
export function DashboardPage() {
    const { t } = useTranslation('common');
    const { layout, loading, saveLayout, resetToDefault } = useDashboardLayoutLocal({
        storageKey: STORAGE_KEY,
        defaultLayout: DEFAULT_LAYOUT,
    });
    const [editable, setEditable] = useState(false);
    const [draftLayout, setDraftLayout] = useState(null);
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
    const handleLayoutChange = useCallback((next) => {
        if (!editable)
            return;
        setDraftLayout(next);
    }, [editable]);
    const handleRemoveWidget = useCallback((widgetId) => {
        setDraftLayout((prev) => (prev ?? layout).filter((item) => item.i !== widgetId));
    }, [layout]);
    const handleAddWidget = useCallback((widgetId) => {
        const def = WIDGET_REGISTRY[widgetId];
        if (!def)
            return;
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
    }, [draftLayout, layout]);
    const handleReset = useCallback(async () => {
        await resetToDefault();
        setDraftLayout(null);
        setEditable(false);
    }, [resetToDefault]);
    if (loading) {
        return <DashboardSkeleton />;
    }
    return (<>
      <PageTitle title={t('nav.dashboard', { defaultValue: 'Panel' })} actions={editable ? (<DashboardEditToolbar layout={activeLayout} registry={WIDGET_REGISTRY} t={t} onSave={handleSave} onCancel={handleCancel} onReset={handleReset} onAddWidget={handleAddWidget} labels={{
                save: t('actions.save'),
                cancel: t('actions.cancel'),
                reset: t('dashboard.reset', { defaultValue: 'Restablecer' }),
                addWidget: t('dashboard.addWidget', { defaultValue: 'Añadir widget' }),
            }}/>) : (<DashboardEditToggleButton editable={editable} onToggle={handleToggleEdit}/>)}/>

      <WidgetGrid registry={WIDGET_REGISTRY} layout={activeLayout} onLayoutChange={handleLayoutChange} editable={editable} onRemoveWidget={handleRemoveWidget} t={t} emptyKey="dashboard.noWidgets" removeAriaLabel={t('actions.delete')}/>
    </>);
}
export default DashboardPage;
