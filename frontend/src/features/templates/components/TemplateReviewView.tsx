import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { Button, ConfirmDialog } from '../../../ui';
import { approveTemplateReview, rejectTemplateReview } from '../../../api/templates';
import { apiFetchJson } from '../../../api/http';

type Props = {
  template: Template;
};

type TemplateComment = {
  id: string;
  template_block_id: string | null;
  author_id: string;
  body: string;
  created_at: string;
};

function InfoBlockDescription({ description }: { description: unknown }) {
  if (!description) return null;
  
  let parsed: unknown[] | null = null;
  
  if (Array.isArray(description)) {
    parsed = description;
  } else if (typeof description === 'string') {
    try {
      const p: unknown = JSON.parse(description);
      if (Array.isArray(p)) {
        parsed = p;
      } else if (p && typeof p === 'object') {
        parsed = [p]; // Wrap single block object in array
      }
    } catch { 
      // Si falla el parseo, lo tratamos como texto plano más abajo
    }
  } else if (description && typeof description === 'object') {
    parsed = [description];
  }

  if (parsed) {
    return (
      <div className="flex-1 overflow-y-auto p-5 custom-scrollbar prose prose-sm dark:prose-invert max-w-none">
        <BlockContentHtml content={parsed} />
      </div>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto p-5 custom-scrollbar">
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
        {String(description)}
      </p>
    </div>
  );
}

export function TemplateReviewView({ template }: Props) {
  const navigate = useNavigate();
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

  // Estado de la barra lateral: 'comments' | 'info' | null
  const [sidebarMode, setSidebarMode] = useState<'comments' | 'info' | null>(null);

  useEffect(() => {
    // Cargar comentarios iniciales
    void loadComments();
  }, [template.id]);

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
          template_block_id: selectedBlockId,
          type: 'review'
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
      navigate('/templates');
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
      navigate('/templates');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al rechazar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const selectedBlock = blocks.find(b => b.id === selectedBlockId);
  const blockComments = comments.filter(c => c.template_block_id === selectedBlockId);

  return (
    <div className="flex flex-col h-full bg-[#ddd9d3] dark:bg-ui-dark-bg/50">
      {/* Header con acciones */}
      <div className="shrink-0 px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shadow-md z-20">
        <div className="flex items-center gap-3">
          <button
            onClick={() => navigate('/templates')}
            className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-secondary transition-colors"
          >
            ←
          </button>
          <div>
            <h2 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
              Validación de Plantilla
            </h2>
            <p className="text-[10px] text-text-muted uppercase tracking-widest font-black truncate max-w-[200px]">
              {template.name}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outlineWarning"
            size="sm"
            onClick={handleRejectClick}
            disabled={actionLoading}
            loading={actionLoading}
            className="text-[10px] font-black uppercase tracking-wider"
          >
            Rechazar validación
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={handleApprove}
            disabled={actionLoading}
            loading={actionLoading}
            className="text-[10px] font-black uppercase tracking-wider px-6"
          >
            Validar y Aprobar
          </Button>
        </div>
      </div>

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
              <div className="flex flex-wrap gap-4 text-[10px] font-bold uppercase tracking-widest text-text-muted">
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
                  const hasComments = comments.some(c => c.template_block_id === block.id);

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
                      {/* Indicador de comentarios */}
                      {hasComments && (
                        <div className="absolute -right-10 top-0 w-6 h-6 bg-odoo-purple text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-sm">
                          {comments.filter(c => c.template_block_id === block.id).length}
                        </div>
                      )}

                      {/* Badge del bloque */}
                      <div className={[
                        'absolute -left-12 top-0 text-[10px] font-black uppercase tracking-tighter transition-opacity duration-200',
                        isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted'
                      ].join(' ')}>
                         #{block.sort_order ?? '?'}
                      </div>

                      {/* Título del bloque (opcional) + botón de descripción */}
                      {(block.title || block.description) && (
                        <div className="flex items-center gap-2 mb-3">
                          <h3 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                            {block.title ? String(block.title) : ''}
                          </h3>
                          {block.description && (
                            <div
                              role="button"
                              onClick={(e) => { 
                                e.stopPropagation(); 
                                setSelectedBlockId(block.id);
                                setSidebarMode(prev => (prev === 'info' && selectedBlockId === block.id) ? 'comments' : 'info'); 
                              }}
                              className="shrink-0 w-[18px] h-[18px] rounded-full border border-text-muted flex items-center justify-center text-[10px] font-black text-text-muted opacity-50 hover:opacity-100 hover:text-odoo-purple hover:border-odoo-purple transition-all cursor-pointer"
                              aria-label="Ver descripción del bloque"
                            >
                              i
                            </div>
                          )}
                        </div>
                      )}

                      {/* Contenido renderizado */}
                      <div className="prose prose-sm dark:prose-invert max-w-none">
                        {parsed ? (
                          <BlockContentHtml content={parsed} />
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

        {/* Sidebar Unificada (Comentarios o Info) */}
        <aside 
          className={[
            'w-[350px] bg-white dark:bg-ui-dark-card border-l border-ui-border dark:border-ui-dark-border flex flex-col shadow-xl transition-transform duration-300 z-10',
            sidebarMode ? 'translate-x-0' : 'translate-x-full'
          ].join(' ')}
        >
          {selectedBlock ? (
            <>
              {/* Header de la Sidebar */}
              <div className="px-5 py-4 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between bg-ui-body/30 dark:bg-ui-dark-bg/30">
                <div className="min-w-0">
                  <h3 className="text-xs font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary truncate">
                    {sidebarMode === 'info' ? 'Descripción del bloque' : 'Comentarios'}
                  </h3>
                  <p className="text-[10px] text-text-muted truncate">
                    Bloque: {selectedBlock.title ? String(selectedBlock.title) : `#${selectedBlock.sort_order}`}
                  </p>
                </div>
                <div className="flex items-center gap-1">
                  {/* Selector de modo */}
                  <button 
                    onClick={() => setSidebarMode(sidebarMode === 'info' ? 'comments' : 'info')}
                    className="p-1.5 rounded-md hover:bg-ui-border dark:hover:bg-ui-dark-border text-text-muted transition-colors text-[10px] font-bold uppercase tracking-tighter"
                    title={sidebarMode === 'info' ? 'Ver comentarios' : 'Ver descripción'}
                  >
                    {sidebarMode === 'info' ? '💬' : 'ℹ️'}
                  </button>
                  <button 
                    onClick={() => { setSidebarMode(null); }}
                    className="p-1.5 rounded-md hover:bg-ui-border dark:hover:bg-ui-dark-border flex items-center justify-center text-text-muted transition-colors"
                  >
                    ✕
                  </button>
                </div>
              </div>

              {/* Contenido de la Sidebar */}
              {sidebarMode === 'info' ? (
                <InfoBlockDescription description={selectedBlock.description} />
              ) : (
                <>
                  <div className="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar">
                    {blockComments.length === 0 ? (
                      <div className="flex flex-col items-center justify-center h-40 text-center space-y-3 opacity-40">
                        <span className="text-3xl">💬</span>
                        <p className="text-xs font-medium text-text-muted">
                          No hay comentarios en este bloque.<br/>Haz una sugerencia o corrección.
                        </p>
                      </div>
                    ) : (
                      blockComments.map((comment) => (
                        <div key={comment.id} className="bg-ui-body/40 dark:bg-ui-dark-bg/40 p-3 rounded-lg border border-ui-border dark:border-ui-dark-border text-xs animate-in fade-in">
                          <p className="text-text-primary dark:text-text-dark-primary leading-relaxed whitespace-pre-wrap">
                            {comment.body}
                          </p>
                          <div className="mt-2 text-[9px] font-bold text-text-muted uppercase tracking-tight">
                            {new Date(comment.created_at).toLocaleString()}
                          </div>
                        </div>
                      ))
                    )}
                  </div>

                  <div className="p-4 border-t border-ui-border dark:border-ui-dark-border bg-ui-body/10">
                    <textarea
                      value={newCommentBody}
                      onChange={(e) => setNewCommentBody(e.target.value)}
                      placeholder="Añade un comentario de revisión..."
                      className="w-full h-24 p-3 text-xs rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg focus:ring-1 focus:ring-odoo-purple focus:border-odoo-purple outline-none transition-all resize-none"
                    />
                    <div className="mt-3 flex justify-end">
                      <Button 
                        variant="primary" 
                        size="sm" 
                        className="text-[10px] font-bold uppercase"
                        onClick={handleAddComment}
                        loading={commentLoading}
                        disabled={!newCommentBody.trim() || commentLoading}
                      >
                        Enviar comentario
                      </Button>
                    </div>
                  </div>
                </>
              )}
            </>
          ) : (
            <div className="flex flex-col items-center justify-center h-full text-center p-8 space-y-4 opacity-30">
              <span className="text-5xl">📄</span>
              <p className="text-sm font-medium text-text-muted">
                Selecciona un bloque en el documento para ver comentarios o descripción.
              </p>
            </div>
          )}
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
