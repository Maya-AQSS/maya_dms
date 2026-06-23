import { buildMayaEditorExtensions, htmlToTiptapDoc } from '@ceedcv-maya/shared-editor-react';
import { useDarkMode } from '@ceedcv-maya/shared-layout-react';
import { Button, ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import {
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import React, { useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  canCommentOnDocument,
  canCreateBlockComment,
  canDeleteBlockComment,
} from '../../../permissions';
import type { BlockType } from '../../../types/blocks';
import { countUnreadCommentsForBlock, getCommentsForBlock } from '../../../utils/blockComments';
import { useCompletedBlocks } from '../../documents/hooks/useCompletedBlocks';
import { usePublishedThemes } from '../../themes/hooks/usePublishedThemes';
import { useUserProfile } from '../../user-profile';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { useTemplateCommentsQuery } from '../hooks/useTemplateComments';
import { validateBlockName } from '../lib/blockValidation';
import { type BlockComment, BlockCommentsCard } from './BlockCommentsCard';
import { DocxBlockSplitter } from './DocxBlockSplitter';
import { useWizardBlockComments } from './useWizardBlockComments';
import { useWizardBlockForm } from './useWizardBlockForm';
import type {
  PanelMode,
  TabId,
  WizardStep2BlocksHandle,
  WizardStep2BlocksProps,
} from './WizardStep2Blocks.types';
import { WizardStep2EditorTabs } from './WizardStep2EditorTabs';
import { WizardStep2PropertiesPanel } from './WizardStep2PropertiesPanel';
import { WizardStep2Sidebar } from './WizardStep2Sidebar';

// ── Main Component ───────────────────────────────────────────────────────────

export type { WizardStep2BlocksHandle } from './WizardStep2Blocks.types';

export const WizardStep2Blocks = React.forwardRef<WizardStep2BlocksHandle, WizardStep2BlocksProps>(
  (
    {
      template,
      isDark = false,
      onBlocksCountChange,
      onBlocksLoadingChange,
      onBlocksChange,
      onContinue,
      onInvalidBlocksChange,
    },
    ref,
  ) => {
    const { t } = useTranslation(['documents', 'common']);
    const commentsQuery = useTemplateCommentsQuery(template.id);
    const reviewComments = commentsQuery.data?.data ?? [];
    const { profile, hasPermission } = useUserProfile();
    const mayComment = canCreateBlockComment(hasPermission);

    const { blocks, loading, createBlock, updateBlock, deleteBlock, reorderBlocks } =
      useTemplateBlocks(template.id, {
        created_by: template.created_by,
        status: template.status,
      });

    // Visual-only "finalizado" marker, persisted in localStorage and namespaced
    // by template id so it survives reloads and doesn't bleed across templates.
    // Mirrors the documents wizard UX — does not affect server-side validations.
    const completedBlocks = useCompletedBlocks(`tpl-${template.id}`);

    const { isDark: globalIsDark } = useDarkMode();
    const effectiveIsDark = isDark || globalIsDark;

    useEffect(() => {
      onBlocksLoadingChange?.(loading);
    }, [loading, onBlocksLoadingChange]);

    useEffect(() => {
      if (!loading) {
        onBlocksCountChange?.(blocks.length);
        onBlocksChange?.(blocks);
      }
    }, [blocks, loading, onBlocksCountChange, onBlocksChange]);

    const hasInvalidBlocks = !loading && blocks.some((b) => !b.title?.trim());

    useEffect(() => {
      onInvalidBlocksChange?.(hasInvalidBlocks);
    }, [hasInvalidBlocks, onInvalidBlocksChange]);

    useEffect(() => {
      const handler = (e: BeforeUnloadEvent) => {
        if (hasInvalidBlocks) e.preventDefault();
      };
      window.addEventListener('beforeunload', handler);
      return () => window.removeEventListener('beforeunload', handler);
    }, [hasInvalidBlocks]);

    const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
    const [isEditorFullscreen, setIsEditorFullscreen] = useState(false);

    const handleEditorFullscreenChange = useCallback((v: boolean) => {
      setIsEditorFullscreen(v);
      document.documentElement.classList.toggle('editor-fullscreen', v);
    }, []);

    useEffect(() => {
      return () => document.documentElement.classList.remove('editor-fullscreen');
    }, []);

    const sensors = useSensors(
      useSensor(PointerSensor),
      useSensor(KeyboardSensor, {
        coordinateGetter: sortableKeyboardCoordinates,
      }),
    );

    const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
    const [panelMode, setPanelMode] = useState<PanelMode>('empty');
    const [activeSingleId, setActiveSingleId] = useState<string | null>(null);
    const [showCommentPanel, setShowCommentPanel] = useState(false);

    // const [multiIndex, setMultiIndex] = useState(0);
    const clickTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [busy, setBusy] = useState(false);
    const [deleteModal, setDeleteModal] = useState(false);
    const [docxSplitterOpen, setDocxSplitterOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<TabId>('properties');
    const [isSaving, setIsSaving] = useState(false);
    const { data: publishedThemes = [] } = usePublishedThemes();

    const form = useWizardBlockForm({
      template,
      publishedThemes,
      activeSingleId,
      panelMode,
      updateBlock,
    });
    const { formName, setNameError, tabIsDirty, loadFormFromBlock, saveStatus, saveCurrentTab } =
      form;

    useEffect(() => {
      if (activeSingleId && !formName.trim()) {
        setShowCommentPanel(false);
      }
    }, [activeSingleId, formName]);

    const selectedBlock = activeSingleId
      ? (blocks.find((b) => b.id === activeSingleId) ?? null)
      : null;
    const selectedBlockIndex = selectedBlock
      ? blocks.findIndex((b) => b.id === selectedBlock.id)
      : -1;

    const blockComments: BlockComment[] = activeSingleId
      ? getCommentsForBlock(activeSingleId, reviewComments)
      : [];

    const {
      commentSubmitLoading,
      commentSubmitError,
      commentsById,
      handleSendMessage,
      handleCreateAnchoredComment,
      handleEditComment,
      handleDeleteComment,
      handleMarkCommentAsRead,
      handleMarkAllBlockCommentsAsRead,
    } = useWizardBlockComments({
      templateId: template.id,
      activeSingleId,
      reviewComments,
      profileName: profile?.name,
    });

    useEffect(() => {
      if (activeTab === 'comments') setActiveTab('properties');
    }, [activeTab]);

    const handleBlockClick = (blockId: string) => {
      if (clickTimerRef.current) clearTimeout(clickTimerRef.current);
      clickTimerRef.current = setTimeout(async () => {
        // Abort navigation if the current block has an invalid name.
        if (activeSingleId && blockId !== activeSingleId) {
          const nameErr = validateBlockName(formName);
          if (nameErr) {
            setNameError(nameErr);
            setActiveTab('properties');
            return;
          }
        }
        if (tabIsDirty && activeSingleId) {
          setIsSaving(true);

          try {
            const success = await saveCurrentTab();

            if (!success) return;
          } finally {
            setIsSaving(false);
          }
        }
        const block = blocks.find((b) => b.id === blockId);
        if (!block) return;
        setSelectedBlockIds([blockId]);
        setActiveSingleId(blockId);
        setPanelMode('edit');
        // Comment panel stays in whatever state the user left it; the badge
        // on the toggle button signals new activity without grabbing space.
        loadFormFromBlock(block);
        setActiveTab('properties');
      }, 200);
    };

    const handleToggleSelectAll = () => {
      if (selectedBlockIds.length === blocks.length && blocks.length > 0) {
        setSelectedBlockIds([]);
        setActiveSingleId(null);
        setPanelMode('empty');
      } else {
        setSelectedBlockIds(blocks.map((b) => b.id));
        // setMultiIndex(0);
        setPanelMode('multi');
        if (blocks[0]) {
          setActiveSingleId(blocks[0].id);
          loadFormFromBlock(blocks[0]);
        }
      }
    };

    const handleDragEnd = (event: DragEndEvent) => {
      const { active, over } = event;
      if (over && active.id !== over.id) {
        const newIndex = blocks.findIndex((i) => i.id === over.id);
        void reorderBlocks(active.id as string, newIndex);
      }
    };

    useImperativeHandle(ref, () => ({
      saveIfPending: async () => {
        if (tabIsDirty) {
          setIsSaving(true);
          try {
            const success = await saveCurrentTab();
            if (!success) return;
          } catch {
            return;
          } finally {
            setIsSaving(false);
          }
        }
      },
      discardInvalidBlocks: async () => {
        const invalidIds = blocks.filter((b) => !b.title?.trim()).map((b) => b.id);
        for (const id of invalidIds) await deleteBlock(id);
        if (activeSingleId && invalidIds.includes(activeSingleId)) {
          setActiveSingleId(null);
          setSelectedBlockIds([]);
          setPanelMode('empty');
        }
        return blocks.filter((b) => !!b.title?.trim());
      },
    }));

    const handleAddBlock = async (
      block?: Partial<{ name: string; description: string; content: any; block_type: BlockType }>,
    ) => {
      // Block creation if the current block still has an invalid name.
      if (activeSingleId) {
        const nameErr = validateBlockName(formName);
        if (nameErr) {
          setNameError(nameErr);
          setActiveTab('properties');
          return;
        }
        if (tabIsDirty) {
          setIsSaving(true);

          try {
            const success = await saveCurrentTab();

            if (!success) return;
          } finally {
            setIsSaving(false);
          }
        }
      }
      setBusy(true);
      try {
        const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG['editable'].payload;
        const newBlock = await createBlock({
          title: block?.name ?? null,
          description: block?.description ?? null,
          type: 'paragraph',
          block_type: block?.block_type ?? 'content',
          block_state,
          mandatory,
          default_content: block?.content ?? null,
        });
        setSelectedBlockIds([newBlock.id]);
        setActiveSingleId(newBlock.id);
        setPanelMode('edit');
        loadFormFromBlock(newBlock);
        setActiveTab('properties');
        setShowCommentPanel(false);
      } finally {
        setBusy(false);
      }
    };

    const handleImportDocx = async (
      imported: Array<{ name: string; html: string }>,
    ): Promise<{ createdCount: number }> => {
      const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG['editable'].payload;
      let firstBlock: Awaited<ReturnType<typeof createBlock>> | null = null;
      let createdCount = 0;
      for (const { name, html } of imported) {
        try {
          const doc = htmlToTiptapDoc(html, buildMayaEditorExtensions('full'));
          const created = await createBlock({
            title: name,
            type: 'paragraph',
            block_state,
            mandatory,
            default_content: doc.content,
          });
          if (!firstBlock) firstBlock = created;
          createdCount++;
        } catch (e) {
          // Para en el primer fallo: el modal deja el resto para reintentar.
          console.error('[WizardStep2Blocks] createBlock failed during import', e);
          break;
        }
      }
      if (firstBlock) {
        setSelectedBlockIds([firstBlock.id]);
        setActiveSingleId(firstBlock.id);
        setPanelMode('edit');
        loadFormFromBlock(firstBlock);
        setActiveTab('properties');
        setShowCommentPanel(false);
      }
      if (createdCount >= imported.length) setDocxSplitterOpen(false);
      return { createdCount };
    };

    const handleDelete = async () => {
      setBusy(true);
      try {
        if (panelMode === 'multi' && selectedBlockIds.length > 0) {
          // Delete all selected blocks
          for (const id of selectedBlockIds) {
            await deleteBlock(id);
          }
          setSelectedBlockIds([]);
          setActiveSingleId(null);
          // setMultiIndex(0);
        } else if (activeSingleId) {
          await deleteBlock(activeSingleId);
          setActiveSingleId(null);
          setSelectedBlockIds([]);
        }
        setPanelMode('empty');
        setDeleteModal(false);
      } finally {
        setBusy(false);
      }
    };

    const handleDuplicate = async () => {
      setBusy(true);
      try {
        const ids =
          panelMode === 'multi' ? selectedBlockIds : activeSingleId ? [activeSingleId] : [];
        for (const id of ids) {
          const source = blocks.find((b) => b.id === id);
          if (!source) continue;
          const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[blockToUiState(source)].payload;
          await createBlock({
            title: `${source.title ?? 'Bloque'} (copia)`,
            type: source.type,
            block_state,
            mandatory,
            default_content: source.default_content,
            description: source.description,
          });
        }
      } finally {
        setBusy(false);
      }
    };

    const handleCancel = async () => {
      if (tabIsDirty && activeSingleId) {
        const original = blocks.find((b) => b.id === activeSingleId);
        if (original) loadFormFromBlock(original);
      }
      setPanelMode('empty');
      setActiveSingleId(null);
      setSelectedBlockIds([]);
      // setMultiIndex(0);
    };

    const renderSaveStatus = () => {
      if (saveStatus === 'saving')
        return <span className="text-xs text-text-muted italic">{t('common:saving')}</span>;
      if (saveStatus === 'saved')
        return (
          <span className="text-xs text-success-dark flex items-center gap-1">
            ✓ {t('common:status.saved')}
          </span>
        );
      if (saveStatus === 'error')
        return <span className="text-xs text-danger-dark">{t('common:errors.saveFailed')}</span>;
      return null;
    };

    const handleSaveAndContinue = async () => {
      setIsSaving(true);
      try {
        const success = await saveCurrentTab();
        if (!success) return;
      } finally {
        setIsSaving(false);
        onContinue?.();
      }
    };

    return (
      <div
        className={[
          isEditorFullscreen
            ? 'fixed inset-0 z-[100] bg-white dark:bg-ui-dark-card flex flex-col'
            : 'flex-1 min-h-0 flex flex-col md:flex-row relative overflow-visible',
          'transition-all duration-300',
        ].join(' ')}
      >
        {!isEditorFullscreen && (
          <WizardStep2Sidebar
            isSidebarCollapsed={isSidebarCollapsed}
            onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
            blocks={blocks}
            loading={loading}
            selectedBlockIds={selectedBlockIds}
            activeSingleId={activeSingleId}
            reviewComments={reviewComments}
            completedBlocks={completedBlocks}
            sensors={sensors}
            busy={busy}
            templateId={template.id}
            hasPermission={hasPermission}
            onToggleSelectAll={handleToggleSelectAll}
            onDragEnd={handleDragEnd}
            onBlockClick={handleBlockClick}
            onAddBlock={(block) => void handleAddBlock(block)}
            onSetDocxSplitter={setDocxSplitterOpen}
          />
        )}

        {/* Main Panel */}

        {/* Main Panel */}
        <div className="flex-1 min-w-0 min-h-0 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-visible">
          {panelMode === 'empty' && (
            <div className="flex-1 flex flex-col items-center justify-center p-6 text-center opacity-40">
              <p className="text-sm font-bold uppercase tracking-widest">
                {t('templates:wizard.selectBlock')}
              </p>
            </div>
          )}

          {(panelMode === 'edit' || panelMode === 'multi') && selectedBlock && (
            <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
              {/* Compact fullscreen header — replaces regular header + tabs when fullscreen */}
              {isEditorFullscreen && (
                <div className="shrink-0 h-11 px-4 flex items-center gap-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
                  <h3 className="flex-1 text-sm font-bold truncate uppercase tracking-widest">
                    Bloque {blocks.indexOf(selectedBlock) + 1}: {selectedBlock.title}
                  </h3>
                  {renderSaveStatus()}
                  {onContinue && (
                    <Button
                      variant="primary"
                      size="xs"
                      onClick={() => {
                        setIsEditorFullscreen(false);
                        void handleSaveAndContinue();
                      }}
                      className="shrink-0"
                    >
                      Guardar y continuar →
                    </Button>
                  )}
                </div>
              )}

              {/* Regular header — hidden in fullscreen.
                Responsive: row on >=sm, stacked column on mobile. Action
                buttons wrap (flex-wrap) so they drop to a second row when
                the title is long. Destructive/secondary actions collapse
                to icon-only on small screens to keep the row compact. */}
              {!isEditorFullscreen && (
                <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shrink-0 bg-white dark:bg-ui-dark-card">
                  <div className="flex items-center gap-3 min-w-0">
                    <h3 className="text-sm font-bold truncate uppercase tracking-widest">
                      Bloque {blocks.indexOf(selectedBlock) + 1}: {selectedBlock.title}
                    </h3>
                    {renderSaveStatus()}
                  </div>
                  <div className="flex items-center flex-wrap gap-2 sm:shrink-0">
                    {!showCommentPanel &&
                      selectedBlock?.title &&
                      (() => {
                        const blockCommentsCount = countUnreadCommentsForBlock(
                          activeSingleId,
                          reviewComments,
                        );
                        return (
                          <Button
                            variant="outline"
                            size="xs"
                            onClick={() => setShowCommentPanel(true)}
                            className="relative text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5"
                            title={t('templates:reviewComments')}
                          >
                            <span className="hidden sm:inline">{t('common:comments.label')}</span>
                            <span className="sm:hidden" aria-hidden>
                              💬
                            </span>
                            {blockCommentsCount > 0 && (
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
                    {activeSingleId &&
                      (() => {
                        const isDone = completedBlocks.isCompleted(activeSingleId);
                        return (
                          <Button
                            type="button"
                            variant="outline"
                            size="xs"
                            className={
                              isDone
                                ? 'text-success-dark border-success/60 bg-success/10 hover:bg-success/15'
                                : 'text-text-secondary border-ui-border hover:text-success-dark hover:border-success/60'
                            }
                            onClick={() => completedBlocks.toggle(activeSingleId)}
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
                              <span className="hidden sm:inline">
                                {isDone ? 'Finalizado' : 'Finalizar'}
                              </span>
                            </span>
                          </Button>
                        );
                      })()}
                    <Button
                      variant="outline"
                      size="xs"
                      onClick={handleDuplicate}
                      disabled={busy}
                      title="Duplicar"
                    >
                      <span className="hidden sm:inline">Duplicar</span>
                      <span className="sm:hidden" aria-hidden>
                        ⎘
                      </span>
                    </Button>
                    <Button
                      variant="outline"
                      size="xs"
                      className="text-danger hover:bg-danger/5 hover:border-danger/40"
                      onClick={() => setDeleteModal(true)}
                      title={t('common:actions.delete')}
                    >
                      <span className="hidden sm:inline">{t('common:actions.delete')}</span>
                      <span className="sm:hidden" aria-hidden>
                        🗑
                      </span>
                    </Button>
                  </div>
                </div>
              )}

              {/* Tabs — hidden in fullscreen. "Cancelar" lives at the right
                edge of the tab strip so the header row above has more
                breathing room on narrow viewports. */}
              {!isEditorFullscreen && (
                <div className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card">
                  {(['properties', 'content', 'description'] as TabId[]).map((tab) => {
                    const isTabDisabled =
                      (tab === 'content' || tab === 'description') &&
                      validateBlockName(formName) !== '';

                    return (
                      <button
                        key={tab}
                        onClick={() => {
                          if (!isTabDisabled) {
                            setActiveTab(tab);
                          }
                        }}
                        disabled={isTabDisabled}
                        title={
                          isTabDisabled
                            ? validateBlockName(formName) ||
                              'Asigna un nombre válido al bloque para habilitar esta pestaña'
                            : ''
                        }
                        className={`px-4 py-2 text-xs font-bold uppercase tracking-widest border-b-2 transition-colors flex items-center gap-1.5 ${
                          activeTab === tab
                            ? 'border-odoo-purple text-odoo-purple'
                            : 'border-transparent text-text-muted hover:text-text-primary'
                        } ${isTabDisabled ? 'opacity-30 cursor-not-allowed' : ''}`}
                      >
                        {tab === 'properties'
                          ? 'Propiedades'
                          : tab === 'content'
                            ? 'Contenido'
                            : 'Descripción'}
                      </button>
                    );
                  })}
                  <div className="ml-auto flex items-center pr-3">
                    <Button
                      variant="ghost"
                      size="xs"
                      className="hover:text-text-primary"
                      onClick={() => void handleCancel()}
                    >
                      {t('common:actions.cancel')}
                    </Button>
                  </div>
                </div>
              )}

              <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                {activeTab === 'properties' && !isEditorFullscreen && (
                  <WizardStep2PropertiesPanel form={form} publishedThemes={publishedThemes} />
                )}
                <WizardStep2EditorTabs
                  form={form}
                  activeTab={activeTab}
                  template={template}
                  blocks={blocks}
                  activeSingleId={activeSingleId}
                  isSaving={isSaving}
                  effectiveIsDark={effectiveIsDark}
                  onEditorFullscreenChange={handleEditorFullscreenChange}
                  onCreateComment={handleCreateAnchoredComment}
                  commentsById={commentsById}
                />
              </div>
            </div>
          )}
        </div>

        {/* Right: comment panel — creator-edit mode, available even if no comments */}
        {showCommentPanel &&
          !isEditorFullscreen &&
          panelMode === 'edit' &&
          selectedBlock &&
          formName.trim() && (
            <div className="hidden md:flex md:w-[35%] shrink-0 border-l border-ui-border dark:border-ui-dark-border flex-col p-4 h-full min-h-0">
              <BlockCommentsCard
                mode="creator-edit"
                blockSortOrder={selectedBlockIndex >= 0 ? selectedBlockIndex + 1 : '?'}
                blockComments={blockComments}
                allComments={reviewComments}
                onSendMessage={handleSendMessage}
                commentLoading={commentSubmitLoading}
                submitError={commentSubmitError}
                onClose={() => setShowCommentPanel(false)}
                canAddComments={canCommentOnDocument(template.status) && mayComment}
                currentUserId={profile?.id}
                canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                onEditComment={handleEditComment}
                onDeleteComment={handleDeleteComment}
                onMarkAsRead={handleMarkCommentAsRead}
                onMarkAllBlockAsRead={handleMarkAllBlockCommentsAsRead}
              />
            </div>
          )}

        <ConfirmDialog
          open={deleteModal}
          title={t('common:confirm.deleteBlock')}
          description={
            panelMode === 'multi'
              ? t('templates:wizard.deleteBlocksConfirm', { count: selectedBlockIds.length })
              : t('common:confirm.actionIrreversible')
          }
          confirmLabel={t('common:actions.delete')}
          variant="danger"
          onConfirm={handleDelete}
          onCancel={() => setDeleteModal(false)}
          loading={busy}
        />

        <DocxBlockSplitter
          open={docxSplitterOpen}
          onCancel={() => setDocxSplitterOpen(false)}
          onConfirm={handleImportDocx}
          isDark={effectiveIsDark}
        />
      </div>
    );
  },
);

WizardStep2Blocks.displayName = 'WizardStep2Blocks';
