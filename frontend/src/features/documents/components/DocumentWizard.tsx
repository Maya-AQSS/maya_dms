import { lazy, Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchDocument, updateDocument, updateDocumentBlock } from '../../../api/documents';
import { ApiHttpError } from '../../../api/http';
import { useDarkMode } from '../../../hooks/useDarkMode';
import type { DocumentDetail, DocumentDisplayBlock } from '../../../types/documents';
import { BLOCK_STATE_LABELS } from '../../../types/blocks';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { Button, FieldLabel, TextInput } from '../../../ui';

const BlockNoteEditorPanel = lazy(() => import('../../templates/components/BlockNoteEditorPanel'));

type Step = 'properties' | 'blocks' | 'summary';

function blockEditorContent(block: DocumentDisplayBlock): unknown {
  if (Array.isArray(block.content) && block.content.length > 0) {
    return block.content;
  }
  if (Array.isArray(block.default_content) && block.default_content.length > 0) {
    return block.default_content;
  }
  return [];
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
  const { isDark } = useDarkMode();

  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [title, setTitle] = useState('');
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const [activeBlockKey, setActiveBlockKey] = useState<string | null>(null);
  const [blockSaveError, setBlockSaveError] = useState<string | null>(null);

  const isDraft = detail?.status === 'draft';

  const reload = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchDocument(documentId);
      setDetail(data);
      setTitle(data.title);
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

  const sortedBlocks = useMemo(
    () => [...(detail?.blocks ?? [])].sort((a, b) => a.sort_order - b.sort_order),
    [detail?.blocks],
  );

  const activeBlock = useMemo(
    () => sortedBlocks.find((b) => (b.document_block_id ?? b.template_block_id) === activeBlockKey) ?? null,
    [sortedBlocks, activeBlockKey],
  );

  const canEditBlocks = isDraft && activeBlock !== null && activeBlock.block_state !== 'locked';

  const persistBlockContent = useCallback(
    async (block: DocumentDisplayBlock, content: unknown) => {
      if (!isDraft || block.block_state === 'locked') return;
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
        const updated = await updateDocument(documentId, { title: title.trim() });
        setDetail((prev) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
        setCompletedSteps((prev) => Array.from(new Set([...prev, 'properties'] as Step[])));
        setStep('blocks');
      } catch (e) {
        setFormError(e instanceof Error ? e.message : 'No se pudo guardar el título.');
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
            <Button type="button" variant="outline" size="sm" onClick={() => navigate('/documents')}>
              Guardar y salir
            </Button>
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
        <div className="flex-1 overflow-y-auto px-8 py-6">
          <div className="max-w-xl space-y-4">
            {formError && (
              <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
                {formError}
              </div>
            )}
            <div>
              <FieldLabel required>Título</FieldLabel>
              <TextInput
                type="text"
                fieldSize="comfortable"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                disabled={!isDraft}
                placeholder="Título de la programación"
              />
            </div>
            <dl className="grid grid-cols-1 gap-2 text-xs border border-ui-border dark:border-ui-dark-border rounded-lg p-4 bg-white dark:bg-ui-dark-card">
              <div className="flex justify-between gap-2">
                <dt className="text-text-muted">Estado</dt>
                <dd className="font-medium">{detail.status}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-text-muted">Plantilla</dt>
                <dd className="font-mono text-[10px] truncate max-w-[60%]">{detail.template_id}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-text-muted">Versión de plantilla</dt>
                <dd className="font-mono text-[10px] truncate max-w-[60%]">{detail.template_version_id ?? '—'}</dd>
              </div>
            </dl>
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
                      {BLOCK_STATE_LABELS[b.block_state]}
                      {b.mandatory ? ' · Obligatorio' : ''}
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
                  <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                    {activeBlock.title || 'Bloque'}
                  </p>
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
                        const hasNodes = Array.isArray(nodes) && nodes.length > 0;
                        return hasNodes ? (
                          <BlockContentHtml content={nodes as unknown[]} />
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

      {step === 'summary' && (
        <div className="flex-1 overflow-y-auto px-8 py-6 space-y-4">
          <p className="text-xs text-text-muted text-center max-w-lg mx-auto">
            Revisa el título y los bloques. Puedes volver atrás con el stepper. Al salir, el documento permanece en su
            estado actual.
          </p>
          <div className="max-w-lg mx-auto rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card p-5 space-y-2 text-sm">
            <p>
              <span className="text-text-muted">Título:</span>{' '}
              <span className="font-medium text-text-primary dark:text-text-dark-primary">{detail.title}</span>
            </p>
            <p>
              <span className="text-text-muted">Bloques:</span>{' '}
              <span className="font-medium">{sortedBlocks.length}</span>
            </p>
            <p>
              <span className="text-text-muted">Estado:</span>{' '}
              <span className="font-medium">{detail.status}</span>
            </p>
          </div>
          <div className="flex justify-center">
            <Button type="button" variant="primary" onClick={() => navigate('/documents')}>
              Finalizar
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
