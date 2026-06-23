import { Button, Spinner } from '@ceedcv-maya/shared-ui-react';
import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { uploadMedia } from '../../../api/media';
import { ErrorBoundaryWrapper as ErrorBoundary } from '../../../components/ErrorBoundaryWrapper';
import type { DocumentDetail, DocumentDisplayBlock } from '../../../types/documents';
import { countUnreadCommentsForBlock, getCommentsForBlock } from '../../../utils/blockComments';
import { BlockListItem } from '../../blocks-ui/BlockListItem';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../../templates/blockUiState';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';
import { BlockCommentsCard } from '../../templates/components/BlockCommentsCard';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { CoverFillEditor } from '../../templates/cover/CoverFillEditor';
import { isUnresolvedEditableBlock } from '../lib/blockContentEquals';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import { DocumentBlockDescriptionView } from './DocumentWizardSubviews';
import type { BlockViewTab } from './documentWizardUtils';
import { IndexFillEditor } from './IndexFillEditor';
import { isStructuralBlockType, StructuralBlockPreview } from './StructuralBlockPreview';

const BlockNoteEditorPanel = lazy(() =>
  import('../../templates/components/BlockNoteEditorPanel').then((m) => ({
    default: m.BlockNoteEditorPanel,
  })),
);

const ContinuousDocumentEditor = lazy(() =>
  import('./ContinuousDocumentEditor').then((m) => ({ default: m.ContinuousDocumentEditor })),
);

type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

interface BlocksComments {
  reviewComments: BlockComment[];
  showPanel: boolean;
  setShowPanel: (updater: boolean | ((v: boolean) => boolean)) => void;
  loading: boolean;
  error: string | null;
  onSend: (parentId: string | null, body: string) => Promise<void>;
  onEdit: (commentId: string, newBody: string) => Promise<void>;
  onDelete: (commentId: string) => Promise<void>;
  onMarkAsRead: (commentId: string) => Promise<void>;
  onMarkAllBlockAsRead: () => Promise<void>;
  canAdd: boolean;
  canDeleteAny: boolean;
  currentUserId: string | null;
}

interface BlocksEditor {
  isDark: boolean;
  isEditorFullscreen: boolean;
  setIsEditorFullscreen: (v: boolean) => void;
  onFullscreenChange: (v: boolean) => void;
  onContentChange: (content: unknown) => void;
  onFlush: (payload?: unknown) => Promise<void> | void;
  editorFlushRef: { current: (() => void | Promise<void>) | null };
  saveStatus: SaveStatus;
  blockSaveError: string | null;
  isSaving: boolean;
}

interface BlocksViewMode {
  mode: 'per-block' | 'continuous';
  setMode: (updater: (prev: 'per-block' | 'continuous') => 'per-block' | 'continuous') => void;
  isContinuousFullscreen: boolean;
  setIsContinuousFullscreen: (updater: boolean | ((v: boolean) => boolean)) => void;
}

interface DocumentBlocksStepProps {
  documentId?: string | null;
  detail: DocumentDetail | null;
  sortedBlocks: DocumentDisplayBlock[];
  activeBlock: DocumentDisplayBlock | null;
  activeBlockKey: string | null;
  isDraft: boolean;
  canEditBlocks: boolean;
  canDeleteOptionalBlock: boolean;
  isSidebarCollapsed: boolean;
  setIsSidebarCollapsed: (v: boolean) => void;
  blockViewTab: BlockViewTab;
  setBlockViewTab: (t: BlockViewTab) => void;
  completedBlocks: { isCompleted: (id: string) => boolean; toggle: (id: string) => void };
  descriptionBlockKey: string | null;
  setDescriptionBlockKey: (updater: (prev: string | null) => string | null) => void;
  onBlockClick: (key: string) => void;
  onContinue: () => void;
  onShowDeleteBlock: () => void;
  onPersistBlockContent: (blockId: string | null | undefined, payload: unknown) => Promise<void>;
  editor: BlocksEditor;
  viewMode: BlocksViewMode;
  comments: BlocksComments;
}

/** Step 2: block editing — view-mode toggle, continuous editor, and per-block editor with sidebar + comments. */
export function DocumentBlocksStep({
  documentId,
  detail,
  sortedBlocks,
  activeBlock,
  activeBlockKey,
  isDraft,
  canEditBlocks,
  canDeleteOptionalBlock,
  isSidebarCollapsed,
  setIsSidebarCollapsed,
  blockViewTab,
  setBlockViewTab,
  completedBlocks,
  descriptionBlockKey,
  setDescriptionBlockKey,
  onBlockClick,
  onContinue,
  onShowDeleteBlock,
  onPersistBlockContent,
  editor,
  viewMode,
  comments,
}: DocumentBlocksStepProps) {
  const { t } = useTranslation(['documents', 'common', 'templates']);
  const { reviewComments } = comments;
  const { isEditorFullscreen, saveStatus, blockSaveError, isSaving, isDark } = editor;

  return (
    <div
      className={
        isEditorFullscreen
          ? 'fixed inset-0 z-[100] bg-white dark:bg-ui-dark-card flex flex-col'
          : 'flex-1 overflow-visible flex flex-col min-h-0'
      }
    >
      {/* Compact fullscreen header */}
      {isEditorFullscreen && activeBlock && (
        <div className="shrink-0 h-11 px-4 flex items-center gap-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
          <h3 className="flex-1 text-sm font-bold truncate uppercase tracking-widest">
            {activeBlock.title || 'Bloque'}
          </h3>
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
          <Button
            type="button"
            variant="primary"
            size="xs"
            onClick={() => {
              editor.setIsEditorFullscreen(false);
              void onContinue();
            }}
            className="shrink-0"
          >
            Continuar →
          </Button>
        </div>
      )}
      {/* View-mode toggle: por bloque | continuo. Solo cuando NO está en fullscreen
          y solo en desktop (md:); en mobile la vista continua no es óptima. */}
      {!isEditorFullscreen && (
        <div className="hidden md:flex shrink-0 px-5 py-2 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card items-center justify-end gap-2 ">
          <span className="text-xs font-medium text-text-muted">{t('wizard.view')}</span>
          <div className="group flex items-center gap-1 rounded-full border border-ui-border bg-ui-body/60 dark:bg-transparent dark:border-ui-dark-border p-0.5 text-xs hover:border-odoo-purple/80 hover:bg-black/10">
            <button
              type="button"
              onClick={() =>
                viewMode.setMode((prev) => (prev === 'per-block' ? 'continuous' : 'per-block'))
              }
              className={[
                'rounded-full px-2.5 py-1 font-medium transition-colors',
                viewMode.mode === 'per-block'
                  ? 'bg-white dark:opacity-60 shadow-sm text-text-primary duration-900 group-hover:translate-x-2 group-hover:animate-slide group-hover:pl-0 group-hover:pr-5 dark:bg-dark'
                  : 'text-text-muted dark:text-text-dark-muted',
              ].join(' ')}
              aria-pressed={viewMode.mode === 'per-block'}
            >
              {t('documents:wizard.viewMode.perBlock', 'Por bloque')}
            </button>
            <button
              type="button"
              onClick={() =>
                viewMode.setMode((prev) => (prev === 'continuous' ? 'per-block' : 'continuous'))
              }
              className={[
                'rounded-full px-2.5 py-1 font-medium transition-colors',
                viewMode.mode === 'continuous'
                  ? 'bg-white dark:opacity-60 shadow-sm text-text-primary duration-900 group-hover:-translate-x-2 group-hover:animate-slide group-hover:pr-0 group-hover:pl-5 dark:bg-dark'
                  : 'text-text-muted dark:text-text-dark-muted',
              ].join(' ')}
              aria-pressed={viewMode.mode === 'continuous'}
              title={t(
                'documents:wizard.viewMode.continuousHint',
                'Documento completo con edición inline',
              )}
            >
              {t('documents:wizard.viewMode.continuous', 'Continuo')}
            </button>
          </div>
          {viewMode.mode === 'continuous' && (
            <button
              type="button"
              onClick={() => viewMode.setIsContinuousFullscreen((v) => !v)}
              className="ml-2 inline-flex items-center gap-1.5 rounded-full border border-ui-border bg-white dark:bg-ui-dark-card dark:text-light px-3 py-1 text-xs font-medium hover:text-text-secondary hover:border-odoo-purple/80 transition-colors dark:border-ui-dark-border"
              title={
                viewMode.isContinuousFullscreen
                  ? t(
                      'documents:wizard.viewMode.exitFullscreenTitle',
                      'Salir de pantalla completa (Esc)',
                    )
                  : t('documents:wizard.viewMode.enterFullscreenTitle', 'Pantalla completa')
              }
              aria-pressed={viewMode.isContinuousFullscreen}
            >
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
              >
                {viewMode.isContinuousFullscreen ? (
                  <>
                    <polyline points="4 14 10 14 10 20" />
                    <polyline points="20 10 14 10 14 4" />
                    <line x1="14" y1="10" x2="21" y2="3" />
                    <line x1="3" y1="21" x2="10" y2="14" />
                  </>
                ) : (
                  <>
                    <polyline points="15 3 21 3 21 9" />
                    <polyline points="9 21 3 21 3 15" />
                    <line x1="21" y1="3" x2="14" y2="10" />
                    <line x1="3" y1="21" x2="10" y2="14" />
                  </>
                )}
              </svg>
              <span>
                {viewMode.isContinuousFullscreen
                  ? t('documents:wizard.viewMode.exitFullscreen', 'Reducir')
                  : t('documents:wizard.viewMode.enterFullscreen', 'Pantalla completa')}
              </span>
            </button>
          )}
        </div>
      )}
      {/* Continuous mode body — only when NOT block-editor fullscreen. Fullscreen always uses the per-block editor. */}
      {!isEditorFullscreen && viewMode.mode === 'continuous' ? (
        <div
          className={
            viewMode.isContinuousFullscreen
              ? 'fixed inset-0 z-[105] overflow-y-auto bg-app-gradient dark:bg-ui-dark-bg animate-in fade-in'
              : 'flex-1 overflow-y-auto bg-app-gradient dark:bg-ui-dark-bg'
          }
        >
          <div className="flex flex-row flex-nowrap items-start gap-8 px-6 py-6">
            <div className="flex-1 min-w-0">
              {/* Fullscreen controls — sit just above the title instead of
                  floating over the content. Exit fullscreen + comments toggle,
                  aligned to the paper's right edge. Esc también sale. */}
              {viewMode.isContinuousFullscreen && (
                <div className="mb-3 flex w-full items-center justify-end gap-2 sticky top-3 z-[85]">
                  <Button
                    type="button"
                    size="xs"
                    variant="outline"
                    className="shadow-md"
                    onClick={() => viewMode.setIsContinuousFullscreen(false)}
                    title={t(
                      'documents:wizard.viewMode.exitFullscreenTitle',
                      'Salir de pantalla completa (Esc)',
                    )}
                  >
                    <span className="inline-flex items-center gap-1.5">
                      <svg
                        width="13"
                        height="13"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                      >
                        <polyline points="4 14 10 14 10 20" />
                        <polyline points="20 10 14 10 14 4" />
                        <line x1="14" y1="10" x2="21" y2="3" />
                        <line x1="3" y1="21" x2="10" y2="14" />
                      </svg>
                      {t('documents:wizard.viewMode.exitFullscreen', 'Reducir')}
                    </span>
                  </Button>
                  {activeBlock?.document_block_id &&
                    (() => {
                      const blockCommentsCount = countUnreadCommentsForBlock(
                        activeBlock.document_block_id,
                        reviewComments,
                      );
                      return (
                        <Button
                          type="button"
                          size="xs"
                          variant="outline"
                          className={
                            comments.showPanel
                              ? 'relative shadow-md text-odoo-purple border-odoo-purple/40 bg-odoo-purple/10'
                              : 'relative shadow-md text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5'
                          }
                          onClick={() => comments.setShowPanel((v) => !v)}
                          aria-pressed={comments.showPanel}
                          title={
                            comments.showPanel
                              ? t('documents:wizard.viewMode.hideComments', 'Ocultar comentarios')
                              : t('documents:wizard.viewMode.showComments', 'Mostrar comentarios')
                          }
                        >
                          <span className="hidden sm:inline">
                            {comments.showPanel
                              ? t('documents:wizard.viewMode.hideComments', 'Ocultar comentarios')
                              : t('documents:wizard.viewMode.showComments', 'Comentarios')}
                          </span>
                          <span className="sm:hidden" aria-hidden>
                            💬
                          </span>
                          {!comments.showPanel && blockCommentsCount > 0 && (
                            // biome-ignore lint/a11y/useAriaPropsSupportedByRole: count badge intentionally carries an accessible label; the rule's role inference is too strict for a numeric badge.
                            <span
                              aria-label={`${blockCommentsCount} comentarios`}
                              className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-odoo-purple text-white text-[10px] font-bold leading-none"
                            >
                              {blockCommentsCount > 99 ? '99+' : blockCommentsCount}
                            </span>
                          )}
                        </Button>
                      );
                    })()}
                </div>
              )}
              <article
                className="w-full bg-white dark:bg-ui-dark-card shadow-xl preview-content"
                style={{ minHeight: 'calc(100vh - 14rem)', padding: '56px 72px' }}
              >
                <Suspense
                  fallback={
                    <div className="p-4 flex justify-center">
                      <Spinner />
                    </div>
                  }
                >
                  <ContinuousDocumentEditor
                    blocks={sortedBlocks}
                    activeBlockKey={activeBlockKey}
                    documentTitle={detail?.title ?? (documentId ? 'Documento' : 'Nuevo documento')}
                    isDark={isDark}
                    canEdit={isDraft}
                    saveStatus={saveStatus}
                    blockSaveError={blockSaveError}
                    switching={isSaving}
                    onSelectBlock={(key) => onBlockClick(key)}
                    onContentChange={editor.onContentChange}
                    onFlush={editor.onFlush}
                    editorFlushRef={editor.editorFlushRef}
                    uploadFile={(file: File) =>
                      uploadMedia(
                        file,
                        activeBlock?.document_block_id
                          ? { type: 'block', id: activeBlock.document_block_id }
                          : undefined,
                      )
                    }
                    isBlockCompleted={(key) => {
                      // `key` puede ser document_block_id o template_block_id; convertimos a tpl id para indexar.
                      const b = sortedBlocks.find(
                        (x) => (x.document_block_id ?? x.template_block_id) === key,
                      );
                      return !!b && completedBlocks.isCompleted(b.template_block_id);
                    }}
                    onToggleCompleted={(key) => {
                      const b = sortedBlocks.find(
                        (x) => (x.document_block_id ?? x.template_block_id) === key,
                      );
                      if (b) completedBlocks.toggle(b.template_block_id);
                    }}
                    onOpenDescription={(block) => {
                      setDescriptionBlockKey((prev) =>
                        prev === block.template_block_id ? null : block.template_block_id,
                      );
                    }}
                    openDescriptionBlockKey={descriptionBlockKey}
                    getCommentCount={(block) =>
                      countUnreadCommentsForBlock(block.document_block_id, reviewComments)
                    }
                  />
                </Suspense>
              </article>
            </div>
            {/* Sidebar derecho — prioridad: descripción > comentarios. */}
            {(() => {
              const descriptionBlock = descriptionBlockKey
                ? sortedBlocks.find((b) => b.template_block_id === descriptionBlockKey)
                : null;
              const showDescriptionSidebar = !!descriptionBlock;
              const showCommentsSidebar =
                !showDescriptionSidebar &&
                comments.showPanel &&
                !!activeBlock &&
                !!activeBlock.document_block_id;
              if (!showDescriptionSidebar && !showCommentsSidebar) return null;
              return (
                <div
                  className="hidden lg:block flex-1 min-w-0 sticky top-4 self-start z-30"
                  style={{ minWidth: '320px', maxWidth: '420px', height: 'calc(100vh - 12rem)' }}
                >
                  <div className="h-full flex flex-col bg-white dark:bg-ui-dark-card shadow-xl rounded-xl overflow-hidden border border-ui-border dark:border-ui-dark-border">
                    {showDescriptionSidebar && descriptionBlock ? (
                      <>
                        <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between bg-ui-body/50 dark:bg-ui-dark-bg">
                          <div className="flex items-center gap-2 min-w-0">
                            <span className="text-xs font-black uppercase tracking-widest text-text-secondary">
                              Descripción · #{descriptionBlock.sort_order ?? '?'}
                            </span>
                            <span className="text-xs font-medium text-text-muted truncate">
                              {descriptionBlock.title || ''}
                            </span>
                          </div>
                          <button
                            type="button"
                            onClick={() => setDescriptionBlockKey(() => null)}
                            className="shrink-0 p-1 rounded text-text-muted hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors"
                            aria-label={t('wizard.closeDescription')}
                            title={t('wizard.closeDescription')}
                          >
                            <svg
                              width="14"
                              height="14"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              strokeWidth="2"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              aria-hidden="true"
                            >
                              <line x1="18" y1="6" x2="6" y2="18" />
                              <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                          </button>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                          {descriptionBlock.description != null &&
                          descriptionBlock.description !== '' ? (
                            <DocumentBlockDescriptionView
                              description={descriptionBlock.description}
                            />
                          ) : (
                            <p className="text-sm text-text-muted italic">
                              Este bloque no tiene descripción / instrucciones.
                            </p>
                          )}
                        </div>
                      </>
                    ) : (
                      activeBlock?.document_block_id && (
                        <BlockCommentsCard
                          mode="creator-edit"
                          blockSortOrder={activeBlock.sort_order ?? '?'}
                          blockComments={getCommentsForBlock(
                            activeBlock.document_block_id,
                            reviewComments,
                          )}
                          allComments={reviewComments}
                          onSendMessage={comments.onSend}
                          commentLoading={comments.loading}
                          submitError={comments.error}
                          onClose={() => comments.setShowPanel(false)}
                          canAddComments={comments.canAdd}
                          currentUserId={comments.currentUserId ?? undefined}
                          canDeleteAnyComment={comments.canDeleteAny}
                          onEditComment={comments.onEdit}
                          onDeleteComment={comments.onDelete}
                          onMarkAsRead={comments.onMarkAsRead}
                          onMarkAllBlockAsRead={comments.onMarkAllBlockAsRead}
                        />
                      )
                    )}
                  </div>
                </div>
              );
            })()}
          </div>
        </div>
      ) : (
        <div
          className={
            isEditorFullscreen
              ? 'flex-1 flex flex-col min-h-0'
              : 'flex-1 overflow-visible flex flex-col md:flex-row min-h-0'
          }
        >
          {/* Block tree — hidden when editor is in fullscreen */}
          {!isEditorFullscreen && (
            <div className="relative shrink-0 z-30 flex flex-col overflow-visible">
              <div
                className={[
                  'h-full flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card transition-all duration-300 overflow-hidden',
                  isSidebarCollapsed ? 'w-0' : 'md:w-64 lg:w-72',
                ].join(' ')}
              >
                <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0">
                  <span className="text-xs font-bold uppercase text-text-secondary tracking-widest truncate">
                    Bloques ({sortedBlocks.length})
                  </span>
                </div>
                <div className="flex-1 overflow-y-auto p-4 space-y-2">
                  {sortedBlocks.length === 0 ? (
                    <p className="text-xs text-text-muted">{t('common:noBlocks')}</p>
                  ) : (
                    sortedBlocks.map((b) => {
                      const key = b.document_block_id ?? b.template_block_id;
                      const selected = key === activeBlockKey;
                      const ui = blockToUiState(b);
                      const isEmptyEditable = isUnresolvedEditableBlock(b);
                      return (
                        <BlockListItem
                          key={key}
                          title={b.title || ''}
                          variant={selected ? 'selected' : 'default'}
                          locked={ui === 'locked'}
                          stateLabel={BLOCK_UI_STATE_CONFIG[ui].label}
                          hasUnreadComments={
                            countUnreadCommentsForBlock(b.document_block_id, reviewComments) > 0
                          }
                          isEmpty={isEmptyEditable}
                          isCompleted={completedBlocks.isCompleted(b.template_block_id)}
                          onClick={() => onBlockClick(key)}
                        />
                      );
                    })
                  )}
                </div>
              </div>

              <button
                type="button"
                onClick={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
                className={[
                  'absolute top-4 -right-3 z-50 w-6 h-6 rounded-full border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex items-center justify-center text-text-muted hover:text-odoo-purple transition-all shadow-sm',
                  isSidebarCollapsed ? 'rotate-180' : '',
                ].join(' ')}
                title={isSidebarCollapsed ? 'Expandir' : 'Colapsar'}
              >
                <svg
                  className="w-3.5 h-3.5 transition-transform duration-200"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  strokeWidth={2.5}
                  aria-hidden="true"
                >
                  <title>{isSidebarCollapsed ? 'Expandir' : 'Colapsar'}</title>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
            </div>
          )}
          <div className="flex-1 min-w-0 min-h-0 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-visible">
            {activeBlock && (
              <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
                {!isEditorFullscreen && (
                  <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
                    <div className="flex items-center gap-3 min-w-0">
                      <h3 className="text-sm font-bold truncate uppercase tracking-widest">
                        {activeBlock.title || 'Bloque'}
                      </h3>
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
                    <div className="flex items-center gap-2 shrink-0">
                      {(() => {
                        const tplKey = activeBlock.template_block_id;
                        const isDone = completedBlocks.isCompleted(tplKey);
                        return (
                          <Button
                            type="button"
                            size="xs"
                            variant="outline"
                            className={
                              isDone
                                ? 'text-success-dark border-success/60 bg-success/10 hover:bg-success/15'
                                : 'text-text-secondary border-ui-border hover:text-success-dark hover:border-success/60'
                            }
                            onClick={() => completedBlocks.toggle(tplKey)}
                            aria-pressed={isDone}
                            title={
                              isDone ? 'Marcar como pendiente' : 'Marcar bloque como finalizado'
                            }
                          >
                            <span className="inline-flex items-center gap-1">
                              <svg
                                width="11"
                                height="11"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="3"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                aria-hidden="true"
                              >
                                <polyline points="20 6 9 17 4 12" />
                              </svg>
                              {isDone ? 'Finalizado' : 'Finalizar'}
                            </span>
                          </Button>
                        );
                      })()}
                      {!comments.showPanel &&
                        activeBlock.document_block_id &&
                        (() => {
                          const blockCommentsCount = countUnreadCommentsForBlock(
                            activeBlock.document_block_id,
                            reviewComments,
                          );
                          return (
                            <Button
                              type="button"
                              size="xs"
                              variant="outline"
                              className="relative text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5"
                              onClick={() => comments.setShowPanel(true)}
                              title={t('templates:reviewComments')}
                            >
                              <span className="hidden sm:inline">{t('common:comments.label')}</span>
                              <span className="sm:hidden" aria-hidden>
                                💬
                              </span>
                              {blockCommentsCount > 0 && (
                                // biome-ignore lint/a11y/useAriaPropsSupportedByRole: count badge intentionally carries an accessible label; the rule's role inference is too strict for a numeric badge.
                                <span
                                  aria-label={`${blockCommentsCount} comentarios`}
                                  className="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-odoo-purple text-white text-[10px] font-bold leading-none"
                                >
                                  {blockCommentsCount > 99 ? '99+' : blockCommentsCount}
                                </span>
                              )}
                            </Button>
                          );
                        })()}
                      {canDeleteOptionalBlock && (
                        <Button
                          type="button"
                          size="xs"
                          variant="outline"
                          className="text-danger border-danger/40 hover:border-danger hover:bg-danger/5"
                          onClick={onShowDeleteBlock}
                        >
                          Eliminar
                        </Button>
                      )}
                    </div>
                  </div>
                )}

                {!isEditorFullscreen && (
                  <div className="flex border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card">
                    {(
                      [
                        { id: 'content', label: 'Contenido' },
                        { id: 'description', label: 'Descripción' },
                      ] as const
                    ).map((tab) => {
                      const isActive = blockViewTab === tab.id;
                      return (
                        <button
                          key={tab.id}
                          type="button"
                          onClick={() => setBlockViewTab(tab.id)}
                          className={`px-4 py-2 text-xs font-bold uppercase tracking-widest border-b-2 transition-colors ${
                            isActive
                              ? 'border-odoo-purple text-odoo-purple'
                              : 'border-transparent text-text-muted hover:text-text-primary'
                          }`}
                        >
                          {tab.label}
                        </button>
                      );
                    })}
                  </div>
                )}

                {blockSaveError && (
                  <p className="text-xs text-danger-dark dark:text-danger px-5 py-2 shrink-0 bg-white dark:bg-ui-dark-card">
                    {blockSaveError}
                  </p>
                )}
                <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                  {blockViewTab === 'content' ? (
                    activeBlock.block_type === 'cover' ? (
                      <CoverFillEditor
                        key={activeBlockKey ?? 'none'}
                        geometry={activeBlock.default_content}
                        value={activeBlock.content}
                        editable={canEditBlocks}
                        onPersist={async (fill) => {
                          await onPersistBlockContent(activeBlock.document_block_id, fill);
                        }}
                      />
                    ) : activeBlock.block_type === 'index' ? (
                      canEditBlocks &&
                      activeBlock.block_state !== 'locked' &&
                      activeBlock.document_block_id ? (
                        <IndexFillEditor
                          key={activeBlockKey ?? 'none'}
                          blocks={sortedBlocks.map((b) => ({
                            id: b.template_block_id,
                            title: b.title,
                            block_type: b.block_type,
                            content: b.content,
                            default_content: b.default_content,
                          }))}
                          currentBlockId={activeBlock.template_block_id}
                          value={activeBlock.content ?? activeBlock.default_content}
                          onPersist={async (next) => {
                            await onPersistBlockContent(activeBlock.document_block_id, next);
                          }}
                        />
                      ) : (
                        <div className="flex-1 overflow-y-auto p-6">
                          <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6">
                            <StructuralBlockPreview block={activeBlock} allBlocks={sortedBlocks} />
                          </div>
                        </div>
                      )
                    ) : canEditBlocks ? (
                      <ErrorBoundary
                        fallback={
                          <div className="p-4 text-danger">
                            Error al cargar el editor de contenido.
                          </div>
                        }
                      >
                        <div className="flex-1 min-h-0 p-6 flex flex-col">
                          <div className="relative flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                            {isSaving && (
                              <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-ui-dark-card/70">
                                <div className="flex items-center gap-2">
                                  <Spinner size="md" />
                                  <span>{t('common:savingChanges')}</span>
                                </div>
                              </div>
                            )}
                            <Suspense
                              fallback={
                                <div className="p-4 flex justify-center">
                                  <Spinner />
                                </div>
                              }
                              key={activeBlockKey ?? 'none'}
                            >
                              <BlockNoteEditorPanel
                                initialContent={normalizeBlockContentForEditor(
                                  activeBlock.content ?? activeBlock.default_content,
                                )}
                                editable
                                isDark={isDark}
                                onChange={editor.onContentChange}
                                onFlush={editor.onFlush}
                                editorFlushRef={editor.editorFlushRef}
                                onFullscreenChange={editor.onFullscreenChange}
                                uploadFile={(file: File) =>
                                  uploadMedia(
                                    file,
                                    activeBlock?.document_block_id
                                      ? { type: 'block', id: activeBlock.document_block_id }
                                      : undefined,
                                  )
                                }
                              />
                            </Suspense>
                          </div>
                        </div>
                      </ErrorBoundary>
                    ) : (
                      <div className="flex-1 overflow-y-auto p-6">
                        <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6">
                          {(() => {
                            if (isStructuralBlockType(activeBlock.block_type)) {
                              return (
                                <StructuralBlockPreview
                                  block={activeBlock}
                                  allBlocks={sortedBlocks}
                                />
                              );
                            }
                            const nodes = normalizeBlockContentForEditor(activeBlock.content);
                            const hasNodes = nodes.length > 0;
                            return hasNodes ? (
                              <BlockContentHtml content={nodes} />
                            ) : (
                              <p className="text-sm text-text-muted italic">
                                {t('common:noBlockContent')}
                              </p>
                            );
                          })()}
                        </div>
                      </div>
                    )
                  ) : (
                    <div className="flex-1 overflow-y-auto p-6">
                      <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6">
                        {activeBlock.description != null && activeBlock.description !== '' ? (
                          <DocumentBlockDescriptionView description={activeBlock.description} />
                        ) : (
                          <p className="text-sm text-text-muted italic">
                            Este bloque no tiene descripción/instrucciones.
                          </p>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Right: creator-edit comment panel for active block */}
          {comments.showPanel && activeBlock?.document_block_id && !isEditorFullscreen && (
            <div className="hidden md:flex md:w-[35%] shrink-0 border-l border-ui-border dark:border-ui-dark-border flex-col p-4 h-full min-h-0">
              <BlockCommentsCard
                mode="creator-edit"
                blockSortOrder={activeBlock.sort_order ?? '?'}
                blockComments={getCommentsForBlock(activeBlock.document_block_id, reviewComments)}
                allComments={reviewComments}
                onSendMessage={comments.onSend}
                commentLoading={comments.loading}
                submitError={comments.error}
                onClose={() => comments.setShowPanel(false)}
                canAddComments={comments.canAdd}
                currentUserId={comments.currentUserId ?? undefined}
                canDeleteAnyComment={comments.canDeleteAny}
                onEditComment={comments.onEdit}
                onDeleteComment={comments.onDelete}
                onMarkAsRead={comments.onMarkAsRead}
                onMarkAllBlockAsRead={comments.onMarkAllBlockAsRead}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
