import { useState, useEffect, useRef } from 'react';
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

type Props = {
  template: Template;
};

type TemplateComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  created_at: string;
  parent_id?: string | null;
};

// Panel geometry constants (px). Keep in sync with the fixed panel JSX.
const PANEL_RIGHT = 24;
const PANEL_WIDTH = 384;

function InfoBlockDescription({ description }: { description: unknown }) {
  if (!description) return null;

  const nodes = normalizeBlockContentForEditor(description);
  if (nodes.length > 0) {
    return <BlockContentHtml content={nodes} />;
  }

  if (typeof description === 'string' && description.trim()) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
        {description}
      </p>
    );
  }

  return null;
}

export function TemplateReviewView({ template }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const { blocks } = useTemplateBlocks(template.id);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [comments, setComments] = useState<TemplateComment[]>([]);
  const [newCommentBody, setNewCommentBody] = useState('');
  const [commentLoading, setCommentLoading] = useState(false);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);
  const [processLabel, setProcessLabel] = useState<string | null>(null);

  const [sidebarMode, setSidebarMode] = useState<'comments' | 'info' | null>(null);
  const [panelTop, setPanelTop] = useState(80);
  const [connectorGeom, setConnectorGeom] = useState<{ top: number; left: number; width: number } | null>(null);
  const [connectorVisible, setConnectorVisible] = useState(false);

  const headerRef = useRef<HTMLDivElement>(null);
  const blockRefs = useRef<Map<string, HTMLElement>>(new Map());
  const panelRef = useRef<HTMLDivElement>(null);
  const cardHeaderRef = useRef<HTMLDivElement>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  const currentUserId = user?.sub || (user as any)?.id;
  const myReview = template.reviewers?.find(r => String(r.user_id) === String(currentUserId));
  const isAlreadyValidated = myReview && myReview.status !== 'pending';
  const isReviewer = !!myReview;
  const isReadOnly = !isReviewer;

  const remainingReviewers = template.reviewers?.filter(r => r.status === 'pending') || [];
  const backTo = (location.state as { backTo?: string } | null)?.backTo ?? '/dashboard';

  const goBack = () => {
    if (window.history.length > 1) {
      navigate(-1);
      return;
    }
    navigate(backTo);
  };

  useEffect(() => {
    void loadComments();
  }, [template.id]);

  useEffect(() => {
    if (!template.process_id) {
      setProcessLabel(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const process = res.data.find((p: Process) => p.id === template.process_id) ?? null;
        setProcessLabel(process ? `Proceso: ${process.code} — ${process.name}` : null);
      })
      .catch(() => {
        if (!cancelled) setProcessLabel(null);
      });
    return () => {
      cancelled = true;
    };
  }, [template.process_id]);

  // Recalculate panel position and connector geometry whenever the selected block changes.
  // position:fixed keeps the panel sticky in the viewport during scroll — no scroll
  // listener needed for the panel itself. Connector visibility is handled separately.
  useEffect(() => {
    if (!sidebarMode || !selectedBlockId) {
      setConnectorGeom(null);
      return;
    }
    const raf = requestAnimationFrame(() => {
      const blockEl = blockRefs.current.get(selectedBlockId);
      if (!blockEl) return;
      const blockRect = blockEl.getBoundingClientRect();
      const panelHeight = panelRef.current?.offsetHeight ?? 420;
      const cardHeaderHeight = cardHeaderRef.current?.offsetHeight ?? 44;
      const headerHeight = headerRef.current?.offsetHeight ?? 60;
      const vh = window.innerHeight;
      const MARGIN = 12;

      let top = blockRect.top;
      top = Math.max(headerHeight + MARGIN, top);
      if (top + panelHeight > vh - MARGIN) {
        top = Math.max(headerHeight + MARGIN, vh - panelHeight - MARGIN);
      }
      setPanelTop(top);

      // Connector line: from right edge of selected block to left edge of panel card.
      const panelLeft = window.innerWidth - PANEL_RIGHT - PANEL_WIDTH;
      const lineLeft = blockRect.right + 10;  // 10px gap past the ring
      const lineWidth = Math.max(0, panelLeft - 8 - lineLeft);
      const lineTop = top + cardHeaderHeight / 2;
      setConnectorGeom({ top: lineTop, left: lineLeft, width: lineWidth });
      setConnectorVisible(true);
    });
    return () => cancelAnimationFrame(raf);
  }, [selectedBlockId, sidebarMode]);

  // Hide/show the connector as the selected block scrolls in/out of the viewport.
  useEffect(() => {
    if (!sidebarMode || !selectedBlockId) {
      setConnectorVisible(false);
      return;
    }
    const scrollEl = scrollContainerRef.current;
    if (!scrollEl) return;
    const headerHeight = headerRef.current?.offsetHeight ?? 60;

    const check = () => {
      const blockEl = blockRefs.current.get(selectedBlockId);
      if (!blockEl) { setConnectorVisible(false); return; }
      const r = blockEl.getBoundingClientRect();
      setConnectorVisible(r.bottom > headerHeight && r.top < window.innerHeight);
    };

    scrollEl.addEventListener('scroll', check, { passive: true });
    return () => scrollEl.removeEventListener('scroll', check);
  }, [selectedBlockId, sidebarMode]);

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
        body: {
          body: newCommentBody,
          blockable_id: selectedBlockId,
        },
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
    if (comments.length === 0) {
      setShowNoCommentsWarning(true);
    } else {
      setShowRejectModal(true);
    }
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

  const closePanel = () => {
    setSidebarMode(null);
    setSelectedBlockId(null);
  };

  const selectedBlock = blocks.find(b => b.id === selectedBlockId);
  const blockComments = comments.filter(c => c.blockable_id === selectedBlockId);

  return (
    <div className="flex flex-col h-full bg-ui-preview-bg dark:bg-ui-dark-bg/50">
      {/* Header con acciones */}
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
              <Button
                variant="outlineWarning"
                size="sm"
                onClick={handleRejectClick}
                disabled={actionLoading}
                loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider"
              >
                Rechazar validación
              </Button>
              <Button
                variant="primary"
                size="sm"
                onClick={handleApprove}
                disabled={actionLoading}
                loading={actionLoading}
                className="text-xs font-black uppercase tracking-wider px-6"
              >
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

      {/* Área de trabajo */}
      <div className="flex-1 overflow-hidden flex relative">

        {/* Document scroll area — folio centrado a max-width 850px */}
        <div ref={scrollContainerRef} className="flex-1 overflow-y-auto p-8 scroll-smooth custom-scrollbar">
          <article
            className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content rounded-sm transition-all duration-300 animate-in fade-in slide-in-from-bottom-4"
            style={{ maxWidth: '850px', minHeight: '100%', padding: '60px 70px' }}
          >
            {/* Header del documento */}
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

            {/* Bloques */}
            {blocks.length === 0 ? (
              <div className="py-20 text-center border-2 border-dashed border-ui-border dark:border-ui-dark-border rounded-xl">
                <p className="text-sm text-text-muted italic">Esta plantilla no tiene bloques configurados.</p>
              </div>
            ) : (
              <div className="space-y-12">
                {blocks.map((block) => {
                  const isSelected = selectedBlockId === block.id;
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
                        setSelectedBlockId(block.id);
                        setSidebarMode('comments');
                      }}
                      className={[
                        'relative group rounded-lg transition-all duration-200 cursor-pointer',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                          : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                      ].join(' ')}
                    >
                      {/* Badge del bloque */}
                      <div className={[
                        'absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200',
                        isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted',
                      ].join(' ')}>
                        #{(block.sort_order ?? '?') as any}
                      </div>

                      {/* Cabecera del bloque */}
                      <div className="flex items-center gap-3 mb-4">
                        <h3 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          {(block.title ? String(block.title) : 'Bloque sin título') as any}
                        </h3>

                        <div className="flex items-center gap-2">
                          {/* Botón Descripción */}
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedBlockId(block.id);
                              setSidebarMode('info');
                            }}
                            className={[
                              'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                              sidebarMode === 'info' && isSelected
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

                          {/* Botón Mensajes */}
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedBlockId(block.id);
                              setSidebarMode('comments');
                            }}
                            className={[
                              'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                              sidebarMode === 'comments' && isSelected
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

        {/* Spacer — reserves width so the folio doesn't sit under the fixed panel.
             Must match PANEL_RIGHT + PANEL_WIDTH + a small left gap. */}
        {sidebarMode !== null && (
          <div className="w-[420px] shrink-0" aria-hidden="true" />
        )}

        {/* Connector line — links the selected block header to the card header */}
        {sidebarMode !== null && connectorGeom && (
          <div
            className="bg-odoo-purple pointer-events-none"
            style={{
              position: 'fixed',
              top: connectorGeom.top,
              left: connectorGeom.left,
              width: connectorGeom.width,
              height: 1.5,
              zIndex: 38,
              opacity: connectorVisible ? 0.55 : 0,
              transform: 'translateY(-50%)',
              transition: 'opacity 200ms ease',
            }}
            aria-hidden="true"
          />
        )}

        {/* Floating review card — position:fixed, top aligned to selected block */}
        {sidebarMode !== null && selectedBlock && (
          <div
            ref={panelRef}
            className="fixed z-40 animate-in fade-in slide-in-from-right-4 duration-300"
            style={{ top: panelTop, right: PANEL_RIGHT, width: PANEL_WIDTH }}
          >
            <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl border border-ui-border/60 dark:border-ui-dark-border flex flex-col overflow-hidden">

              {/* Card header — git-diff style: BLOQUE #N | COMENTARIOS DE REVISIÓN ✕ */}
              <div
                ref={cardHeaderRef}
                className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0"
              >
                {/* Left zone: block identifier */}
                <div className="px-4 py-3 flex items-center shrink-0">
                  <span className="text-[11px] font-black uppercase tracking-widest text-odoo-purple">
                    Bloque #{(selectedBlock.sort_order ?? '?') as any}
                  </span>
                </div>

                {/* Vertical divider */}
                <div className="w-px bg-ui-border dark:bg-ui-dark-border self-stretch" />

                {/* Right zone: panel title + close */}
                <div className="flex-1 px-4 py-3 flex items-center justify-between min-w-0">
                  <span className="text-[11px] font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary truncate">
                    Comentarios de Revisión
                  </span>
                  <button
                    aria-label="Cerrar panel de revisión"
                    onClick={closePanel}
                    className="group ml-3 w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-all shrink-0"
                  >
                    <span className="block text-sm leading-none group-hover:rotate-90 transition-transform duration-200">✕</span>
                  </button>
                </div>
              </div>

              {/* Tabs */}
              <div className="flex gap-6 px-4 border-b border-ui-border dark:border-ui-dark-border">
                <button
                  onClick={() => setSidebarMode('comments')}
                  className={[
                    'py-3 text-xs font-bold uppercase tracking-widest transition-all relative',
                    sidebarMode === 'comments'
                      ? 'text-odoo-purple after:absolute after:bottom-0 after:left-0 after:w-full after:h-[3px] after:bg-odoo-purple'
                      : 'text-text-muted hover:text-text-primary dark:hover:text-text-dark-primary',
                  ].join(' ')}
                >
                  Mensajes
                </button>
                <button
                  onClick={() => setSidebarMode('info')}
                  className={[
                    'py-3 text-xs font-bold uppercase tracking-widest transition-all relative',
                    sidebarMode === 'info'
                      ? 'text-odoo-purple after:absolute after:bottom-0 after:left-0 after:w-full after:h-[3px] after:bg-odoo-purple'
                      : 'text-text-muted hover:text-text-primary dark:hover:text-text-dark-primary',
                  ].join(' ')}
                >
                  Descripción
                </button>
              </div>

              {/* Tab content */}
              {sidebarMode === 'info' ? (
                <div className="px-4 py-4 max-h-[55vh] overflow-y-auto custom-scrollbar">
                  <InfoBlockDescription description={selectedBlock.description} />
                </div>
              ) : (
                <div className="flex flex-col min-h-0">
                  {/* Messages list */}
                  <div className="max-h-[42vh] overflow-y-auto custom-scrollbar px-4 pt-4 pb-2 space-y-6">
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
                          const replies = comments.filter(r => r.parent_id === comment.id);
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

                  {/* Input — always at the bottom */}
                  {!isAlreadyValidated && !isReadOnly ? (
                    <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0">
                      <textarea
                        value={newCommentBody}
                        onChange={(e) => setNewCommentBody(e.target.value)}
                        placeholder="Escribe un mensaje de revisión..."
                        className="w-full h-24 p-3 text-sm rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
                      />
                      <div className="mt-3 flex justify-end">
                        <Button
                          variant="primary"
                          size="md"
                          className="text-xs font-black uppercase tracking-widest px-6 py-2"
                          onClick={handleAddComment}
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
              )}
            </div>
          </div>
        )}
      </div>

      {/* Diálogos */}
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
