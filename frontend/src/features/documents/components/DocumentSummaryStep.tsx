import { Button } from '@ceedcv-maya/shared-ui-react';
import { useTranslation } from 'react-i18next';
import { SubmissionChangelogReadonly } from '../../../components/VersionChangelogModal';
import type { DocumentDetail, DocumentDisplayBlock } from '../../../types/documents';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../../templates/blockUiState';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import { DocSummaryRow, DocumentBlockDescriptionView } from './DocumentWizardSubviews';
import {
  type BlockViewTab,
  documentStatusLabel,
  type ReviewerView,
  type VisibilityRuleMode,
} from './documentWizardUtils';

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

interface DocumentSummaryStepProps {
  detail: DocumentDetail;
  isValidateMode: boolean;
  transferError: string | null;
  visibilityRule: VisibilityRuleMode;
  reviewerListKind: 'document' | 'template_fallback' | 'none';
  documentReviewers: ReviewerView[];
  summaryError: string | null;
  sortedBlocks: DocumentDisplayBlock[];
  summaryBlockKey: string | null;
  onSelectSummaryBlock: (key: string) => void;
  saveStatus: SaveStatus;
  summaryBlockTab: BlockViewTab;
  onSelectSummaryTab: (tab: BlockViewTab) => void;
  selectedSummaryBlock: DocumentDisplayBlock | null;
  onPreview: () => void;
}

/** Summary step: properties recap, reviewer list, and per-block content preview. */
export function DocumentSummaryStep({
  detail,
  isValidateMode,
  transferError,
  visibilityRule,
  reviewerListKind,
  documentReviewers,
  summaryError,
  sortedBlocks,
  summaryBlockKey,
  onSelectSummaryBlock,
  saveStatus,
  summaryBlockTab,
  onSelectSummaryTab,
  selectedSummaryBlock,
  onPreview,
}: DocumentSummaryStepProps) {
  const { t } = useTranslation(['documents', 'common']);

  return (
    <div className="flex-1 min-h-0 flex flex-col px-6 py-5 space-y-4 overflow-hidden">
      {transferError && (
        <div className="shrink-0 rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
          {transferError}
        </div>
      )}
      {isValidateMode && (
        <p className="text-xs text-text-muted text-center shrink-0">
          Revisa el resumen del documento y confirma si lo apruebas o lo rechazas.
        </p>
      )}
      {isValidateMode && detail.submission_changelog?.trim() ? (
        <SubmissionChangelogReadonly text={detail.submission_changelog.trim()} />
      ) : null}

      <div className="shrink-0 bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden grid grid-cols-2 animate-in fade-in slide-in-from-top-1 w-full">
        {/* Columna izquierda — Propiedades */}
        <div className="px-5 py-4 border-r border-ui-border dark:border-ui-dark-border">
          <p className="text-xs font-bold uppercase tracking-widest text-text-secondary mb-3">
            {t('common:properties')}
          </p>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-0">
            <DocSummaryRow label={t('list.titleColumn')} value={detail?.title} />
            <DocSummaryRow
              label={t('table.columns.status')}
              value={detail ? documentStatusLabel(detail.status, t) : ''}
            />
            <DocSummaryRow
              label={t('list.versionColumn')}
              value={detail ? `v${detail.current_version}` : ''}
            />
            {visibilityRule !== 'personal' && (
              <>
                <DocSummaryRow label={t('fields.studyType')} value={detail?.study_type_id} />
                <DocSummaryRow label={t('fields.study')} value={detail?.study_id} />
                <DocSummaryRow label={t('fields.module')} value={detail?.module_id} />
              </>
            )}
            <DocSummaryRow
              label={t('summary.deliveryDeadline')}
              value={
                detail?.delivery_deadline
                  ? new Date(detail.delivery_deadline).toLocaleDateString()
                  : null
              }
            />
          </dl>
        </div>

        {/* Columna derecha — Revisores / validadores */}
        <div className="px-5 py-4 space-y-3">
          <p className="text-xs font-bold uppercase tracking-widest text-text-secondary">
            {reviewerListKind === 'document'
              ? 'Validadores del documento'
              : reviewerListKind === 'template_fallback'
                ? 'Quién validará (revisores de plantilla)'
                : 'Revisores / validadores'}
          </p>
          {documentReviewers.length > 0 ? (
            <div className="space-y-1">
              {reviewerListKind === 'template_fallback' && (
                <p className="text-xs text-text-muted dark:text-text-dark-muted leading-snug">
                  La plantilla no define validadores de documento. Los revisores de plantilla
                  listados abajo no aplican a la revisión del documento; al publicar no pasará por
                  validación.
                </p>
              )}
              <ul className="mt-1 space-y-1 text-xs">
                {documentReviewers.map((reviewer) => (
                  <li key={reviewer.id}>• {reviewer.name}</li>
                ))}
              </ul>
            </div>
          ) : (
            <p className="text-xs text-text-muted italic">
              {reviewerListKind === 'none'
                ? 'La plantilla no tiene revisores ni validadores de documento configurados.'
                : '—'}
            </p>
          )}
          {summaryError && (
            <p className="mt-2 text-xs text-danger-dark dark:text-danger">{summaryError}</p>
          )}
        </div>
      </div>

      <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1 w-full">
        <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
          <span className="text-xs font-bold uppercase tracking-widest text-text-secondary">
            Contenido — {sortedBlocks.length} bloque{sortedBlocks.length !== 1 ? 's' : ''}
          </span>
          <Button type="button" variant="outline" size="sm" onClick={onPreview}>
            PREVISUALIZAR
          </Button>
        </div>
        {sortedBlocks.length === 0 ? (
          <div className="p-5">
            <p className="text-xs text-warning-dark italic">{t('noBlocks')}</p>
          </div>
        ) : (
          <div className="flex-1 min-h-0 grid" style={{ gridTemplateColumns: '200px 1fr' }}>
            <div className="border-r border-ui-border dark:border-ui-dark-border p-3 overflow-y-auto">
              <div className="space-y-1">
                {sortedBlocks.map((block, i) => {
                  const key = block.document_block_id ?? block.template_block_id;
                  const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                  const fallbackKey = sortedBlocks[0]
                    ? (sortedBlocks[0].document_block_id ?? sortedBlocks[0].template_block_id)
                    : null;
                  const isSelected = key === (summaryBlockKey ?? fallbackKey);
                  return (
                    <button
                      key={key}
                      type="button"
                      onClick={() => onSelectSummaryBlock(key)}
                      className={[
                        'w-full text-left flex items-center gap-2 px-2.5 py-2 rounded-lg border transition-all',
                        isSelected
                          ? 'bg-odoo-purple/10 border-odoo-purple/30 dark:bg-odoo-dark-purple/15'
                          : 'bg-transparent border-ui-border dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border',
                      ].join(' ')}
                    >
                      <span className="shrink-0 text-xs font-bold text-text-muted w-4 text-right">
                        {i + 1}
                      </span>
                      <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
                        {block.title || 'Sin nombre'}
                      </span>
                      <span
                        className={`shrink-0 px-1.5 py-0.5 rounded text-xs font-bold uppercase ${cfg.badgeCls}`}
                      >
                        {cfg.label}
                      </span>
                    </button>
                  );
                })}
              </div>
              <div className="absolute right-4 top-1/2 -translate-y-1/2 flex items-center gap-2">
                {saveStatus === 'saving' && (
                  <span className="text-xs text-text-muted italic animate-pulse">
                    {t('common:saving')}
                  </span>
                )}
                {saveStatus === 'saved' && (
                  <span className="text-xs text-success-dark font-bold">✓ Guardado</span>
                )}
                {saveStatus === 'error' && (
                  <span className="text-xs text-danger-dark font-bold">
                    {t('common:errors.saveFailed')}
                  </span>
                )}
              </div>
            </div>
            <div className="flex flex-col min-w-0 min-h-0 preview-content">
              <div className="shrink-0 px-4 pt-3 border-b border-ui-border dark:border-ui-dark-border">
                <div className="flex gap-0 -mb-px">
                  {(
                    [
                      { id: 'content', label: 'Contenido' },
                      { id: 'description', label: 'Descripción' },
                    ] as const
                  ).map((tab) => {
                    const isActive = summaryBlockTab === tab.id;
                    return (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => onSelectSummaryTab(tab.id)}
                        className={[
                          'px-3 py-1.5 text-xs border-b-2 transition-all',
                          isActive
                            ? 'border-odoo-purple text-odoo-purple font-medium cursor-default'
                            : 'border-transparent text-text-muted hover:text-text-primary cursor-pointer',
                        ].join(' ')}
                        disabled={isActive}
                      >
                        {tab.label}
                      </button>
                    );
                  })}
                </div>
              </div>
              <div className="flex-1 min-h-0 overflow-y-auto p-4">
                {selectedSummaryBlock ? (
                  summaryBlockTab === 'content' ? (
                    (() => {
                      const nodes = normalizeBlockContentForEditor(selectedSummaryBlock.content);
                      const hasNodes = nodes.length > 0;
                      return hasNodes ? (
                        <BlockContentHtml content={nodes} />
                      ) : (
                        <span className="text-xs text-text-muted italic">
                          {t('common:noBlockContent')}
                        </span>
                      );
                    })()
                  ) : selectedSummaryBlock.description != null &&
                    selectedSummaryBlock.description !== '' ? (
                    <DocumentBlockDescriptionView description={selectedSummaryBlock.description} />
                  ) : (
                    <span className="text-xs text-text-muted italic">
                      Este bloque no tiene descripción.
                    </span>
                  )
                ) : null}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
