import { useState, useRef, useMemo, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
import { useQueryClient } from '@tanstack/react-query';
import type { Template, ReviewCycleSnapshot, ReviewCycleBlock } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import {
  diffTiptapContentLines,
  type TiptapDiffLine,
} from '../../documents/lib/tiptapLineDiff';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { StructuralBlockPreview, isStructuralBlockType } from '../../documents/components/StructuralBlockPreview';
import { SequentialValidatorBadge } from '../../documents/components/SequentialValidatorBadge';
import { SubmissionChangelogReadonly } from '../../../components/VersionChangelogModal';
import { Button, ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import { approveTemplateReview, rejectTemplateReview } from '../../../api/templates';
import { refreshDmsDashboardQuery } from '../../dashboard/hooks/useDmsDashboard';
import { canCommentOnDocument, canCreateBlockComment, canDeleteBlockComment, DMS_PERMISSIONS } from '../../../permissions';
import { apiFetchJson, ApiHttpError } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { useTemplateCommentsQuery } from '../hooks/useTemplateComments';
import { BlockCommentsCard, ViewCardHeader } from './BlockCommentsCard';
import type { BlockComment, CommentMode } from './BlockCommentsCard';
import { getCommentsForBlock, countUnreadCommentsForBlock, resolveCommentBlockableId } from '../../../utils/blockComments';
import { appendCommentToTemplateCache, patchTemplateCommentCache, markCommentAsReadInTemplateCache, markCommentDeletedInTemplateCache, markBlockCommentsAsReadInTemplateCache } from '../../comments/commentCache';

type Props = { template: Template };

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

type DiffLine = TiptapDiffLine;

// ─── Sub-components ──────────────────────────────────────────────────────────

function InfoBlockDescription({ description }: { description: unknown }) {
  if (!description) return null;
  const nodes = normalizeBlockContentForEditor(description);
  if (nodes.length > 0) return <BlockContentHtml content={nodes} />;
  if (typeof description === 'string' && description.trim()) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
        {description}
      </p>
    );
  }
  return null;
}

type HistoryTab = 'content' | 'description';

type CycleEntry = {
  cycle: number;
  submitted_at: string;
  block: ReviewCycleBlock;
  diffContent: DiffLine[];
  diffDescription: DiffLine[];
};

type HistoryPanelProps = {
  blockId: string;
  blockNumber: number | string;
  history: ReviewCycleSnapshot[];
  hasDescription: boolean;
  onClose: () => void;
};

function DiffLines({ lines }: { lines: DiffLine[] }) {
  const { t } = useTranslation('templates');
  if (lines.length === 0) {
    return (
      <p className="px-2 py-1 text-text-muted italic text-2xs">
        {t('history.noChangesInSubmission')}
      </p>
    );
  }
  return (
    <>
      {lines.map((line, li) => (
        <div
          key={li}
          className={`px-2 py-0.5 whitespace-pre-wrap break-all leading-relaxed ${
            line.type === 'removed'
              ? 'bg-danger/10 text-danger-dark dark:bg-danger/15 dark:text-danger'
              : 'bg-success/10 text-success-dark dark:bg-success/15 dark:text-success'
          }`}
        >
          <span className="mr-2 select-none font-bold opacity-70">
            {line.type === 'removed' ? '−' : '+'}
          </span>
          {line.text}
        </div>
      ))}
    </>
  );
}

function TemplateBlockHistoryPanel({ blockId, blockNumber, history, hasDescription, onClose }: HistoryPanelProps) {
  const { t } = useTranslation('templates');
  const [ascending, setAscending] = useState(false);
  const [tab, setTab] = useState<HistoryTab>('content');

  useEffect(() => {
    if (!hasDescription && tab === 'description') setTab('content');
  }, [hasDescription, tab]);

  const entriesChron = useMemo((): CycleEntry[] => {
    return history
      .map((cycle, i) => {
        const block = cycle.blocks.find((b) => b.id === blockId) ?? null;
        if (!block) return null;
        const prevBlock = i > 0 ? (history[i - 1].blocks.find((b) => b.id === blockId) ?? null) : null;

        const mkDiff = (getCurrent: (b: ReviewCycleBlock) => unknown, getPrev: (b: ReviewCycleBlock) => unknown) =>
          diffTiptapContentLines(
            prevBlock ? getPrev(prevBlock) : null,
            getCurrent(block),
          );

        const diffContent = mkDiff((b) => b.default_content, (b) => b.default_content);
        const diffDescription = mkDiff((b) => b.description, (b) => b.description);

        if (diffContent.length === 0 && diffDescription.length === 0) return null;
        return { cycle: cycle.cycle, submitted_at: cycle.submitted_at, block, diffContent, diffDescription };
      })
      .filter((e): e is CycleEntry => e !== null);
  }, [history, blockId]);

  const entries = useMemo(() => {
    const sorted = ascending ? entriesChron : [...entriesChron].reverse();
    return sorted.filter((e: CycleEntry) => (tab === 'content' ? e.diffContent.length > 0 : e.diffDescription.length > 0));
  }, [entriesChron, ascending, tab]);

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-4 py-3 gap-2">
        <span className="text-2xs font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary flex-1">
          {t('history.panelHeader', { n: blockNumber })}
        </span>
        <button
          type="button"
          onClick={() => setAscending((v) => !v)}
          className="flex items-center gap-1 text-2xs font-black uppercase tracking-widest text-text-muted hover:text-odoo-teal transition-colors cursor-pointer"
          title={ascending ? t('history.sortAscendingTitle') : t('history.sortDescendingTitle')}
        >
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
            {ascending
              ? <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
              : <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
            }
          </svg>
        </button>
        <button
          type="button"
          onClick={onClose}
          aria-label={t('review.closePanelAria')}
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Tabs */}
      <div className="shrink-0 flex border-b border-ui-border dark:border-ui-dark-border">
        {(['content', 'description'] as HistoryTab[]).map((tabKey) => {
          const isDisabled = tabKey === 'description' && !hasDescription;
          return (
            <button
              key={tabKey}
              type="button"
              disabled={isDisabled}
              onClick={() => !isDisabled && setTab(tabKey)}
              title={isDisabled ? t('history.noDescription') : undefined}
              className={[
                'flex-1 py-2 text-2xs font-black uppercase tracking-widest transition-colors',
                isDisabled
                  ? 'opacity-40 cursor-not-allowed text-text-muted dark:text-text-dark-muted'
                  : tab === tabKey
                    ? 'text-odoo-purple border-b-2 border-odoo-purple -mb-px bg-odoo-purple/5 cursor-pointer'
                    : 'text-text-muted hover:text-text-primary dark:hover:text-text-dark-primary cursor-pointer',
              ].join(' ')}
            >
              {tabKey === 'content' ? t('history.tabContent') : t('history.tabDescription')}
            </button>
          );
        })}
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto divide-y divide-ui-border dark:divide-ui-dark-border">
        {entries.length === 0 ? (
          <p className="py-8 text-center text-xs text-text-muted dark:text-text-dark-muted italic">
            {t('history.noChanges')}
          </p>
        ) : (
          entries.map((entry) => (
            <div key={entry.cycle} className="px-4 py-3">
              <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                  {t('history.revision', { n: entry.cycle })}
                </p>
                <span className="text-2xs text-text-muted dark:text-text-dark-muted tabular-nums">
                  {new Date(entry.submitted_at).toLocaleString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
              <div className="rounded overflow-hidden border border-ui-border dark:border-ui-dark-border text-2xs font-mono">
                <DiffLines lines={tab === 'content' ? entry.diffContent : entry.diffDescription} />
              </div>
            </div>
          ))
        )}
      </div>

      {/* Footer legend */}
      {entries.length > 0 && (
        <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border px-4 py-2 flex items-center gap-3 text-2xs text-text-muted dark:text-text-dark-muted bg-ui-body/30 dark:bg-ui-dark-bg/30">
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-danger/25 border border-danger/40" />
            {t('history.legendRemoved')}
          </span>
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-success/25 border border-success/40" />
            {t('history.legendAdded')}
          </span>
          <span className="ml-auto font-semibold">
            {t('history.revisions', { count: entries.length })}
          </span>
        </div>
      )}
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export function TemplateReviewView({ template }: Props) {
  const { t } = useTranslation('templates');
  const { goBack } = useBackNavigation({
    fallback: template.process_id ? `/processes/${template.process_id}` : '/processes',
  });
  const { profile, hasPermission } = useUserProfile();
  const { blocks } = useTemplateBlocks(template.id, {
    created_by: template.created_by,
    status: template.status,
  });
  const queryClient = useQueryClient();

  const [activeView, setActiveView] = useState<ActiveView | null>(null);
  const [diffBlockId, setDiffBlockId] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [commentLoading, setCommentLoading] = useState(false);
  const [commentSubmitError, setCommentSubmitError] = useState<string | null>(null);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);

  const blockRefs = useRef<Map<string, HTMLElement>>(new Map());

  const myReview = template.reviewers?.find(r => String(r.user_id) === String(profile?.id));
  const isReviewer = !!myReview;
  const isCreator = !!profile?.id && template.created_by === profile.id;

  const previousStagesPending =
    template.review_mode === 'sequential' &&
    myReview != null &&
    (template.reviewers ?? []).some(
      (r) => r.stage < myReview.stage && r.status !== 'approved',
    );

  const canPerformReview = hasPermission(DMS_PERMISSIONS.templateReview);

  const isActiveValidator =
    isReviewer &&
    canPerformReview &&
    template.status === 'in_review' &&
    myReview?.status === 'pending' &&
    !previousStagesPending;

  const commentMode: CommentMode = (() => {
    if (isReviewer && template.status === 'in_review') return 'validator';
    if (isCreator) return 'creator-edit';
    return 'creator-readonly';
  })();

  const commentsQuery = useTemplateCommentsQuery(template.id);
  const comments = commentsQuery.data?.data ?? [];

  const hasHistory = Array.isArray(template.review_history) && template.review_history.length > 0;

  const processesQuery = useProcessesQuery(undefined, { enabled: !!template.process_id });
  const processLabel = useMemo<string | null>(() => {
    if (!template.process_id) return null;
    const process = processesQuery.data?.data.find((p) => p.id === template.process_id) ?? null;
    return process ? `Proceso: ${process.code} — ${process.name}` : null;
  }, [template.process_id, processesQuery.data]);

  const handleSendMessage = async (parentId: string | null, body: string) => {
    setCommentSubmitError(null);
    setCommentLoading(true);
    try {
      const blockableId = resolveCommentBlockableId(
        parentId,
        comments,
        activeView?.blockId ?? null,
      );
      const res = await apiFetchJson<{ data: BlockComment }>(`templates/${template.id}/comments`, {
        method: 'POST',
        body: {
          body,
          parent_id: parentId,
          blockable_id: blockableId,
        },
      });
      appendCommentToTemplateCache(queryClient, template.id, res.data);
    } catch {
      setCommentSubmitError('No se pudo guardar el comentario.');
      throw new Error('comment-send-failed');
    } finally {
      setCommentLoading(false);
    }
  };

  const handleEditComment = async (commentId: string, newBody: string) => {
    const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}`, {
      method: 'PATCH',
      body: { body: newBody },
    });
    patchTemplateCommentCache(queryClient, template.id, (comments) =>
      comments.map(c => c.id === commentId ? res.data : c));
  };

  const handleDeleteComment = async (commentId: string) => {
    await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
    markCommentDeletedInTemplateCache(queryClient, template.id, commentId, profile?.name);
  };

  const handleMarkCommentAsRead = async (commentId: string) => {
    await markCommentAsReadInTemplateCache(queryClient, template.id, commentId);
  };

  const handleMarkAllBlockCommentsAsRead = async () => {
    if (!activeView?.blockId) return;
    await markBlockCommentsAsReadInTemplateCache(queryClient, template.id, activeView.blockId);
  };

  const openView = (blockId: string, mode: 'comments' | 'info') => {
    setDiffBlockId(null);
    setActiveView({ blockId, mode });
  };

  const closeView = () => setActiveView(null);

  const handleApprove = async () => {
    setActionLoading(true);
    setError(null);
    try {
      await approveTemplateReview(template.id);
      await queryClient.invalidateQueries({ queryKey: ['templates'] });
      await refreshDmsDashboardQuery(queryClient);
      goBack({ replace: true });
    } catch (e) {
      setError(e instanceof ApiHttpError ? e.message : e instanceof Error ? e.message : 'Error al aprobar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRejectClick = () => {
    const myComments = comments.filter(
      c => String(c.author_id) === String(profile?.id),
    );
    if (myComments.length === 0) setShowNoCommentsWarning(true);
    else setShowRejectModal(true);
  };

  const handleConfirmReject = async () => {
    setShowRejectModal(false);
    setActionLoading(true);
    setError(null);
    try {
      await rejectTemplateReview(template.id);
      await queryClient.invalidateQueries({ queryKey: ['templates'] });
      await refreshDmsDashboardQuery(queryClient);
      goBack({ replace: true });
    } catch (e) {
      setError(e instanceof ApiHttpError ? e.message : e instanceof Error ? e.message : 'Error al rechazar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const selectedBlock = blocks.find((b) => b.id === activeView?.blockId);

  return (
    <PaperPreviewLayout
      title={template.name}
      subtitle={processLabel}
      viewMode="default"
      onBack={() => goBack()}
      backLabel={t('common:navigation.backToTemplates')}
      metaInfo={
        <div className="flex flex-wrap gap-4 text-xs font-bold uppercase tracking-widest text-text-muted justify-center">
          <span>{visibilityLabel(template.visibility_level, t)}</span>
          {template.study_id && <span>• {String(template.study_id)}</span>}
          {template.module_id && <span>• {String(template.module_id)}</span>}
        </div>
      }
      actions={
        <div className="flex items-center gap-2">
          <SequentialValidatorBadge
            reviewMode={template?.review_mode}
            reviewers={(template?.reviewers ?? []).map((r) => ({ stage: r.stage, status: r.status, name: r.user_name }))}
          />
          {isActiveValidator ? (
            <>
              <Button variant="outlineWarning" size="sm" onClick={handleRejectClick}
                disabled={actionLoading} loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider hover:text-warning">
                Rechazar validación
              </Button>
              <Button variant="primary" size="sm" onClick={handleApprove}
                disabled={actionLoading} loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider px-6">
                Validar y Aprobar
              </Button>
            </>
          ) : previousStagesPending ? (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-ui-body dark:bg-ui-dark-border border border-ui-border dark:border-ui-dark-border">
              <span className="text-text-muted dark:text-text-dark-muted text-xs font-black uppercase tracking-widest">
                Esperando etapas anteriores
              </span>
            </div>
          ) : myReview?.status === 'approved' ? (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-success/10 border border-success/20">
              <span className="text-success-dark text-xs font-black uppercase tracking-widest">
                ✓ Aprobaste esta plantilla
              </span>
            </div>
          ) : myReview?.status === 'rejected' ? (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-warning/10 border border-warning/20">
              <span className="text-warning-dark dark:text-warning-light text-xs font-black uppercase tracking-widest">
                ✗ Rechazaste esta plantilla
              </span>
            </div>
          ) : (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-ui-body dark:bg-ui-dark-border border border-ui-border dark:border-ui-dark-border">
              <span className="text-text-muted dark:text-text-dark-muted text-xs font-black uppercase tracking-widest">
                Vista de seguimiento
              </span>
            </div>
          )}
        </div>
      }
      sidebar={
        diffBlockId !== null && !activeView
          ? (
            <TemplateBlockHistoryPanel
              blockId={diffBlockId}
              blockNumber={(blocks.findIndex((b) => b.id === diffBlockId) + 1) || '?'}
              history={template.review_history ?? []}
              hasDescription={Boolean(blocks.find((b) => b.id === diffBlockId)?.description)}
              onClose={() => setDiffBlockId(null)}
            />
          )
          : activeView && selectedBlock
            ? (
              activeView.mode === 'comments' ? (
                <BlockCommentsCard
                  mode={commentMode}
                  blockSortOrder={(selectedBlock.sort_order + 1) || '?'}
                  blockComments={getCommentsForBlock(selectedBlock.id, comments)}
                  allComments={comments}
                  onClose={closeView}
                  onSendMessage={handleSendMessage}
                  commentLoading={commentLoading}
                  submitError={commentSubmitError}
                  canAddComments={
                    canCommentOnDocument(template.status) &&
                    canCreateBlockComment(hasPermission) &&
                    (isCreator || (isReviewer && template.status === 'in_review'))
                  }
                  currentUserId={profile?.id}
                  canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                  onEditComment={handleEditComment}
                  onDeleteComment={handleDeleteComment}
                  onMarkAsRead={handleMarkCommentAsRead}
                  onMarkAllBlockAsRead={handleMarkAllBlockCommentsAsRead}
                />
              ) : (
                <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
                  <ViewCardHeader
                    blockSortOrder={(blocks.findIndex((b) => b.id === selectedBlock.id) + 1) || '?'}
                    title={t('review.blockDescriptionTitle')}
                    onClose={closeView}
                  />
                  <div className="flex-1 overflow-y-auto" style={{ padding: '40px 60px' }}>
                    <InfoBlockDescription description={selectedBlock.description} />
                  </div>
                </div>
              )
            )
            : undefined
      }
    >
      {error && (
        <div
          role="alert"
          className="mb-6 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger-dark dark:text-danger"
        >
          {error}
        </div>
      )}
      {template.submission_changelog?.trim() ? (
        <SubmissionChangelogReadonly text={template.submission_changelog.trim()} />
      ) : null}
      {/* Blocks list (article content) */}
      <div className="space-y-12">
        {blocks.length === 0 ? (
          <div className="py-20 text-center border-2 border-dashed border-ui-border dark:border-ui-dark-border rounded-xl">
            <p className="text-sm text-text-muted italic">{t('review.noBlocks')}</p>
          </div>
        ) : (
          blocks.map((block) => {
            const isSelected = activeView?.blockId === block.id;
            const nodes = normalizeBlockContentForEditor(block.default_content);

            return (
              <section
                key={block.id}
                ref={(el) => {
                  if (el) blockRefs.current.set(block.id, el);
                  else blockRefs.current.delete(block.id);
                }}
                className={[
                  'relative group rounded-lg transition-all duration-200 cursor-pointer',
                  isSelected
                    ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                    : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                ].join(' ')}
                onClick={() => openView(block.id, 'comments')}
              >
                <div className="flex flex-wrap items-start gap-x-3 gap-y-2 mb-4">
                  <h4 className="flex-1 min-w-[140px] text-sm font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                    Bloque {(blocks.findIndex((b) => b.id === block.id) + 1)}: {block.title || 'Sin título'}
                  </h4>
                  <div className="flex flex-wrap items-center gap-2 shrink-0">
                    {Boolean(block.description) && (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          openView(block.id, 'info');
                        }}
                        className={[
                          'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                          isSelected && activeView?.mode === 'info'
                            ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm'
                            : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                        ].join(' ')}
                        title={t('review.viewDescription')}
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{t('common:info')}</span>
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        if (isSelected && activeView?.mode === 'comments') {
                          closeView();
                        } else {
                          openView(block.id, 'comments');
                        }
                      }}
                      className={[
                        'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                        isSelected && activeView?.mode === 'comments'
                          ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm'
                          : 'border-ui-border dark:border-ui-dark-border text-text bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                      ].join(' ')}
                    >
                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                      </svg>
                      <span>{t('common:messages')}</span>
                      {countUnreadCommentsForBlock(block.id, comments) > 0 && (
                        <span className="ml-1 bg-odoo-purple text-text-inverse px-1.5 py-0.5 rounded-full text-2xs leading-none font-bold">
                          {countUnreadCommentsForBlock(block.id, comments)}
                        </span>
                      )}
                    </button>
                    {hasHistory && (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          setActiveView(null);
                          setDiffBlockId((prev) => (prev === block.id ? null : block.id));
                        }}
                        className={[
                          'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                          diffBlockId === block.id
                            ? 'border-odoo-teal text-odoo-teal bg-odoo-teal/10 shadow-sm'
                            : 'border-ui-border dark:border-ui-dark-border text-text bg-ui-body/30 hover:text-odoo-teal hover:border-odoo-teal/50 hover:bg-odoo-teal/5',
                        ].join(' ')}
                        title={t('review.viewBlockHistory')}
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>{t('common:viewChanges')}</span>
                      </button>
                    )}
                  </div>
                </div>
                {isStructuralBlockType(block.block_type) ? (
                  <StructuralBlockPreview block={block} allBlocks={blocks} />
                ) : nodes.length > 0 ? (
                  <BlockContentHtml content={nodes} />
                ) : (
                  <p className="text-sm text-text-muted italic">{t('common:noBlockContent')}</p>
                )}
              </section>
            );
          })
        )}
      </div>

      <ConfirmDialog
        open={showRejectModal}
        title={t('preview.rejectValidationTitle')}
        description={t('review.rejectDescription')}
        confirmLabel={t('review.rejectConfirm')}
        variant="danger"
        loading={actionLoading}
        onCancel={() => setShowRejectModal(false)}
        onConfirm={handleConfirmReject}
      />
      <ConfirmDialog
        open={showNoCommentsWarning}
        title={t('review.requiredComments')}
        description={t('review.noCommentsDescription')}
        confirmLabel={t('common:actions.understood')}
        variant="danger"
        onCancel={() => setShowNoCommentsWarning(false)}
        onConfirm={() => setShowNoCommentsWarning(false)}
      />
    </PaperPreviewLayout>
  );
}
