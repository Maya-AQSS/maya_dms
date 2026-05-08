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
import type { Process } from '../../../types/processes';

type Props = { template: Template };

type TemplateComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  created_at: string;
  parent_id?: string | null;
};

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

// Column widths (px). COMMENTS fits alongside the folio on standard desktop.
// INFO matches folio width — causes horizontal overflow (accepted per spec).
const COMMENTS_COL_WIDTH = 408;   // card fills 408 − 24px right gap = 384px
const INFO_COL_WIDTH = 882;        // page fills 882 − 32px right gap = 850px
// Min-width for the document column in info mode so the folio isn't squished.
const DOC_COL_MIN_WIDTH_INFO = 914; // 850 folio + 2×32 padding

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

/** Git-diff-style two-zone header shared by both card types. */
function ViewCardHeader({
  blockSortOrder,
  title,
  onClose,
  headerRef,
}: {
  blockSortOrder: unknown;
  title: string;
  onClose: () => void;
  headerRef: RefObject<HTMLDivElement | null>;
}) {
  return (
    <div
      ref={headerRef}
      className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0"
    >
      <div className="px-4 py-3 flex items-center shrink-0">
        <span className="text-[11px] font-black uppercase tracking-widest text-odoo-purple">
          Bloque #{blockSortOrder as any}
        </span>
      </div>
      <div className="w-px bg-ui-border dark:bg-ui-dark-border self-stretch" />
      <div className="flex-1 px-4 py-3 flex items-center justify-between min-w-0">
        <span className="text-[11px] font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary truncate">
          {title}
        </span>
        <button
          aria-label="Cerrar panel de revisión"
          onClick={onClose}
          className="group ml-3 w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-all shrink-0"
        >
          <span className="block text-sm leading-none group-hover:rotate-90 transition-transform duration-200">✕</span>
        </button>
      </div>
    </div>
  );
}

type CommentsCardProps = {
  selectedBlock: any;
  blockComments: TemplateComment[];
  allComments: TemplateComment[];
  isAlreadyValidated: boolean | null | undefined;
  isReadOnly: boolean;
  newCommentBody: string;
  onNewCommentBodyChange: (v: string) => void;
  onAddComment: () => void;
  commentLoading: boolean;
  headerRef: RefObject<HTMLDivElement | null>;
  onClose: () => void;
};

function ValidatorCommentsCard({
  selectedBlock,
  blockComments,
  allComments,
  isAlreadyValidated,
  isReadOnly,
  newCommentBody,
  onNewCommentBodyChange,
  onAddComment,
  commentLoading,
  headerRef,
  onClose,
}: CommentsCardProps) {
  return (
    <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl border border-ui-border/60 dark:border-ui-dark-border flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300">
      <ViewCardHeader
        blockSortOrder={selectedBlock.sort_order ?? '?'}
        title="Comentarios de Revisión"
        onClose={onClose}
        headerRef={headerRef}
      />

      {/* Messages list — internal scroll */}
      <div className="max-h-[50vh] overflow-y-auto custom-scrollbar px-4 pt-4 pb-2 space-y-6">
        {blockComments.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
            <svg className="w-10 h-10 mb-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <p className="text-sm font-medium text-text-muted leading-relaxed">
              No hay mensajes en este bloque.
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            {blockComments.filter(c => !c.parent_id).map((comment) => {
              const replies = allComments.filter(r => r.parent_id === comment.id);
              return (
                <div key={comment.id} className="space-y-3">
                  <div className="group relative pl-5 animate-in fade-in slide-in-from-right-2">
                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-ui-border dark:bg-ui-dark-border group-hover:bg-odoo-purple/40 transition-colors rounded-full" />
                    <div className="flex items-center justify-between mb-1.5">
                      <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
                        {comment.author?.name || 'Usuario'}
                      </span>
                      <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider opacity-70">
                        {new Date(comment.created_at).toLocaleDateString()}
                      </span>
                    </div>
                    <div className="text-sm text-text-primary dark:text-text-dark-primary leading-relaxed bg-ui-body/30 dark:bg-ui-dark-bg p-4 rounded-xl border border-ui-border/50 dark:border-ui-dark-border/50 shadow-sm">
                      {comment.body}
                    </div>
                  </div>
                  {replies.length > 0 && (
                    <div className="ml-8 space-y-3">
                      {replies.map(r => (
                        <div key={r.id} className="relative pl-3 border-l-2 border-ui-border/30 dark:border-ui-dark-border/30">
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                              {r.author?.name || 'Usuario'}
                            </span>
                            <span className="text-[10px] text-text-muted font-bold uppercase tracking-widest">
                              {new Date(r.created_at).toLocaleDateString()}
                            </span>
                          </div>
                          <div className="text-xs text-text-primary dark:text-text-dark-primary bg-ui-body/10 dark:bg-ui-dark-bg p-3 rounded-lg border border-ui-border/20 dark:border-ui-dark-border/20 italic">
                            {r.body}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Input — always visible at bottom */}
      {!isAlreadyValidated && !isReadOnly ? (
        <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0">
          <textarea
            value={newCommentBody}
            onChange={(e) => onNewCommentBodyChange(e.target.value)}
            placeholder="Escribe un mensaje de revisión..."
            className="w-full h-24 p-3 text-sm rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
          />
          <div className="mt-3 flex justify-end">
            <Button
              variant="primary"
              size="md"
              className="text-xs font-black uppercase tracking-widest px-6 py-2"
              onClick={onAddComment}
              loading={commentLoading}
              disabled={!newCommentBody.trim() || commentLoading}
            >
              Enviar mensaje
            </Button>
          </div>
        </div>
      ) : (
        <div className="px-4 pb-4 pt-3 border-t border-ui-border dark:border-ui-dark-border shrink-0">
          <div className="p-4 rounded-xl bg-ui-body/5 dark:bg-ui-dark-border/20 border border-ui-border dark:border-ui-dark-border text-center">
            <p className="text-xs text-text-muted dark:text-text-dark-muted italic font-medium">
              No puedes añadir más mensajes porque ya has finalizado tu validación.
            </p>
          </div>
        </div>
      )}
    </div>
  );
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
  const { blocks } = useTemplateBlocks(template.id);

  const [activeView, setActiveView] = useState<ActiveView | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [comments, setComments] = useState<TemplateComment[]>([]);
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
  const isAlreadyValidated = myReview && myReview.status !== 'pending';
  const isReviewer = !!myReview;
  const isReadOnly = !isReviewer;

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
      const res = await apiFetchJson<{ data: TemplateComment[] }>(`templates/${template.id}/comments`);
      setComments(res.data);
    } catch (e) {
      console.error('Error loading comments', e);
    }
  };

  const handleAddComment = async () => {
    if (!newCommentBody.trim()) return;
    setCommentLoading(true);
    try {
      const res = await apiFetchJson<{ data: TemplateComment }>(`templates/${template.id}/comments`, {
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
    if (comments.length === 0) setShowNoCommentsWarning(true);
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

  const closeView = () => setActiveView(null);

  const selectedBlock = blocks.find(b => b.id === activeView?.blockId);
  const blockComments = comments.filter(c => c.blockable_id === activeView?.blockId);

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
          {isReadOnly ? (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-ui-body dark:bg-ui-dark-border border border-ui-border dark:border-ui-dark-border">
              <span className="text-text-muted dark:text-text-dark-muted text-xs font-black uppercase tracking-widest">
                Vista de seguimiento
              </span>
            </div>
          ) : !isAlreadyValidated ? (
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
          ) : (
            <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-success/10 border border-success/20">
              <span className="text-success-dark text-xs font-black uppercase tracking-widest">
                ✓ Ya has validado esta plantilla
              </span>
            </div>
          )}
        </div>
      </div>

      {isAlreadyValidated && remainingReviewers.length > 0 && (
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

          {/* ── Document (folio) column ──────────────────────────────────────── */}
          <div
            className={activeView?.mode === 'info' ? 'shrink-0 p-8' : 'flex-1 p-8'}
            style={activeView?.mode === 'info' ? { minWidth: DOC_COL_MIN_WIDTH_INFO } : undefined}
          >
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
                            setActiveView({ blockId: block.id, mode: 'comments' });
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
                                  setActiveView({ blockId: block.id, mode: 'info' });
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
                                  setActiveView({ blockId: block.id, mode: 'comments' });
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
          {activeView && selectedBlock && (
            <div
              ref={viewColRef}
              className="shrink-0 pr-6"
              style={{
                width: activeView.mode === 'comments' ? COMMENTS_COL_WIDTH : INFO_COL_WIDTH,
                paddingTop: viewPaddingTop,
              }}
            >
              {activeView.mode === 'comments' ? (
                <ValidatorCommentsCard
                  selectedBlock={selectedBlock}
                  blockComments={blockComments}
                  allComments={comments}
                  isAlreadyValidated={isAlreadyValidated}
                  isReadOnly={isReadOnly}
                  newCommentBody={newCommentBody}
                  onNewCommentBodyChange={setNewCommentBody}
                  onAddComment={handleAddComment}
                  commentLoading={commentLoading}
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
