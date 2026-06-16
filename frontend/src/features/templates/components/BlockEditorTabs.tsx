/**
 * BlockEditorTabs — renders the tab interface for block editing.
 * Extracted to reduce WizardStep2Blocks complexity.
 *
 * Tabs: Properties, Content, Description, Comments
 *
 * NOTA G1-Tabs: NO migrado a `Tabs` de shared-ui-react a propósito — paridad
 * visual imposible hoy: el Trigger activo shared usa `text-text-primary`
 * (#212529) y la List `border-ui-border-l` (#E9ECEF), vs `text-odoo-purple`
 * (#714B67) activo y `border-ui-border` (#D0D0D0) aquí (convención de tabs de
 * dms, ver TemplateReviewView/BlockChangesPanel). Override por className no es
 * determinista en Tailwind (utilidades en conflicto). Además el badge de
 * comentarios y el disabled global durante autosave no tienen equivalente.
 * Candidato: parametrizar color activo/borde en maya_platform y migrar entonces.
 */
import { useTranslation } from 'react-i18next';

type TabId = 'properties' | 'content' | 'description' | 'comments';

interface BlockEditorTabsProps {
  activeTab: TabId;
  onTabChange: (tab: TabId) => void;
  isDark?: boolean;
  hasComments?: boolean;
  isSaving?: boolean;
}

export function BlockEditorTabs({
  activeTab,
  onTabChange,
  hasComments = false,
  isSaving = false,
}: BlockEditorTabsProps) {
  const { t } = useTranslation('templates');

  const tabs: Array<{ id: TabId; label: string; badge?: boolean }> = [
    { id: 'properties', label: t('editor.tabs.properties') },
    { id: 'content', label: t('editor.tabs.content') },
    { id: 'description', label: t('editor.tabs.description') },
  ];

  if (hasComments) {
    tabs.push({ id: 'comments', label: t('editor.tabs.comments'), badge: hasComments });
  }

  return (
    <div className="flex gap-1 border-b border-ui-border dark:border-ui-dark-border">
      {tabs.map((tab) => (
        <button
          key={tab.id}
          type="button"
          disabled={isSaving}
          onClick={() => onTabChange(tab.id)}
          className={[
            'px-3 py-2 text-sm font-medium transition-colors relative',
            activeTab === tab.id
              ? 'text-odoo-purple dark:text-odoo-dark-purple border-b-2 border-odoo-purple dark:border-odoo-dark-purple'
              : 'text-text-secondary dark:text-text-dark-secondary hover:text-text-primary dark:hover:text-text-dark-primary',
            'disabled:opacity-50 disabled:pointer-events-none',
          ].join(' ')}
        >
          {tab.label}
          {tab.badge && (
            <span className="absolute -top-2 -right-2 w-2 h-2 bg-orange-500 rounded-full" />
          )}
        </button>
      ))}
    </div>
  );
}
