import { useState, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
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

function InfoBlockDescription({ description }: { description: unknown }) {
  if (!description) return null;

  let parsed: unknown[] | null = null;
  if (Array.isArray(description)) {
    parsed = description;
  } else if (typeof description === 'string') {
    try {
      const p: unknown = JSON.parse(description);
      if (Array.isArray(p)) parsed = p;
      else if (p && typeof p === 'object') parsed = [p];
    } catch { /* plain text fallback */ }
  } else if (typeof description === 'object') {
    parsed = [description];
  }

  if (parsed) {
    return (
      <div className="prose prose-sm dark:prose-invert max-w-none">
        <BlockContentHtml content={parsed as unknown[]} />
      </div>
    );
  }

  return (
    <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
      {String(description)}
    </p>
  );
}

export function TemplateReviewView({ template }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const { blocks } = useTemplateBlocks(template.id);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // Comentarios
  const [comments, setComments] = useState<TemplateComment[]>([]);
  const [newCommentBody, setNewCommentBody] = useState('');
  const [commentLoading, setCommentLoading] = useState(false);
  
  // Modales
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);
  const [processLabel, setProcessLabel] = useState<string | null>(null);

  // Estado de la barra lateral: 'comments' | 'info' | null
  const [sidebarMode, setSidebarMode] = useState<'comments' | 'info' | null>(null);

  const currentUserId = user?.sub || (user as any)?.id;
  const myReview = template.reviewers?.find(r => String(r.user_id) === String(currentUserId));
  const isAlreadyValidated = myReview && myReview.status !== 'pending';
  // Creator (template owner) can view but cannot approve/reject/comment
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
    // Cargar comentarios iniciales
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
        if (!process) {
          setProcessLabel(null);
          return;
        }
        setProcessLabel(`Proceso: ${process.code} — ${process.name}`);
      })
      .catch(() => {
        if (!cancelled) setProcessLabel(null);
      });
    return () => {
      cancelled = true;
    };
  }, [template.process_id]);

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
        }
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

  const selectedBlock = blocks.find(b => b.id === selectedBlockId);
  const blockComments = comments.filter(c => c.blockable_id === selectedBlockId);

  return (
    <div className="flex flex-col h-full bg-ui-preview-bg dark:bg-ui-dark-bg/50">
      {/* Header con acciones */}
      <div className="shrink-0 px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shadow-md z-20">
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
                Faltan {remainingReviewers.length} {remainingReviewers.length === 1 ? 'persona' : 'personas'} por validar: 
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

      {/* Área de trabajo con Paper + Sidebar Unificada */}
      <div className="flex-1 overflow-hidden flex relative">
        
        {/* Document Render (Paper) */}
        <div className="flex-1 overflow-y-auto p-8 scroll-smooth custom-scrollbar">
          <article
            className="mx-auto bg-white dark:bg-ui-card shadow-2xl rounded-sm transition-all duration-300 animate-in fade-in slide-in-from-bottom-4"
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
                  const content = block.default_content;
                  const hasComments = comments.some(c => c.blockable_id === block.id);

                  let parsed: unknown[] | null = null;
                  if (Array.isArray(content)) {
                    if (content.length > 0) parsed = content;
                  } else if (typeof content === 'string') {
                    try {
                      const p = JSON.parse(content);
                      if (Array.isArray(p) && p.length > 0) parsed = p;
                    } catch { /* fallback */ }
                  }

                  return (
                    <section
                      key={block.id}
                      onClick={(e) => {
                        e.stopPropagation();
                        setSelectedBlockId(block.id);
                        setSidebarMode('comments');
                      }}
                      className={[
                        'relative group rounded-lg transition-all duration-200 cursor-pointer',
                        isSelected 
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm' 
                          : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card'
                      ].join(' ')}
                    >
                      {/* Badge del bloque */}
                      <div className={[
                        'absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200',
                        isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted'
                      ].join(' ')}>
                         #{(block.sort_order ?? '?') as any}
                      </div>

                      {/* Cabecera del bloque: Título y botones de acción */}
                      <div className="flex items-center gap-3 mb-4">
                        <h3 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          {(block.title ? String(block.title) : 'Bloque sin título') as any}
                        </h3>
                        
                        <div className="flex items-center gap-2">
                          {/* Botón de Descripción (Pill) */}
                          <button
                            type="button"
                            onClick={(e) => { 
                              e.stopPropagation(); 
                              setSelectedBlockId(block.id);
                              setSidebarMode('info'); 
                            }}
                            className={[
                              "shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider",
                              sidebarMode === 'info' && isSelected
                                ? "border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm"
                                : "border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5"
                            ].join(' ')}
                            title="Ver descripción del bloque"
                          >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Info</span>
                          </button>

                          {/* Botón de Mensaje (Pill) */}
                          <button
                            type="button"
                            onClick={(e) => { 
                              e.stopPropagation(); 
                              setSelectedBlockId(block.id);
                              setSidebarMode('comments'); 
                            }}
                            className={[
                              "shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider",
                              sidebarMode === 'comments' && isSelected
                                ? "border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm"
                                : "border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5"
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

                      <div className="prose prose-sm dark:prose-invert max-w-none">
                        {parsed ? (
                          <BlockContentHtml content={parsed as any} />
                        ) : typeof content === 'string' && content.trim() !== '' ? (
                          <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed">
                            {content}
                          </p>
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

        {/* Panel derecho Unificado (Comentarios o Info) */}
        <aside 
          className={[
            'transition-all duration-400 ease-out z-30 flex flex-col overflow-hidden',
            sidebarMode ? 'w-[45%] max-w-[850px] min-w-[500px]' : 'w-0'
          ].join(' ')}
        >
          <div className="flex-1 overflow-y-auto p-8 scroll-smooth custom-scrollbar w-full relative">
            <article
              className="mx-auto bg-white dark:bg-ui-card shadow-2xl rounded-sm transition-all duration-300 flex flex-col h-full"
              style={{ padding: '60px 70px' }}
            >
              {selectedBlock ? (
                <>
                  {/* Header de la Sidebar */}
                  <div className="shrink-0 mb-8">
                    <div className="flex items-center justify-between mb-8">
                      <div className="min-w-0">
                        <h3 className="text-2xl font-black text-text-primary dark:text-text-dark-primary leading-tight">
                          {sidebarMode === 'info' ? 'Información del Bloque' : 'Comentarios de Revisión'}
                        </h3>
                        <p className="text-xs uppercase tracking-widest text-text-muted mt-2 font-bold">
                          Bloque #{(selectedBlock.sort_order ?? '?') as any}
                        </p>
                      </div>
                      <button 
                        onClick={() => { setSidebarMode(null); }}
                        className="group w-10 h-10 -mr-4 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-all shrink-0"
                        title="Cerrar panel"
                      >
                        <span className="block text-xl group-hover:rotate-90 transition-transform">✕</span>
                      </button>
                    </div>

                    {/* Tabs Minimalistas (Underline style) */}
                    <div className="flex gap-8 border-b border-ui-border dark:border-ui-dark-border pb-px">
                      <button
                        onClick={() => setSidebarMode('comments')}
                        className={[
                          'pb-4 text-xs font-bold uppercase tracking-widest transition-all relative',
                          sidebarMode === 'comments' 
                            ? 'text-odoo-purple after:absolute after:bottom-0 after:left-0 after:w-full after:h-[3px] after:bg-odoo-purple' 
                            : 'text-text-muted hover:text-text-primary'
                        ].join(' ')}
                      >
                        Mensajes
                      </button>
                      <button
                        onClick={() => setSidebarMode('info')}
                        className={[
                          'pb-4 text-xs font-bold uppercase tracking-widest transition-all relative',
                          sidebarMode === 'info' 
                            ? 'text-odoo-purple after:absolute after:bottom-0 after:left-0 after:w-full after:h-[3px] after:bg-odoo-purple' 
                            : 'text-text-muted hover:text-text-primary'
                        ].join(' ')}
                      >
                        Descripción
                      </button>
                    </div>
                  </div>

                  {/* Contenido de la Sidebar */}
                  {sidebarMode === 'info' ? (
                    <div className="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                      <InfoBlockDescription description={selectedBlock.description} />
                    </div>
                  ) : (
                    <div className="flex flex-col flex-1 overflow-hidden">
                      <div className="flex-1 overflow-y-auto pr-4 space-y-6 custom-scrollbar pb-6">
                        {blockComments.length === 0 ? (
                          <div className="flex flex-col items-center justify-center h-40 text-center opacity-40 mt-10">
                            <svg className="w-12 h-12 mb-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <p className="text-sm font-medium text-text-muted leading-relaxed">
                              No hay mensajes en este bloque.
                            </p>
                          </div>
                        ) : (
                          <div className="space-y-8 mt-2">
                            {blockComments.filter(c => !c.parent_id).map((comment) => {
                              const replies = comments.filter(r => r.parent_id === comment.id);
                              return (
                                <div key={comment.id} className="space-y-4">
                                  <div className="group relative pl-6 animate-in fade-in slide-in-from-right-2">
                                    <div className="absolute left-0 top-0 bottom-0 w-1 bg-ui-border dark:bg-ui-dark-border group-hover:bg-odoo-purple/40 transition-colors rounded-full" />
                                    <div className="flex items-center justify-between mb-2">
                                      <span className="text-sm font-black text-text-primary dark:text-text-dark-primary">
                                        {comment.author?.name || 'Usuario'}
                                      </span>
                                      <span className="text-xs text-text-muted font-bold uppercase tracking-wider opacity-70">
                                        {new Date(comment.created_at).toLocaleDateString()}
                                      </span>
                                    </div>
                                    <div className="text-sm text-text-primary dark:text-text-dark-primary leading-relaxed bg-ui-body/30 dark:bg-ui-dark-bg p-5 rounded-xl border border-ui-border/50 dark:border-ui-dark-border/50 shadow-sm">
                                      {comment.body}
                                    </div>
                                  </div>

                                  {/* Replies */}
                                  {replies.length > 0 && (
                                    <div className="ml-12 space-y-4">
                                      {replies.map(r => (
                                        <div key={r.id} className="relative pl-4 border-l-2 border-ui-border/30">
                                          <div className="flex items-center justify-between mb-1">
                                            <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                                              {r.author?.name || 'Usuario'}
                                            </span>
                                            <span className="text-xs text-text-muted font-bold uppercase tracking-widest">
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

                      {!isAlreadyValidated && !isReadOnly ? (
                        <div className="mt-4 pt-6 border-t border-ui-border dark:border-ui-dark-border shrink-0">
                          <textarea
                            value={newCommentBody}
                            onChange={(e) => setNewCommentBody(e.target.value)}
                            placeholder="Escribe un mensaje de revisión..."
                            className="w-full h-32 p-4 text-sm rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
                          />
                          <div className="mt-4 flex justify-end">
                            <Button 
                              variant="primary" 
                              size="md" 
                              className="text-xs font-black uppercase tracking-widest px-8 py-2.5"
                              onClick={handleAddComment}
                              loading={commentLoading}
                              disabled={!newCommentBody.trim() || commentLoading}
                            >
                              Enviar mensaje
                            </Button>
                          </div>
                        </div>
                      ) : (
                        <div className="mt-4 p-6 border border-ui-border dark:border-ui-dark-border rounded-xl bg-ui-body/5 text-center shrink-0">
                          <p className="text-sm text-text-muted italic font-medium">
                            No puedes añadir más mensajes porque ya has finalizado tu validación.
                          </p>
                        </div>
                      )}
                    </div>
                  )}
                </>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-center p-8 space-y-6 opacity-30">
                  <span className="text-6xl grayscale">📄</span>
                  <p className="text-base font-medium text-text-muted max-w-sm leading-relaxed">
                    Selecciona un bloque en el documento de la izquierda para ver su descripción o sus mensajes.
                  </p>
                </div>
              )}
            </article>
          </div>
        </aside>
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
