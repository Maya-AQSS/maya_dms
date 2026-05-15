import { useState, useEffect, useRef, useMemo, type RefObject } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { Button, ConfirmDialog } from '@maya/shared-ui-react';
import { approveTemplateReview, rejectTemplateReview, fetchTemplateVersion } from '../../../api/templates';
import type { TemplateVersionDetail } from '../../../api/templates';
import { fetchProcesses } from '../../../api/processes';
import { apiFetchJson } from '../../../api/http';
import { useAuth } from '@maya/shared-auth-react';
import { useUserProfile } from '../../user-profile';
import type { Process } from '../../../types/processes';
import { BlockCommentsCard, ViewCardHeader } from './BlockCommentsCard';
import type { BlockComment, CommentMode } from './BlockCommentsCard';
import { computeChangedBlocks } from '../../documents/components/DocumentDiffModal';
import { DocumentDiffPanel } from '../../documents/components/DocumentDiffPanel';
import type { DocumentDisplayBlock } from '../../../types/documents';

type Props = { template: Template };

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

// Sidebar width percentage.
const SIDEBAR_WIDTH = '35%';

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



// ─── Main component ───────────────────────────────────────────────────────────

export function TemplateReviewView({ template }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuth();
  const { profile } = useUserProfile();
  const { blocks } = useTemplateBlocks(template.id);

  const [activeView, setActiveView] = useState<ActiveView | null>(null);
  const [diffBlockId, setDiffBlockId] = useState<string | null>(null);
  const [publishedVersion, setPublishedVersion] = useState<TemplateVersionDetail | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [comments, setComments] = useState<BlockComment[]>([]);
  const [commentingOpen, setCommentingOpen] = useState(true);
  const [commentLoading, setCommentLoading] = useState(false);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);
  const [processLabel, setProcessLabel] = useState<string | null>(null);

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
  // - creator-edit: creator can always respond, even in_review
  // - creator-readonly: fallback
  const commentMode: CommentMode = (() => {
    if (isReviewer && template.status === 'in_review' && myReview?.status === 'pending') return 'validator';
    if (isCreator) return 'creator-edit';
    return 'creator-readonly';
  })();

  const remainingReviewers = template.reviewers?.filter(r => r.status === 'pending') || [];
  const backTo = (location.state as { backTo?: string } | null)?.backTo ?? '/dashboard';

  const changedBlockIds = useMemo((): Set<string> => {
    const history = template.review_history;
    if (!Array.isArray(history) || history.length < 2) return new Set();
    const prev = history[history.length - 2];
    const curr = history[history.length - 1];
    if (!prev || !curr) return new Set();
    const prevMap = new Map(prev.blocks.map((b) => [b.id, JSON.stringify(b.default_content)]));
    const changed = new Set<string>();
    for (const block of curr.blocks) {
      const prevContent = prevMap.get(block.id);
      if (prevContent === undefined || prevContent !== JSON.stringify(block.default_content)) {
        changed.add(block.id);
      }
    }
    return changed;
  }, [template.review_history]);

  const goBack = () => {
    if (window.history.length > 1) { navigate(-1); return; }
    navigate(backTo);
  };

  useEffect(() => { void loadComments(); }, [template.id]);

  const hasPreviousSubmission = Array.isArray(template.blocks_at_previous_submission) && template.blocks_at_previous_submission.length > 0;

  useEffect(() => {
    if (hasPreviousSubmission) return;
    if (!template.latest_published_version_id) return;
    void fetchTemplateVersion(template.latest_published_version_id)
      .then(setPublishedVersion)
      .catch(() => {});
  }, [hasPreviousSubmission, template.latest_published_version_id]);

  const diffBlocks = useMemo((): DocumentDisplayBlock[] => {
    const baselineBlocks = hasPreviousSubmission
      ? template.blocks_at_previous_submission!
      : publishedVersion?.blocks_snapshot ?? null;
    if (!baselineBlocks) return [];
    return blocks.map((block) => {
      const baseline = baselineBlocks.find((pb) => pb.id === block.id);
      return {
        template_block_id: block.id,
        document_block_id: null,
        type: block.type,
        title: block.title,
        default_content: (baseline?.default_content ?? null) as unknown,
        block_state: block.block_state,
        mandatory: block.mandatory,
        sort_order: block.sort_order,
        content: block.default_content,
        is_filled: true,
      };
    });
  }, [blocks, hasPreviousSubmission, template.blocks_at_previous_submission, publishedVersion]);

  const changedTemplateDiffBlocks = useMemo(() => computeChangedBlocks(diffBlocks), [diffBlocks]);
  const changedTemplateBlockIds = useMemo(
    () => new Set(changedTemplateDiffBlocks.map((b) => b.template_block_id)),
    [changedTemplateDiffBlocks],
  );

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

  // We removed the connector line and absolute padding top because we are using a sticky sidebar now.

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

  const handleSendMessage = async (parentId: string | null, body: string) => {
    setCommentLoading(true);
    try {
      const parent = parentId ? comments.find(c => c.id === parentId) : null;
      const res = await apiFetchJson<{ data: BlockComment }>(`templates/${template.id}/comments`, {
        method: 'POST',
        body: { 
          body, 
          parent_id: parentId, 
          blockable_id: activeView?.blockId || parent?.blockable_id || null 
        },
      });
      setComments(prev => [...prev, res.data]);
    } catch (e) {
      setError('No se pudo guardar el comentario.');
    } finally {
      setCommentLoading(false);
    }
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
      navigate(backTo);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Error al aprobar la plantilla');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRejectClick = () => {
    const myComments = comments.filter(
      c => String(c.author_id) === String(currentUserId),
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

  const selectedBlock = blocks.find((b) => b.id === activeView?.blockId);

  // Sub-component for Info sidebar (placed inside to share 'blocks' scope)
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
      <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300">
        <ViewCardHeader
          blockSortOrder={(blocks.findIndex((b) => b.id === selectedBlock.id) + 1) || '?'}
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
  const getCommentsForBlock = (bid: string | null, allComments: BlockComment[]) => {
    if (!bid) return [];
    
    // Recursive function to get all replies to a comment
    const getReplies = (parentId: string): BlockComment[] => {
      const replies = allComments.filter(c => c.parent_id === parentId);
      return [...replies, ...replies.flatMap(r => getReplies(r.id))];
    };

    // Get all root comments for this block
    const roots = allComments.filter(c => c.blockable_id === bid && !c.parent_id);
    
    // Combine roots and all their recursive replies
    const allForBlock = [...roots, ...roots.flatMap(r => getReplies(r.id))];

    // Deduplicate by ID to be safe
    const uniqueIds = Array.from(new Set(allForBlock.map(c => c.id)));
    return uniqueIds.map(id => allForBlock.find(c => c.id === id) as BlockComment);
  };
  const blockComments = getCommentsForBlock(activeView?.blockId ?? null, comments);

  return (
    <PaperPreviewLayout
      title={template.name}
      onBack={() => navigate('/templates')}
      backLabel="Volver a plantillas"
      metaInfo={
        <div className="flex flex-wrap gap-4 text-xs font-bold uppercase tracking-widest text-text-muted justify-center">
          <span>{visibilityLabel(template.visibility_level)}</span>
          {template.study_id && <span>• {String(template.study_id)}</span>}
          {template.module_id && <span>• {String(template.module_id)}</span>}
          {processLabel && <span>• Proceso: {processLabel}</span>}
        </div>
      }
      actions={
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
      }
      sidebar={
        diffBlockId !== null && !activeView
          ? (
            <DocumentDiffPanel
              blocks={diffBlocks.filter((b) => b.template_block_id === diffBlockId)}
              onClose={() => setDiffBlockId(null)}
            />
          )
          : activeView && selectedBlock
            ? (
              activeView.mode === 'comments' ? (
                <BlockCommentsCard
                  mode={commentMode}
                  blockSortOrder={(blocks.findIndex((b: any) => b.id === selectedBlock.id) + 1) || '?'}
                  blockComments={getCommentsForBlock(selectedBlock.id, comments)}
                  allComments={comments}
                  onClose={closeView}
                  onSendMessage={handleSendMessage}
                  commentLoading={actionLoading}
                  canAddComments={template.status !== 'published'}
                />
              ) : (
                <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
                  <ViewCardHeader
                    blockSortOrder={(blocks.findIndex((b: any) => b.id === selectedBlock.id) + 1) || '?'}
                    title="Descripción del Bloque"
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
      {/* Blocks list (article content) */}
      <div className="space-y-12">
        {blocks.length === 0 ? (
          <div className="py-20 text-center border-2 border-dashed border-ui-border dark:border-ui-dark-border rounded-xl">
            <p className="text-sm text-text-muted italic">Esta plantilla no tiene bloques configurados.</p>
          </div>
        ) : (
          blocks.map((block) => {
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
                className={[
                  'relative group rounded-lg transition-all duration-200 cursor-pointer',
                  isSelected
                    ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                    : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                ].join(' ')}
                onClick={() => openView(block.id, 'comments')}
              >
                <div className="flex items-center gap-3 mb-4">
                  <h4 className="flex-1 text-sm font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                    Bloque {(blocks.findIndex((b: any) => b.id === block.id) + 1)}: {block.title || 'Sin título'}
                  </h4>
                  <div className="flex items-center gap-2">
                    {block.description && (
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
                        title="Ver descripción"
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
                          {changedBlockIds.has(block.id) && (
                            <span
                              className="shrink-0 text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-warning/10 text-warning-dark dark:text-warning-light border border-warning/20"
                              title="Este bloque fue modificado desde la última sesión de revisión"
                            >
                              Modificado
                            </span>
                          )}

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
                {nodes.length > 0 ? (
                  <BlockContentHtml content={nodes} />
                ) : (
                  <p className="text-sm text-text-muted italic">Bloque sin contenido.</p>
                )}
              </section>
            );
          })
        )}
      </div>

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
    </PaperPreviewLayout>
  );
}
