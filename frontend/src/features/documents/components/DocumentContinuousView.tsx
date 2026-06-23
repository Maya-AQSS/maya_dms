import { Button, Spinner } from '@ceedcv-maya/shared-ui-react';
import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { uploadMedia } from '../../../api/media';
import { countUnreadCommentsForBlock, getCommentsForBlock } from '../../../utils/blockComments';
import { BlockCommentsCard } from '../../templates/components/BlockCommentsCard';
import { DocumentBlockDescriptionView } from './DocumentWizardSubviews';
import type { DocumentBlocksSharedProps } from './documentBlocksTypes';

const ContinuousDocumentEditor = lazy(() =>
  import('./ContinuousDocumentEditor').then((m) => ({ default: m.ContinuousDocumentEditor })),
);

interface DocumentContinuousViewProps
  extends Pick<
    DocumentBlocksSharedProps,
    | 'sortedBlocks'
    | 'activeBlock'
    | 'activeBlockKey'
    | 'documentId'
    | 'detail'
    | 'isDraft'
    | 'onBlockClick'
    | 'completedBlocks'
    | 'descriptionBlockKey'
    | 'setDescriptionBlockKey'
    | 'editor'
    | 'viewMode'
    | 'comments'
  > {}

/** Continuous (whole-document) edit view with the description/comments right rail. */
export function DocumentContinuousView({
  sortedBlocks,
  activeBlock,
  activeBlockKey,
  documentId,
  detail,
  isDraft,
  onBlockClick,
  completedBlocks,
  descriptionBlockKey,
  setDescriptionBlockKey,
  editor,
  viewMode,
  comments,
}: DocumentContinuousViewProps) {
  const { t } = useTranslation(['documents', 'common', 'templates']);
  const { reviewComments } = comments;
  const { saveStatus, blockSaveError, isSaving, isDark } = editor;

  return (
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
                        <DocumentBlockDescriptionView description={descriptionBlock.description} />
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
  );
}
