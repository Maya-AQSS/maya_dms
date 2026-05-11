import { useState, useEffect, useRef, type RefObject } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { Button, ConfirmDialog } from '@maya/shared-ui-react';
import { approveTemplateReview, rejectTemplateReview } from '../../../api/templates';
import { fetchProcesses } from '../../../api/processes';
import { apiFetchJson } from '../../../api/http';
import { useAuth } from '@maya/shared-auth-react';
import { useUserProfile } from '../../user-profile';
import type { Process } from '../../../types/processes';
import { BlockCommentsCard, ViewCardHeader } from './BlockCommentsCard';
import type { BlockComment, CommentMode } from './BlockCommentsCard';

type Props = { template: Template };

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

// Comments card column width (px). The card fills 408 − 24px right gap = 384px.
// In comments mode the document column stays flex-1 (normal folio width).
// In info mode both columns are flex-1 (strict 50/50, no fixed widths).
const COMMENTS_COL_WIDTH = 408;

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

function ValidatorInfoPage({
  selectedBlock,
  headerRef,
  onClose,
}: {
  selectedBlock: any;
  headerRef: RefObject<HTMLDivElement | null>;
  onClose: () => void;
}) {
  return (
    <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-sm flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300">
      <ViewCardHeader
        blockSortOrder={selectedBlock.sort_order ?? '?'}
        title="Descripción del Bloque"
        onClose={onClose}
        headerRef={headerRef}
      />
      <div style={{ padding: '40px 60px' }}>
        <InfoBlockDescription description={selectedBlock.description} />
        {!selectedBlock.description && (
          <p className="text-sm text-text-muted italic">Este bloque no tiene descripción.</p>
        )}
      </div>
    </div>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export function TemplateReviewView({ template }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const { profile } = useUserProfile();
  const { blocks } = useTemplateBlocks(template.id);

  const [activeView, setActiveView] = useState<ActiveView | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [comments, setComments] = useState<BlockComment[]>([]);
  const [commentingOpen, setCommentingOpen] = useState(true);
  const [newCommentBody, setNewCommentBody] = useState('');
  const [commentLoading, setCommentLoading] = useState(false);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);
  const [processLabel, setProcessLabel] = useState<string | null>(null);

  // viewPaddingTop: pushes the view column down so its top aligns with the selected block.
  const [viewPaddingTop, setViewPaddingTop] = useState(0);
  const [connectorGeom, setConnectorGeom] = useState<{ top: number; left: number; width: number } | null>(null);

  const headerRef = useRef<HTMLDivElement>(null);       // page header (for height only)
  const blockRefs = useRef<Map<string, HTMLElement>>(new Map());
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const articleRef = useRef<HTMLElement>(null);
  const viewColRef = useRef<HTMLDivElement>(null);
  const viewHeaderRef = useRef<HTMLDivElement>(null);   // card/page header inside the view

  const currentUserId = user?.sub || (user as any)?.id;
  const myReview = template.reviewers?.find(r => String(r.user_id) === String(currentUserId));
  const isReviewer = !!myReview;
  const isCreator = !!profile?.id && template.created_by === profile.id;

  // Determine the comment card mode for this user:
  // - validator: assigned reviewer while template is in_review and review is pending
  // - creator-edit: creator viewing a draft with unresolved review comments (post-rejection)
  // - creator-readonly: creator in any other state (in_review being actively reviewed, etc.)
  const commentMode: CommentMode = (() => {
    if (isReviewer && template.status === 'in_review' && myReview?.status === 'pending') return 'validator';
    if (isCreator && template.status === 'draft' && template.has_review_comments) return 'creator-edit';
    if (isCreator) return 'creator-readonly';
    return 'creator-readonly';
  })();

  const remainingReviewers = template.reviewers?.filter(r => r.status === 'pending') || [];
  const backTo = (location.state as { backTo?: string } | null)?.backTo ?? '/dashboard';

  const goBack = () => {
    if (window.history.length > 1) { navigate(-1); return; }
    navigate(backTo);
  };

  useEffect(() => { void loadComments(); }, [template.id]);

  useEffect(() => {
    if (!template.process_id) { setProcessLabel(null); return; }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const process = res.data.find((p: Process) => p.id === template.process_id) ?? null;
        setProcessLabel(process ? `Proceso: ${process.code} — ${process.name}` : null);
      })
      .catch(() => { if (!cancelled) setProcessLabel(null); });
    return () => { cancelled = true; };
  }, [template.process_id]);

  // Recalculate view position and connector whenever the active view changes.
  // Both the view column and the connector are position:absolute inside the scroll
  // container, so they scroll with the document (non-sticky).
  useEffect(() => {
    if (!activeView) {
      setConnectorGeom(null);
      setViewPaddingTop(0);
      return;
    }
    const raf = requestAnimationFrame(() => {
      const blockEl = blockRefs.current.get(activeView.blockId);
      const scrollEl = scrollContainerRef.current;
      const artEl = articleRef.current;
      if (!blockEl || !scrollEl || !artEl) return;

      const scrollRect = scrollEl.getBoundingClientRect();
      const blockRect = blockEl.getBoundingClientRect();
      const artRect = artEl.getBoundingClientRect();

      // Block's Y in scroll-content space (accounts for current scroll position).
      const blockTopInScroll = blockRect.top - scrollRect.top + scrollEl.scrollTop;
      setViewPaddingTop(blockTopInScroll);

      // Connector geometry — both in scroll-content space.
      const viewHeaderH = viewHeaderRef.current?.offsetHeight ?? 44;
      const connectorTop = blockTopInScroll + viewHeaderH / 2;

      // Article right edge in scroll-content X space.
      const artRightX = artRect.right - scrollRect.left;

      // View column left edge in scroll-content X space.
      // The view column is already in the DOM (activeView triggered render before effect ran).
      const viewColRect = viewColRef.current?.getBoundingClientRect();
      if (viewColRect) {
        const viewColLeftX = viewColRect.left - scrollRect.left;
        const connectorLeft = artRightX + 6;
        const connectorWidth = Math.max(0, viewColLeftX - 6 - connectorLeft);
        setConnectorGeom({ top: connectorTop, left: connectorLeft, width: connectorWidth });
      }
    });
    return () => cancelAnimationFrame(raf);
  }, [activeView]);

  const loadComments = async () => {
    try {
      const res = await apiFetchJson<{ data: BlockComment[]; meta?: { commenting_open?: boolean } }>(
        `templates/${template.id}/comments`,
      );
      setComments(res.data);
      if (res.meta?.commenting_open === false) setCommentingOpen(false);
    } catch (e) {
      console.error('Error loading comments', e);
    }
  };

  const handleAddComment = async () => {
    if (!newCommentBody.trim()) return;
    setCommentLoading(true);
    try {
      const res = await apiFetchJson<{ data: BlockComment }>(`templates/${template.id}/comments`, {
        method: 'POST',
        body: { body: newCommentBody, blockable_id: activeView?.blockId },
      });
      setComments([...comments, res.data]);
      setNewCommentBody('');
    } catch (e) {
      setError('No se pudo guardar el comentario.');
    } finally {
      setCommentLoading(false);
    }
  };

  const handleApprove = async () => {
    setActionLoading(true);
    setError(null);
    try {
      await approveTemplateReview(template.id);
      navigate(backTo);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al aprobar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRejectClick = () => {
    const myComments = comments.filter(
      c => String(c.author_id) === String(currentUserId) && !c.parent_id,
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
      navigate(backTo);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al rechazar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const handleReply = async (parentCommentId: string, body: string) => {
    const parent = comments.find(c => c.id === parentCommentId);
    try {
      const res = await apiFetchJson<{ data: BlockComment }>(`templates/${template.id}/comments`, {
        method: 'POST',
        body: {
          body,
          parent_id: parentCommentId,
          blockable_id: parent?.blockable_id ?? null,
        },
      });
      setComments(prev => [...prev, res.data]);
    } catch {
      setError('No se pudo enviar la respuesta.');
    }
  };

  const openView = (blockId: string, mode: 'comments' | 'info') => {
    setActiveView({ blockId, mode });
    setNewCommentBody('');
  };

  const closeView = () => setActiveView(null);

  const selectedBlock = blocks.find(b => b.id === activeView?.blockId);
  const blockComments = (() => {
    const bid = activeView?.blockId;
    if (!bid) return [];
    const rootIds = comments
      .filter(c => c.blockable_id === bid && !c.parent_id)
      .map(c => c.id);
    return comments.filter(
      c => (c.blockable_id === bid && !c.parent_id)
        || (c.parent_id !== null && rootIds.includes(c.parent_id)),
    );
  })();

  return (
    <div className="flex flex-col h-full bg-ui-preview-bg dark:bg-ui-dark-bg/50">

      {/* ── Page header ─────────────────────────────────────────────────────── */}
      <div
        ref={headerRef}
        className="shrink-0 px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shadow-md z-20"
      >
        <div className="flex items-center gap-3">
          <button
            onClick={goBack}
            className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-secondary transition-colors"
          >
            ←
          </button>
          <div>
            <h2 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
              Validación de Plantilla
            </h2>
            <p className="text-xs text-text-muted uppercase tracking-widest font-black truncate max-w-[200px]">
              {template.name}
            </p>
            {processLabel && (
              <p className="text-[11px] text-text-muted mt-0.5 truncate max-w-[420px]">
                {processLabel}
              </p>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2">
          {commentMode === 'validator' ? (
            <>
              <Button variant="outlineWarning" size="sm" onClick={handleRejectClick}
                disabled={actionLoading} loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider">
                Rechazar validación
              </Button>
              <Button variant="primary" size="sm" onClick={handleApprove}
                disabled={actionLoading} loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider px-6">
                Validar y Aprobar
              </Button>
            </>
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
      </div>

      {myReview?.status === 'approved' && remainingReviewers.length > 0 && (
        <div className="mx-6 mt-4 p-3 bg-odoo-purple/5 border border-odoo-purple/20 rounded-lg flex items-center justify-between animate-in fade-in slide-in-from-top-2">
          <div className="flex items-center gap-3">
            <span className="text-lg">⏳</span>
            <div>
              <p className="text-xs font-black uppercase tracking-widest text-odoo-purple">Pendiente de otros validadores</p>
              <p className="text-xs text-text-secondary dark:text-text-dark-secondary">
                Faltan {remainingReviewers.length}{' '}
                {remainingReviewers.length === 1 ? 'persona' : 'personas'} por validar:{' '}
                <span className="font-bold ml-1">
                  {remainingReviewers.map(r => r.user_name || 'Usuario').join(', ')}
                </span>
              </p>
            </div>
          </div>
        </div>
      )}

      {error && (
        <div className="mx-6 mt-4 p-3 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold animate-in slide-in-from-top-1 z-10">
          ⚠️ {error}
        </div>
      )}

      {/* ── Work area — single scroll context, both columns move together ──── */}
      <div
        ref={scrollContainerRef}
        className="flex-1 overflow-y-auto scroll-smooth custom-scrollbar relative"
      >
        {/* Connector line — absolute, scrolls with the page */}
        {activeView && connectorGeom && (
          <div
            className="bg-odoo-purple pointer-events-none"
            style={{
              position: 'absolute',
              top: connectorGeom.top,
              left: connectorGeom.left,
              width: connectorGeom.width,
              height: 1.5,
              zIndex: 10,
              transform: 'translateY(-50%)',
            }}
            aria-hidden="true"
          />
        )}

        <div className="flex min-h-full">

          {/* ── Document (folio) column — always flex-1, fills available width ── */}
          <div className="flex-1 p-8">
            <article
              ref={articleRef as RefObject<HTMLElement>}
              className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content rounded-sm transition-all duration-300 animate-in fade-in slide-in-from-bottom-4"
              style={{ maxWidth: '850px', minHeight: '100%', padding: '60px 70px' }}
            >
              {/* Document header */}
              <header className="mb-12 border-b border-ui-border dark:border-ui-dark-border pb-8">
                <h1 className="text-3xl font-black text-text-primary dark:text-text-dark-primary mb-4 leading-tight">
                  {template.name}
                </h1>
                <div className="flex flex-wrap gap-4 text-xs font-bold uppercase tracking-widest text-text-muted">
                  <span>{visibilityLabel(template.visibility_level)}</span>
                  {template.study_id && <span>• {String(template.study_id)}</span>}
                  {template.module_id && <span>• {String(template.module_id)}</span>}
                </div>
              </header>

              {/* Blocks */}
              {blocks.length === 0 ? (
                <div className="py-20 text-center border-2 border-dashed border-ui-border dark:border-ui-dark-border rounded-xl">
                  <p className="text-sm text-text-muted italic">Esta plantilla no tiene bloques configurados.</p>
                </div>
              ) : (
                <div className="space-y-12">
                  {blocks.map((block) => {
                    const isSelected = activeView?.blockId === block.id;
                    const hasComments = comments.some(c => c.blockable_id === block.id);
                    const nodes = normalizeBlockContentForEditor(block.default_content);

                    return (
                      <section
                        key={block.id}
                        ref={(el) => {
                          if (el) blockRefs.current.set(block.id, el);
                          else blockRefs.current.delete(block.id);
                        }}
                        onClick={(e) => {
                          e.stopPropagation();
                          // Click on block body toggles comment view for this block.
                          if (isSelected && activeView?.mode === 'comments') {
                            closeView();
                          } else {
                            openView(block.id, 'comments');
                          }
                        }}
                        className={[
                          'relative group rounded-lg transition-all duration-200 cursor-pointer',
                          isSelected
                            ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                            : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                        ].join(' ')}
                      >
                        {/* Block order badge */}
                        <div className={[
                          'absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200',
                          isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted',
                        ].join(' ')}>
                          #{(block.sort_order ?? '?') as any}
                        </div>

                        {/* Block header row */}
                        <div className="flex items-center gap-3 mb-4">
                          <h3 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                            {(block.title ? String(block.title) : 'Bloque sin título') as any}
                          </h3>

                          <div className="flex items-center gap-2">
                            {/* Info button — opens description page */}
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation();
                                if (isSelected && activeView?.mode === 'info') {
                                  closeView();
                                } else {
                                  openView(block.id, 'info');
                                }
                              }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                isSelected && activeView?.mode === 'info'
                                  ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm'
                                  : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                              ].join(' ')}
                              title="Ver descripción del bloque"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              <span>Info</span>
                            </button>

                            {/* Messages button — opens comments card */}
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
                                  : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                              ].join(' ')}
                              title="Ver comentarios del bloque"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                              </svg>
                              <span>Mensajes</span>
                              {hasComments && (
                                <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-xs leading-none">
                                  {comments.filter(c => c.blockable_id === block.id).length}
                                </span>
                              )}
                            </button>
                          </div>
                        </div>

                        <div>
                          {nodes.length > 0 ? (
                            <BlockContentHtml content={nodes} />
                          ) : (
                            <p className="text-xs text-text-muted italic">Sin contenido configurado.</p>
                          )}
                        </div>
                      </section>
                    );
                  })}
                </div>
              )}
            </article>
          </div>

          {/* ── View column — scrolls with document, top aligned to block ─── */}
          {/* COMMENTS: fixed width, folio stays normal.                        */}
          {/* INFO: flex-1, both columns share space equally (50/50).           */}
          {activeView && selectedBlock && (
            <div
              ref={viewColRef}
              className={activeView.mode === 'comments' ? 'shrink-0 pr-6' : 'flex-1 pr-6'}
              style={{
                ...(activeView.mode === 'comments' ? { width: COMMENTS_COL_WIDTH } : {}),
                paddingTop: viewPaddingTop,
              }}
            >
              {activeView.mode === 'comments' ? (
                <BlockCommentsCard
                  mode={commentMode}
                  blockSortOrder={selectedBlock.sort_order ?? '?'}
                  blockComments={blockComments}
                  allComments={comments}
                  newCommentBody={newCommentBody}
                  onNewCommentBodyChange={setNewCommentBody}
                  onAddComment={handleAddComment}
                  commentLoading={commentLoading}
                  canAddComments={commentingOpen && commentMode === 'validator'}
                  onReply={commentingOpen && commentMode === 'creator-edit' ? handleReply : undefined}
                  commentingClosed={!commentingOpen}
                  headerRef={viewHeaderRef}
                  onClose={closeView}
                />
              ) : (
                <ValidatorInfoPage
                  selectedBlock={selectedBlock}
                  headerRef={viewHeaderRef}
                  onClose={closeView}
                />
              )}
            </div>
          )}
        </div>
      </div>

      {/* ── Dialogs ─────────────────────────────────────────────────────────── */}
      <ConfirmDialog
        open={showRejectModal}
        title="¿Rechazar validación?"
        description="La plantilla volverá al estado de borrador y el creador recibirá tus comentarios para corregirla."
        confirmLabel="Rechazar definitivamente"
        variant="danger"
        loading={actionLoading}
        onCancel={() => setShowRejectModal(false)}
        onConfirm={handleConfirmReject}
      />
      <ConfirmDialog
        open={showNoCommentsWarning}
        title="Comentarios obligatorios"
        description="Para rechazar una validación debes indicar al menos una razón o comentario en algún bloque para que el creador sepa qué corregir."
        confirmLabel="Entendido"
        variant="danger"
        onCancel={() => setShowNoCommentsWarning(false)}
        onConfirm={() => setShowNoCommentsWarning(false)}
      />
    </div>
  );
}
