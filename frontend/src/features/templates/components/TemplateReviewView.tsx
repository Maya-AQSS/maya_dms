import { useState, useRef, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import type { Template, ReviewCycleSnapshot, ReviewCycleBlock } from '../../../types/templates';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { Button, ConfirmDialog } from '@maya/shared-ui-react';
import { useAuth } from '@maya/shared-auth-react';
import { approveTemplateReview, rejectTemplateReview } from '../../../api/templates';
import { apiFetchJson } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import {
  useTemplateCommentsQuery,
  templateCommentsKey,
  type TemplateCommentsResponse,
} from '../hooks/useTemplateComments';
import { BlockCommentsCard, ViewCardHeader } from './BlockCommentsCard';
import type { BlockComment, CommentMode } from './BlockCommentsCard';
import { getCommentsForBlock } from '../../../utils/blockComments';

type Props = { template: Template };

type ActiveView = { blockId: string; mode: 'comments' | 'info' };

// ─── Diff utilities (same logic as DocumentDiffPanel) ─────────────────────────

function extractBlockText(block: unknown): string {
  if (!block || typeof block !== 'object') return '';
  const b = block as Record<string, unknown>;
  const content = Array.isArray(b.content) ? b.content : [];
  const inline = content
    .map((c: unknown) => {
      if (!c || typeof c !== 'object') return '';
      const item = c as Record<string, unknown>;
      if (item.type === 'text') return String(item.text ?? '');
      if (item.type === 'link') {
        const lc = Array.isArray(item.content) ? item.content : [];
        return lc.map((x: unknown) => String((x as Record<string, unknown>).text ?? '')).join('');
      }
      return '';
    })
    .join('');
  const type = String(b.type ?? '');
  const props = (b.props ?? {}) as Record<string, unknown>;
  let prefix = '';
  if (type === 'heading') prefix = '#'.repeat(Number(props.level ?? 1)) + ' ';
  else if (type === 'bulletListItem') prefix = '• ';
  else if (type === 'numberedListItem') prefix = '1. ';
  return prefix + inline;
}

function extractTextLines(content: unknown): string[] {
  const blocks = normalizeBlockContentForEditor(content);
  if (!Array.isArray(blocks)) return [];
  const lines: string[] = [];
  for (const block of blocks) {
    const text = extractBlockText(block);
    if (text.trim()) lines.push(text);
    const b = block as Record<string, unknown>;
    const children = Array.isArray(b.children) ? b.children : [];
    for (const child of children) {
      const ct = extractBlockText(child);
      if (ct.trim()) lines.push('  ' + ct);
    }
  }
  return lines;
}

type DiffLine = { type: 'removed' | 'added' | 'unchanged'; text: string };

function computeLineDiff(original: string[], modified: string[]): DiffLine[] {
  const m = original.length;
  const n = modified.length;
  const dp = Array.from({ length: m + 1 }, () => new Array<number>(n + 1).fill(0));
  for (let i = 1; i <= m; i++)
    for (let j = 1; j <= n; j++)
      dp[i][j] =
        original[i - 1] === modified[j - 1]
          ? dp[i - 1][j - 1] + 1
          : Math.max(dp[i - 1][j], dp[i][j - 1]);
  const result: DiffLine[] = [];
  let i = m;
  let j = n;
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && original[i - 1] === modified[j - 1]) {
      result.unshift({ type: 'unchanged', text: original[i - 1] });
      i--;
      j--;
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      result.unshift({ type: 'added', text: modified[j - 1] });
      j--;
    } else {
      result.unshift({ type: 'removed', text: original[i - 1] });
      i--;
    }
  }
  return result;
}

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

type CycleEntry = {
  cycle: number;
  submitted_at: string;
  block: ReviewCycleBlock;
  diff: DiffLine[];
};

type HistoryPanelProps = {
  blockId: string;
  blockNumber: number | string;
  history: ReviewCycleSnapshot[];
  onClose: () => void;
};

function TemplateBlockHistoryPanel({ blockId, blockNumber, history, onClose }: HistoryPanelProps) {
  const [ascending, setAscending] = useState(false);

  const entriesChron = useMemo((): CycleEntry[] => {
    return history
      .map((cycle, i) => {
        const block = cycle.blocks.find((b) => b.id === blockId) ?? null;
        if (!block) return null;
        const prevBlock = i > 0 ? (history[i - 1].blocks.find((b) => b.id === blockId) ?? null) : null;
        const currentLines = extractTextLines(block.default_content);
        const prevLines = prevBlock ? extractTextLines(prevBlock.default_content) : [];
        const diff = prevBlock
          ? computeLineDiff(prevLines, currentLines).filter((l) => l.type !== 'unchanged')
          : currentLines.map((text) => ({ type: 'added' as const, text }));
        return { cycle: cycle.cycle, submitted_at: cycle.submitted_at, block, diff };
      })
      .filter((e): e is CycleEntry => e !== null && e.diff.length > 0);
  }, [history, blockId]);

  const entries = useMemo(
    () => (ascending ? entriesChron : [...entriesChron].reverse()),
    [entriesChron, ascending],
  );

  return (
    <div className="flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center shrink-0 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-4 py-3 gap-2">
        <span className="text-[10px] font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary flex-1">
          ⎇ Bloque {blockNumber} · Historial de cambios
        </span>
        <button
          type="button"
          onClick={() => setAscending((v) => !v)}
          className="flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-text-muted hover:text-odoo-teal transition-colors cursor-pointer"
          title={ascending ? 'Mostrar más recientes primero' : 'Mostrar más antiguos primero'}
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
          aria-label="Cerrar panel"
          className="w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-colors text-sm shrink-0"
        >
          ✕
        </button>
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto divide-y divide-ui-border dark:divide-ui-dark-border">
        {entries.length === 0 ? (
          <p className="py-8 text-center text-xs text-text-muted dark:text-text-dark-muted italic">
            Este bloque no tiene cambios registrados.
          </p>
        ) : (
          entries.map((entry) => (
            <div key={entry.cycle} className="px-4 py-3">
              {/* Cycle header */}
              <div className="flex items-center justify-between mb-2">
                <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                  Revisión {entry.cycle}
                </p>
                <span className="text-[10px] text-text-muted dark:text-text-dark-muted tabular-nums">
                  {new Date(entry.submitted_at).toLocaleString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
              {/* Git-diff lines */}
              <div className="rounded overflow-hidden border border-ui-border dark:border-ui-dark-border text-[11px] font-mono">
                {entry.diff.length === 0 ? (
                  <p className="px-2 py-1 text-text-muted italic text-[11px]">
                    Sin cambios respecto al envío anterior.
                  </p>
                ) : (
                  entry.diff.map((line, li) => (
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
                  ))
                )}
              </div>
            </div>
          ))
        )}
      </div>

      {/* Footer legend */}
      {entries.length > 0 && (
        <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border px-4 py-2 flex items-center gap-3 text-[10px] text-text-muted dark:text-text-dark-muted bg-ui-body/30 dark:bg-ui-dark-bg/30">
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-danger/25 border border-danger/40" />
            Eliminado
          </span>
          <span className="flex items-center gap-1.5">
            <span className="inline-block w-3 h-3 rounded-sm bg-success/25 border border-success/40" />
            Añadido
          </span>
          <span className="ml-auto font-semibold">
            {entries.length} revisión{entries.length !== 1 ? 'es' : ''}
          </span>
        </div>
      )}
    </div>
  );
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
  // Error state tracked for telemetry but not surfaced in this view yet.
  const [, setError] = useState<string | null>(null);

  const [, setCommentLoading] = useState(false);

  const [showRejectModal, setShowRejectModal] = useState(false);
  const [showNoCommentsWarning, setShowNoCommentsWarning] = useState(false);

  const blockRefs = useRef<Map<string, HTMLElement>>(new Map());

  const currentUserId = user?.sub ?? (user as { id?: string } | null | undefined)?.id;
  const myReview = template.reviewers?.find(r => String(r.user_id) === String(currentUserId));
  const isReviewer = !!myReview;
  const isCreator = !!profile?.id && template.created_by === profile.id;

  const commentMode: CommentMode = (() => {
    if (isReviewer && template.status === 'in_review' && myReview?.status === 'pending') return 'validator';
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
            <TemplateBlockHistoryPanel
              blockId={diffBlockId}
              blockNumber={(blocks.findIndex((b) => b.id === diffBlockId) + 1) || '?'}
              history={template.review_history ?? []}
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
                            : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-teal hover:border-odoo-teal/50 hover:bg-odoo-teal/5',
                        ].join(' ')}
                        title="Ver historial de cambios del bloque"
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
