import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DocumentDiffPanel } from './DocumentDiffPanel';
import { DocumentBlockHistoryPanel } from './DocumentBlockHistoryPanel';
import type { DocumentDisplayBlock, DocumentReviewCycleSnapshot } from '../../../types/documents';

type Tab = 'changed' | 'history';

type Props = {
  /** Bloques (normalmente el bloque seleccionado) para el diff vs. plantilla. */
  diffBlocks: DocumentDisplayBlock[];
  /** Lista completa para numerar bloques en el diff. */
  allBlocks?: DocumentDisplayBlock[];
  /** Muestra la pestaña «Cambiado» (diff vs. plantilla). */
  showChangedTab: boolean;
  /** `document_block_id` del bloque para la pestaña «Histórico». */
  historyBlockId: string | null;
  historyBlockNumber: number | string;
  reviewHistory: DocumentReviewCycleSnapshot[];
  /** Muestra la pestaña «Histórico» (revisiones del bloque). */
  showHistoryTab: boolean;
  onClose: () => void;
};

/**
 * Panel unificado de cambios de un bloque con dos pestañas:
 * «Cambiado» (diferencias respecto a la plantilla) e «Histórico» (revisiones).
 * Reutiliza {@link DocumentDiffPanel} y {@link DocumentBlockHistoryPanel} en modo embebido.
 */
export function BlockChangesPanel({
  diffBlocks,
  allBlocks,
  showChangedTab,
  historyBlockId,
  historyBlockNumber,
  reviewHistory,
  showHistoryTab,
  onClose,
}: Props) {
  const { t } = useTranslation('documents');
  const [tab, setTab] = useState<Tab>(showChangedTab ? 'changed' : 'history');

  // Si la pestaña activa deja de estar disponible, cae a la otra.
  const activeTab: Tab =
    tab === 'changed' && !showChangedTab
      ? 'history'
      : tab === 'history' && !showHistoryTab
        ? 'changed'
        : tab;

  const renderTab = (key: Tab, label: string, available: boolean) =>
    available ? (
      <button
        key={key}
        type="button"
        onClick={() => setTab(key)}
        className={[
          'px-3 py-2 text-2xs font-black uppercase tracking-[0.12em] border-b-2 transition-colors cursor-pointer',
          activeTab === key
            ? 'border-odoo-purple text-odoo-purple'
            : 'border-transparent text-text-muted hover:text-text-primary dark:hover:text-text-dark-primary',
        ].join(' ')}
      >
        {label}
      </button>
    ) : null;

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Tab bar */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card pl-2 pr-2">
        <div className="flex items-center flex-1 min-w-0">
          {renderTab('changed', t('changes.tabChanged'), showChangedTab)}
          {renderTab('history', t('changes.tabHistory'), showHistoryTab)}
        </div>
        <button
          type="button"
          onClick={onClose}
          aria-label={t('diff.closePanel')}
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Active tab content */}
      <div className="flex-1 min-h-0 overflow-hidden">
        {activeTab === 'changed' ? (
          <DocumentDiffPanel embedded blocks={diffBlocks} allBlocks={allBlocks} />
        ) : historyBlockId ? (
          <DocumentBlockHistoryPanel
            embedded
            blockId={historyBlockId}
            blockNumber={historyBlockNumber}
            history={reviewHistory}
          />
        ) : null}
      </div>
    </div>
  );
}
