import { lazy, Suspense, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  fetchDocument,
  submitDocumentForReview,
  updateDocument,
  updateDocumentBlock,
} from '../../../api/documents';
import { ApiHttpError } from '../../../api/http';
import { fetchTemplate } from '../../../api/templates';
import { searchDocumentReviewerCandidates, searchUsers } from '../../../api/users';
import { useDarkMode } from '../../../hooks/useDarkMode';
import type { DocumentDetail, DocumentDisplayBlock, DocumentStatus } from '../../../types/documents';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../../templates/blockUiState';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { Button, ConfirmDialog, TextInput } from '../../../ui';

const BlockNoteEditorPanel = lazy(() => import('../../templates/components/BlockNoteEditorPanel'));

type Step = 'properties' | 'blocks' | 'summary';
type SummaryConfirmAction = 'save' | 'submit' | null;
type ReviewModeView = 'sequential' | 'parallel';
type ReviewerView = {
  id: string;
  name: string;
  resolved: boolean;
};

const DOCUMENT_STATUS_LABELS: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

function DocSummaryRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col py-1.5 border-b border-ui-border dark:border-ui-dark-border/30 last:border-0">
      <dt className="text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
        {label}
      </dt>
      <dd className="mt-0.5 text-xs font-medium text-text-primary dark:text-text-dark-primary">
        {value || <span className="text-text-muted italic">—</span>}
      </dd>
    </div>
  );
}

function dateIsoToInput(value: string | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function blockEditorContent(block: DocumentDisplayBlock): unknown[] {
  const fromDoc = normalizeBlockContentForEditor(block.content);
  if (fromDoc.length > 0) {
    return fromDoc;
  }
  return normalizeBlockContentForEditor(block.default_content);
}

type Props = {
  documentId: string;
};

/**
 * Asistente de edición de documento (3 pasos, sin usuarios/validadores).
 * Reutiliza estética y piezas de plantillas (BlockNote, preview HTML) sin acoplar al flujo de TemplateWizard.
 */
export function DocumentWizard({ documentId }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { isDark } = useDarkMode();

  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [title, setTitle] = useState('');
  const [deliveryDeadline, setDeliveryDeadline] = useState('');
  const [saving, setSaving] = useState(false);
  const [submittingForReview, setSubmittingForReview] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const [activeBlockKey, setActiveBlockKey] = useState<string | null>(null);
  const [summaryBlockKey, setSummaryBlockKey] = useState<string | null>(null);
  const [blockSaveError, setBlockSaveError] = useState<string | null>(null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const [documentReviewers, setDocumentReviewers] = useState<ReviewerView[]>([]);
  const [documentReviewMode, setDocumentReviewMode] = useState<ReviewModeView>('parallel');
  const [summaryConfirmAction, setSummaryConfirmAction] = useState<SummaryConfirmAction>(null);

  const isDraft = detail?.status === 'draft';
  const returnToSummary = (location.state as { step?: string } | null)?.step === 'summary';

  const reload = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchDocument(documentId);
      setDetail(data);
      setTitle(data.title);
      setDeliveryDeadline(dateIsoToInput(data.delivery_deadline));
      setActiveBlockKey((prev) => {
        if (prev) return prev;
        if (data.blocks.length === 0) return null;
        const first = data.blocks[0];
        return first.document_block_id ?? first.template_block_id;
      });
    } catch (e) {
      setLoadError(e instanceof Error ? e.message : 'No se pudo cargar el documento.');
      setDetail(null);
    } finally {
      setLoading(false);
    }
  }, [documentId]);

  const refreshDetail = useCallback(async () => {
    try {
      const data = await fetchDocument(documentId);
      setDetail(data);
      setTitle(data.title);
      setDeliveryDeadline(dateIsoToInput(data.delivery_deadline));
      setActiveBlockKey((prev) => {
        if (prev && data.blocks.some((b) => (b.document_block_id ?? b.template_block_id) === prev)) {
          return prev;
        }
        if (data.blocks.length === 0) return null;
        const first = data.blocks[0];
        return first.document_block_id ?? first.template_block_id;
      });
    } catch (e) {
      setBlockSaveError(e instanceof Error ? e.message : 'No se pudo actualizar el documento.');
    }
  }, [documentId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    setStep('properties');
    setCompletedSteps([]);
    setFormError(null);
    setBlockSaveError(null);
  }, [documentId]);

  useEffect(() => {
    if (!detail || detail.status === 'draft') return;
    setCompletedSteps(['properties', 'blocks']);
    setStep('blocks');
  }, [detail?.id, detail?.status]);

  useEffect(() => {
    if (!detail || !returnToSummary) return;
    setCompletedSteps(['properties', 'blocks']);
    setStep('summary');
  }, [detail, returnToSummary]);

  const sortedBlocks = useMemo(
    () => [...(detail?.blocks ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [detail?.blocks],
  );

  const activeBlock = useMemo(
    () => sortedBlocks.find((b) => (b.document_block_id ?? b.template_block_id) === activeBlockKey) ?? null,
    [sortedBlocks, activeBlockKey],
  );
  const activeBlockUiState = activeBlock ? blockToUiState(activeBlock) : null;

  const selectedSummaryBlock = useMemo(
    () =>
      sortedBlocks.find((b) => (b.document_block_id ?? b.template_block_id) === summaryBlockKey) ??
      sortedBlocks[0] ??
      null,
    [sortedBlocks, summaryBlockKey],
  );

  useEffect(() => {
    if (step !== 'summary' || sortedBlocks.length === 0) {
      return;
    }
    setSummaryBlockKey((prev) => {
      if (prev && sortedBlocks.some((b) => (b.document_block_id ?? b.template_block_id) === prev)) {
        return prev;
      }
      const first = sortedBlocks[0];
      return first.document_block_id ?? first.template_block_id;
    });
  }, [step, sortedBlocks]);

  useEffect(() => {
    if (step !== 'summary' || !detail) {
      return;
    }
    let cancelled = false;
    const loadDocumentReviewers = async () => {
      setSummaryError(null);
      try {
        const [templateResp, usersResp] = await Promise.all([
          fetchTemplate(detail.template_id),
          searchDocumentReviewerCandidates(),
        ]);
        if (cancelled) return;
        setDocumentReviewMode(templateResp.data.review_mode ?? 'parallel');
        const ids = templateResp.data.document_reviewers ?? [];
        if (ids.length === 0) {
          setDocumentReviewers([]);
          return;
        }
        const byId = new Map(usersResp.data.map((u) => [u.id, u.name] as const));
        const initial = ids.map((id) => ({
          id,
          name: byId.get(id) ?? '',
          resolved: byId.has(id),
        }));
        const missing = initial.filter((r) => !r.resolved);
        if (missing.length === 0) {
          setDocumentReviewers(initial);
          return;
        }
        const lookedUp = await Promise.all(
          missing.map(async (r) => {
            try {
              const resp = await searchUsers(r.id);
              const exact = resp.data.find((u) => u.id === r.id);
              if (exact?.name) {
                return { ...r, name: exact.name, resolved: true };
              }
            } catch {
              // noop: fallback below
            }
            return {
              ...r,
              name: `Usuario no encontrado (${r.id.slice(0, 8)}...)`,
              resolved: false,
            };
          }),
        );
        const lookedUpById = new Map(lookedUp.map((r) => [r.id, r] as const));
        setDocumentReviewers(initial.map((r) => lookedUpById.get(r.id) ?? r));
      } catch (e) {
        if (!cancelled) {
          setSummaryError(e instanceof Error ? e.message : 'No se pudieron cargar los validadores de documento.');
          setDocumentReviewers([]);
        }
      }
    };
    void loadDocumentReviewers();
    return () => {
      cancelled = true;
    };
  }, [step, detail]);

  const canEditBlocks = isDraft && activeBlock !== null && activeBlockUiState !== 'locked';
  const canClearOptionalBlock = isDraft && activeBlock !== null && activeBlockUiState === 'optional';

  const persistBlockContent = useCallback(
    async (block: DocumentDisplayBlock, content: unknown) => {
      if (!isDraft || blockToUiState(block) === 'locked') return;
      const blockId = block.document_block_id;
      if (!blockId) {
        setBlockSaveError('Este bloque aún no tiene fila de documento; no se puede guardar.');
        return;
      }
      setBlockSaveError(null);
      try {
        await updateDocumentBlock(documentId, blockId, content);
        await refreshDetail();
      } catch (e) {
        const msg =
          e instanceof ApiHttpError ? e.message : e instanceof Error ? e.message : 'Error al guardar el bloque.';
        setBlockSaveError(msg);
      }
    },
    [documentId, isDraft, refreshDetail],
  );

  const handleGoToStep = (s: Step) => {
    if (s === 'properties') setStep(s);
    else if (s === 'blocks' && completedSteps.includes('properties')) setStep(s);
    else if (s === 'summary' && completedSteps.includes('blocks')) setStep(s);
  };

  const handleContinue = async () => {
    setFormError(null);
    if (step === 'properties') {
      if (!title.trim()) {
        setFormError('El título es obligatorio.');
        return;
      }
      if (!isDraft) {
        setCompletedSteps((prev) => Array.from(new Set([...prev, 'properties'] as Step[])));
        setStep('blocks');
        return;
      }
      setSaving(true);
      try {
        const updated = await updateDocument(documentId, {
          title: title.trim(),
          delivery_deadline: deliveryDeadline ? deliveryDeadline : null,
        });
        setDetail((prev) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
        setCompletedSteps((prev) => Array.from(new Set([...prev, 'properties'] as Step[])));
        setStep('blocks');
      } catch (e) {
        setFormError(e instanceof Error ? e.message : 'No se pudieron guardar los datos del documento.');
      } finally {
        setSaving(false);
      }
      return;
    }
    if (step === 'blocks') {
      setCompletedSteps((prev) => Array.from(new Set([...prev, 'blocks'] as Step[])));
      setStep('summary');
      return;
    }
    if (step === 'summary') {
      navigate('/documents');
    }
  };

  const handleSubmitForReview = async () => {
    if (!detail || detail.status !== 'draft') {
      return;
    }
    setSummaryError(null);
    setSubmittingForReview(true);
    try {
      const updated = await submitDocumentForReview(detail.id);
      setDetail((prev) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
      navigate(`/documents/${detail.id}`);
    } catch (e) {
      setSummaryError(e instanceof Error ? e.message : 'No se pudo enviar el documento a validar.');
    } finally {
      setSubmittingForReview(false);
    }
  };

  const handleConfirmSummaryAction = async () => {
    if (summaryConfirmAction === 'save') {
      setSummaryConfirmAction(null);
      navigate('/documents');
      return;
    }
    if (summaryConfirmAction === 'submit') {
      await handleSubmitForReview();
      setSummaryConfirmAction(null);
    }
  };

  const renderStepper = () => {
    const stepsData: { id: Step; label: string; sub: string }[] = [
      { id: 'properties', label: 'Propiedades', sub: 'Título y metadatos' },
      { id: 'blocks', label: 'Bloques', sub: 'Contenido de la programación' },
      { id: 'summary', label: 'Resumen', sub: 'Revisión antes de salir' },
    ];

    return (
      <div className="flex items-center px-6 py-4 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shrink-0">
        {stepsData.map((s, i) => {
          const isActive = step === s.id;
          const isDone = completedSteps.includes(s.id);
          const isPending = !isActive && !isDone;

          const circleCls = isActive
            ? 'bg-odoo-purple text-white'
            : isDone
              ? 'bg-success text-white'
              : 'border border-ui-border text-text-muted';

          const labelCls = isActive ? 'text-odoo-purple' : isDone ? 'text-success' : 'text-text-muted';

          return (
            <div key={s.id} className="flex flex-1 items-center last:flex-none">
              <button
                type="button"
                onClick={() => handleGoToStep(s.id)}
                className={`flex items-center gap-3 focus:outline-none transition-all group ${
                  isPending ? 'opacity-50 cursor-default' : 'cursor-pointer hover:scale-105'
                }`}
                disabled={isPending}
              >
                <span
                  className={`flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0 transition-colors shadow-sm ${circleCls}`}
                >
                  {isDone && !isActive ? '✓' : i + 1}
                </span>
                <span className="text-left hidden lg:block">
                  <span className={`block text-[10px] font-black uppercase tracking-widest ${labelCls}`}>
                    {s.label}
                  </span>
                  <span className="block text-[10px] text-text-muted">{s.sub}</span>
                </span>
              </button>
              {i < stepsData.length - 1 && (
                <div
                  className={`flex-1 h-0.5 mx-4 rounded-full ${
                    completedSteps.includes(s.id) ? 'bg-success' : 'bg-ui-border'
                  }`}
                />
              )}
            </div>
          );
        })}
      </div>
    );
  };

  if (loading && !detail) {
    return (
      <div className="p-6 text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</div>
    );
  }

  if (loadError || !detail) {
    return (
      <div className="p-6 space-y-3">
        <p className="text-sm text-warning-dark dark:text-warning-light">{loadError ?? 'Documento no encontrado.'}</p>
        <Button type="button" variant="secondary" onClick={() => navigate('/documents')}>
          Volver al listado
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col min-h-[calc(100dvh-7rem)] bg-ui-body dark:bg-ui-dark-bg">
      <div className="shrink-0 flex items-center justify-between gap-3 px-4 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shadow-sm z-10">
        <div className="flex items-center gap-3 min-w-0">
          <button
            type="button"
            onClick={() => navigate(`/documents/${documentId}`)}
            className="w-9 h-9 rounded-full text-text-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-all flex items-center justify-center border border-transparent hover:border-ui-border active:scale-95 shrink-0"
            aria-label="Volver a la previsualización"
          >
            ←
          </button>
          <span className="text-sm text-text-secondary truncate">
            Programaciones /{' '}
            <span className="font-bold text-text-primary dark:text-text-dark-primary">{detail.title}</span>
          </span>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          {step === 'summary' && (
            <>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setSummaryConfirmAction('save')}
              >
                Guardar sin enviar
              </Button>
              <Button
                type="button"
                variant="primary"
                size="sm"
                loading={submittingForReview}
                disabled={!isDraft}
                onClick={() => setSummaryConfirmAction('submit')}
              >
                Enviar a validar
              </Button>
            </>
          )}
          {step !== 'summary' && (
            <Button
              type="button"
              variant="primary"
              size="sm"
              loading={saving}
              onClick={() => void handleContinue()}
              className="text-[10px] font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
            >
              Continuar
            </Button>
          )}
        </div>
      </div>

      {!isDraft && (
        <p className="shrink-0 px-6 py-2 text-xs bg-warning-light/20 text-warning-dark dark:bg-warning-dark/20 dark:text-warning-light border-b border-warning/20">
          Este documento no está en borrador: la edición de bloques está deshabilitada; solo puedes revisar el contenido.
        </p>
      )}

      {renderStepper()}

      {step === 'properties' && (
        <div className="flex-1 overflow-y-auto px-6 py-5 bg-ui-body/30 dark:bg-ui-dark-bg space-y-4">
          <p className="text-xs text-text-muted text-center">
            Define los datos base del documento antes de editar los bloques.
          </p>

          {formError && (
            <div className="max-w-5xl mx-auto rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
              {formError}
            </div>
          )}

          <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden grid grid-cols-2 animate-in fade-in slide-in-from-top-1 max-w-5xl mx-auto">
            <div className="px-5 py-4 border-r border-ui-border dark:border-ui-dark-border space-y-3">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
                Propiedades editables
              </p>

              <div>
                <label
                  htmlFor="doc-title-input"
                  className="block text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-1"
                >
                  Título *
                </label>
                <TextInput
                  id="doc-title-input"
                  type="text"
                  fieldSize="comfortable"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  disabled={!isDraft}
                  placeholder="Título de la programación"
                />
              </div>

              <div>
                <label
                  htmlFor="doc-delivery-deadline-input"
                  className="block text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-1"
                >
                  Fecha de entrega
                </label>
                <TextInput
                  id="doc-delivery-deadline-input"
                  type="date"
                  fieldSize="comfortable"
                  value={deliveryDeadline}
                  onChange={(e) => setDeliveryDeadline(e.target.value)}
                  disabled={!isDraft}
                />
                <p className="mt-1 text-[10px] text-text-muted">
                  Esta fecha guía la planificación del documento y se guarda en el propio borrador.
                </p>
              </div>
            </div>

            <div className="px-5 py-4">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary mb-3">
                Datos del documento
              </p>
              <dl className="space-y-0">
                <DocSummaryRow
                  label="Estado"
                  value={DOCUMENT_STATUS_LABELS[detail.status] ?? detail.status}
                />
                <DocSummaryRow
                  label="Fecha de entrega"
                  value={deliveryDeadline ? new Date(`${deliveryDeadline}T00:00:00`).toLocaleDateString() : '—'}
                />
                <DocSummaryRow label="Plantilla" value={detail.template_id} />
                <DocSummaryRow label="Versión de plantilla" value={detail.template_version_id ?? '—'} />
              </dl>
            </div>
          </div>
        </div>
      )}

      {step === 'blocks' && (
        <div className="flex-1 flex min-h-0 min-h-[420px] overflow-hidden">
          <aside className="w-[280px] shrink-0 border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card overflow-y-auto p-3 space-y-1">
            {sortedBlocks.length === 0 ? (
              <p className="text-xs text-text-muted">No hay bloques.</p>
            ) : (
              sortedBlocks.map((b) => {
                const key = b.document_block_id ?? b.template_block_id;
                const selected = key === activeBlockKey;
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setActiveBlockKey(key)}
                    className={`w-full text-left rounded px-3 py-2 text-xs border transition-colors ${
                      selected
                        ? 'border-odoo-purple bg-odoo-purple/10 text-text-primary'
                        : 'border-transparent hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-secondary'
                    }`}
                  >
                    <span className="block font-medium truncate">{b.title || 'Sin título'}</span>
                    <span className="block text-[10px] text-text-muted mt-0.5">
                      {(() => {
                        const ui = blockToUiState(b);
                        return BLOCK_UI_STATE_CONFIG[ui].label;
                      })()}
                    </span>
                  </button>
                );
              })
            )}
          </aside>
          <div className="flex-1 flex flex-col min-w-0 bg-ui-body dark:bg-ui-dark-bg">
            {activeBlock && (
              <>
                <div className="shrink-0 px-4 py-2 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                      {activeBlock.title || 'Bloque'}
                    </p>
                    {canClearOptionalBlock && (
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => void persistBlockContent(activeBlock, [])}
                      >
                        Eliminar bloque opcional
                      </Button>
                    )}
                  </div>
                  {blockSaveError && (
                    <p className="text-xs text-danger-dark dark:text-danger mt-1">{blockSaveError}</p>
                  )}
                </div>
                <div className="flex-1 min-h-0 flex flex-col">
                  {canEditBlocks ? (
                    <Suspense
                      fallback={<p className="p-4 text-xs text-text-muted">Cargando editor…</p>}
                      key={activeBlockKey ?? 'none'}
                    >
                      <BlockNoteEditorPanel
                        initialContent={blockEditorContent(activeBlock)}
                        editable
                        isDark={isDark}
                        onChange={(content) => void persistBlockContent(activeBlock, content)}
                      />
                    </Suspense>
                  ) : (
                    <div className="flex-1 overflow-y-auto p-4">
                      {(() => {
                        const nodes = blockEditorContent(activeBlock);
                        const hasNodes = nodes.length > 0;
                        return hasNodes ? (
                          <BlockContentHtml content={nodes} />
                        ) : (
                          <p className="text-sm text-text-muted italic">Sin contenido en este bloque.</p>
                        );
                      })()}
                    </div>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {step === 'summary' && detail && (
        <div className="flex-1 overflow-y-auto px-6 py-5 bg-ui-body/30 dark:bg-ui-dark-bg space-y-4">
          <p className="text-xs text-text-muted text-center">
            Revisa el título y el contenido de cada bloque. Puedes volver atrás con el stepper. Al salir, el documento
            permanece en su estado actual.
          </p>

          <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden grid grid-cols-2 animate-in fade-in slide-in-from-top-1 max-w-5xl mx-auto">
            <div className="px-5 py-4 border-r border-ui-border dark:border-ui-dark-border">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary mb-3">Propiedades</p>
              <dl className="space-y-0">
                <DocSummaryRow label="Título" value={detail.title} />
                <DocSummaryRow
                  label="Estado"
                  value={DOCUMENT_STATUS_LABELS[detail.status] ?? detail.status}
                />
                <DocSummaryRow label="Versión" value={`v${detail.current_version}`} />
                {detail.study_type_id ? (
                  <DocSummaryRow label="Tipo de estudio" value={detail.study_type_id} />
                ) : null}
                {detail.study_id ? <DocSummaryRow label="Estudio" value={detail.study_id} /> : null}
                {detail.module_id ? <DocSummaryRow label="Módulo" value={detail.module_id} /> : null}
              </dl>
            </div>
            <div className="px-5 py-4">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary mb-3">Documento</p>
              <dl className="space-y-0">
                <DocSummaryRow label="Bloques" value={String(sortedBlocks.length)} />
                <DocSummaryRow
                  label="Bloques con contenido"
                  value={String(sortedBlocks.filter((b) => b.is_filled).length)}
                />
                <DocSummaryRow
                  label="Validadores del documento"
                  value={
                    documentReviewers.length > 0
                      ? (
                          <ul className="mt-1 space-y-1">
                            {documentReviewers.map((reviewer) => (
                              <li key={reviewer.id}>
                                • {reviewer.name}
                              </li>
                            ))}
                          </ul>
                        )
                      : 'Sin validadores asignados en plantilla'
                  }
                />
              </dl>
              {summaryError && (
                <p className="mt-2 text-xs text-danger-dark dark:text-danger">{summaryError}</p>
              )}
            </div>
          </div>

          <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1 max-w-5xl mx-auto">
            <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
              <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
                Contenido — {sortedBlocks.length} bloque{sortedBlocks.length !== 1 ? 's' : ''}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  navigate(`/documents/${documentId}`, { state: { returnToStep: 'summary' } })
                }
              >
                Previsualizar
              </Button>
            </div>
            {sortedBlocks.length === 0 ? (
              <div className="p-5">
                <p className="text-xs text-warning-dark italic">Este documento no tiene bloques.</p>
              </div>
            ) : (
              <div className="grid" style={{ gridTemplateColumns: '200px 1fr', minHeight: '200px' }}>
                <div className="border-r border-ui-border dark:border-ui-dark-border p-3 overflow-y-auto max-h-80">
                  <div className="space-y-1">
                    {sortedBlocks.map((block, i) => {
                      const key = block.document_block_id ?? block.template_block_id;
                      const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                      const fallbackKey = sortedBlocks[0]
                        ? (sortedBlocks[0].document_block_id ?? sortedBlocks[0].template_block_id)
                        : null;
                      const isSelected = key === (summaryBlockKey ?? fallbackKey);
                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => setSummaryBlockKey(key)}
                          className={[
                            'w-full text-left flex items-center gap-2 px-2.5 py-2 rounded-lg border transition-all',
                            isSelected
                              ? 'bg-odoo-purple/10 border-odoo-purple/30 dark:bg-odoo-dark-purple/15'
                              : 'bg-transparent border-ui-border dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border',
                          ].join(' ')}
                        >
                          <span className="shrink-0 text-[10px] font-bold text-text-muted w-4 text-right">
                            {i + 1}
                          </span>
                          <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
                            {block.title || 'Sin nombre'}
                          </span>
                          <span
                            className={`shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}
                          >
                            {cfg.label}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>
                <div className="flex flex-col min-w-0 p-4 overflow-y-auto max-h-80 preview-content">
                  {selectedSummaryBlock ? (
                    (() => {
                      const nodes = blockEditorContent(selectedSummaryBlock);
                      const hasNodes = nodes.length > 0;
                      return hasNodes ? (
                        <BlockContentHtml content={nodes} />
                      ) : (
                        <span className="text-xs text-text-muted italic">Este bloque no tiene contenido.</span>
                      );
                    })()
                  ) : null}
                </div>
              </div>
            )}
          </div>

        </div>
      )}
      <ConfirmDialog
        open={summaryConfirmAction !== null}
        title={summaryConfirmAction === 'submit' ? 'Confirmar envío a validar' : 'Confirmar guardado'}
        description={
          summaryConfirmAction === 'submit'
            ? (
                <div className="space-y-2">
                  <p>Se enviará una notificación a los validadores del documento.</p>
                  {documentReviewers.length > 0 ? (
                    <>
                      <p>
                        Tipo de revisión:{' '}
                        <strong>{documentReviewMode === 'sequential' ? 'Ordenada' : 'Libre'}</strong>
                      </p>
                      {documentReviewMode === 'sequential' ? (
                        <ol className="list-decimal pl-4 space-y-1">
                          {documentReviewers.map((reviewer) => (
                            <li key={reviewer.id}>{reviewer.name}</li>
                          ))}
                        </ol>
                      ) : (
                        <ul className="space-y-1">
                          {documentReviewers.map((reviewer) => (
                            <li key={reviewer.id}>• {reviewer.name}</li>
                          ))}
                        </ul>
                      )}
                    </>
                  ) : (
                    <p>No hay validadores configurados en la plantilla.</p>
                  )}
                  <p>Después no se podrá seguir editando como borrador.</p>
                </div>
              )
            : '¿Quieres guardar y salir sin enviar? El documento permanecerá en estado borrador.'
        }
        confirmLabel={summaryConfirmAction === 'submit' ? 'Sí, enviar a validar' : 'Sí, guardar y salir'}
        cancelLabel="Cancelar"
        variant={summaryConfirmAction === 'submit' ? 'primary' : 'teal'}
        loading={summaryConfirmAction === 'submit' && submittingForReview}
        onCancel={() => setSummaryConfirmAction(null)}
        onConfirm={() => void handleConfirmSummaryAction()}
      />
    </div>
  );
}
