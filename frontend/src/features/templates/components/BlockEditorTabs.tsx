/**
 * BlockEditorTabs — renders the tab interface for block editing.
 * Extracted to reduce WizardStep2Blocks complexity.
 *
 * Tabs: Properties, Content, Description, Comments
 */
import { useTranslation } from 'react-i18next';
import { Button, TextInput, FieldLabel } from '@ceedcv-maya/shared-ui-react';
import type { BlockUiState } from '../blockUiState';

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
  isDark = false,
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
              ? 'text-odoo-purple border-b-2 border-odoo-purple'
              : 'text-text-secondary hover:text-text-primary',
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
