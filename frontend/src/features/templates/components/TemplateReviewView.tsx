import { useState, useRef, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import type { Template } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { Button, ConfirmDialog } from '@maya/shared-ui-react';
import { createDataHook, useAuth } from '@maya/shared-auth-react';
import { approveTemplateReview, rejectTemplateReview, fetchTemplateVersion } from '../../../api/templates';
import type { TemplateVersionDetail } from '../../../api/templates';
import { fetchProcesses } from '../../../api/processes';
import { apiFetchJson } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import type { Process } from '../../../types/processes';
import { BlockCommentsCard, ViewCardHeader } from './BlockCommentsCard';
import type { BlockComment, CommentMode } from './BlockCommentsCard';
import { computeChangedBlocks } from '../../documents/components/DocumentDiffModal';
import { getCommentsForBlock } from '../../../utils/blockComments';
import { DocumentDiffPanel } from '../../documents/components/DocumentDiffPanel';
import type { DocumentDisplayBlock } from '../../../types/documents';

interface TemplateCommentsResponse {
  data: BlockComment[];
  meta?: { commenting_open?: boolean };
}

const templateCommentsKey = (templateId: string) => ['templates', templateId, 'comments'] as const;

const useTemplateCommentsQuery = createDataHook<string, TemplateCommentsResponse>({
  queryKey: (templateId) => templateCommentsKey(templateId),
  fetcher: (templateId) => apiFetchJson<TemplateCommentsResponse>(`templates/${templateId}/comments`),
  defaultOptions: { staleTime: 0 },
});

const useTemplateVersionQuery = createDataHook<string, TemplateVersionDetail>({
  queryKey: (versionId) => ['template-version', versionId],
  fetcher: (versionId) => fetchTemplateVersion(versionId),
  defaultOptions: { staleTime: 60_000 },
});

const useProcessesQuery = createDataHook<void, { data: Process[] }>({
  queryKey: () => ['processes'],
  fetcher: () => fetchProcesses(),
  defaultOptions: { staleTime: 60_000 },
});

type Props = { template: Template };

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

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
  const backTo = (location.state as { backTo?: string } | null)?.backTo ?? '/dashboard';
  const { user } = useAuth();
  const { profile } = useUserProfile();
  const { blocks } = useTemplateBlocks(template.id);
  const queryClient = useQueryClient();

  const [activeView, setActiveView] = useState<ActiveView | null>(null);
  const [diffBlockId, setDiffBlockId] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  // Error state is tracked for telemetry but not surfaced in this view yet.
  const [, setError] = useState<string | null>(null);

  const [, setCommentLoading] = useState(false);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);

  const blockRefs = useRef<Map<string, HTMLElement>>(new Map());

  const currentUserId = user?.sub ?? (user as { id?: string } | null | undefined)?.id;
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


  // Comentarios del template (TanStack Query).
  const commentsQuery = useTemplateCommentsQuery(template.id);
  const comments = commentsQuery.data?.data ?? [];

  const hasPreviousSubmission = Array.isArray(template.blocks_at_previous_submission) && template.blocks_at_previous_submission.length > 0;

  // Versión publicada (solo si no hay snapshot previo embebido).
  const publishedVersionQuery = useTemplateVersionQuery(
    template.latest_published_version_id ?? '',
    { enabled: !hasPreviousSubmission && !!template.latest_published_version_id },
  );
  const publishedVersion = publishedVersionQuery.data ?? null;

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

  // Etiqueta del proceso (TanStack Query, caché compartida con ProcesosPage).
  const processesQuery = useProcessesQuery(undefined, { enabled: !!template.process_id });
  const processLabel = useMemo<string | null>(() => {
    if (!template.process_id) return null;
    const process = processesQuery.data?.data.find((p) => p.id === template.process_id) ?? null;
    return process ? `Proceso: ${process.code} — ${process.name}` : null;
  }, [template.process_id, processesQuery.data]);

  // We removed the connector line and absolute padding top because we are using a sticky sidebar now.

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
      // Append optimista al cache de la query (sin refetch completo).
      queryClient.setQueryData<TemplateCommentsResponse>(
        templateCommentsKey(template.id),
        (current) => {
          if (!current) return { data: [res.data] };
          return { ...current, data: [...current.data, res.data] };
        },
      );
    } catch {
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
                  blockSortOrder={(blocks.findIndex((b) => b.id === selectedBlock.id) + 1) || '?'}
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
                    blockSortOrder={(blocks.findIndex((b) => b.id === selectedBlock.id) + 1) || '?'}
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
                    Bloque {(blocks.findIndex((b) => b.id === block.id) + 1)}: {block.title || 'Sin título'}
                  </h4>
                  <div className="flex items-center gap-2">
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
                        title="Ver descripción"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Info</span>
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
                          : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                      ].join(' ')}
                    >
                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                      </svg>
                      <span>Mensajes</span>
                      {getCommentsForBlock(block.id, comments).length > 0 && (
                        <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-[10px] leading-none font-bold">
                          {getCommentsForBlock(block.id, comments).length}
                        </span>
                      )}
                    </button>
                    {changedTemplateBlockIds.has(block.id) && (
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
                            : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-teal hover:border-odoo-teal/50 hover:bg-odoo-teal/5',
                        ].join(' ')}
                        title="Ver cambios respecto a la versión publicada"
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Ver cambios</span>
                      </button>
                    )}
                  </div>
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
