import { useEffect, useMemo, useState, useTransition } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { CascadeFilters } from './CascadeFilters';
import {
  useDocuments,
  useFilteredDocuments,
  type CascadeDocumentFilters,
} from '../features/documents';
import { useHierarchy } from '../features/hierarchy';
import {
  createDocumentFromModule,
  fetchDocumentCreationOptions,
  type DocumentCreationOption,
} from '../api/documents';
import { fetchTemplateVersion, type TemplateVersionSnapshotBlock } from '../api/templates';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { useUserProfile } from '../features/user-profile';
import type { DocumentStatus } from '../types/documents';
import { BLOCK_STATE_LABELS, type BlockState } from '../types/blocks';
import { Button, FieldLabel, Select } from '../ui';

function snapshotBlockStateLabel(raw: string | undefined): string {
  if (raw != null && raw in BLOCK_STATE_LABELS) {
    return BLOCK_STATE_LABELS[raw as BlockState];
  }
  return BLOCK_STATE_LABELS.editable;
}

function snapshotNodesForPreview(block: TemplateVersionSnapshotBlock): unknown[] {
  return normalizeBlockContentForEditor(block.default_content);
}

const STATUS_LABELS: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

const STATUS_CLASS: Record<DocumentStatus, string> = {
  published:
    'bg-odoo-teal/10 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
  in_review:
    'bg-warning-light text-warning-dark dark:bg-warning-dark/20 dark:text-warning-light',
  draft:
    'bg-ui-border dark:bg-ui-dark-border text-text-secondary dark:text-text-dark-secondary',
};

export function DocumentsContent() {
  const navigate = useNavigate();
  const location = useLocation();
  const [showSubmittedForReviewBanner, setShowSubmittedForReviewBanner] = useState(false);
  const [activeFilters, setActiveFilters] = useState<CascadeDocumentFilters>({
    studyTypeId: '',
    studyId: '',
    moduleId: '',
  });

  // ── Estado del formulario de creación ─────────────────────────────────────
  const [showSelector, setShowSelector] = useState(false);

  // Cascada dentro del formulario de creación
  const [formStudyTypeId, setFormStudyTypeId] = useState('');
  const [formStudyId, setFormStudyId] = useState('');
  const [formModuleId, setFormModuleId] = useState('');
  const [cascadeErrors, setCascadeErrors] = useState<{
    studyTypeId?: string;
    studyId?: string;
    moduleId?: string;
  }>({});

  // Opciones de plantilla para el módulo seleccionado en el formulario
  const [creationOptions, setCreationOptions] = useState<DocumentCreationOption[]>([]);
  const [creationMode, setCreationMode] = useState<'none' | 'auto' | 'select' | null>(null);
  const [creationMessage, setCreationMessage] = useState<string | null>(null);
  const [loadingCreationOptions, setLoadingCreationOptions] = useState(false);
  const [creationError, setCreationError] = useState<string | null>(null);

  // Vista previa de plantilla
  const [previewOption, setPreviewOption] = useState<DocumentCreationOption | null>(null);
  const [previewBlocks, setPreviewBlocks] = useState<TemplateVersionSnapshotBlock[]>([]);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [creatingDocument, setCreatingDocument] = useState(false);

  const [, startTransition] = useTransition();

  const { documents, loading, error, reload } = useDocuments();
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const { hasPermission, loading: profileLoading, profile } = useUserProfile();
  const filtered = useFilteredDocuments(documents, activeFilters, hierarchy);

  // ── Datos derivados de la cascada del formulario ───────────────────────────
  const allStudies = hierarchy.flatMap((t) => t.studies);
  const formStudies = formStudyTypeId
    ? (hierarchy.find((t) => t.id === formStudyTypeId)?.studies ?? [])
    : [];
  const formModules = formStudyId
    ? (allStudies.find((s) => s.id === formStudyId)?.course_modules ?? [])
    : [];

  // ── Botón Nueva Programación ───────────────────────────────────────────────
  const newProgrammingDisabledReason = useMemo(() => {
    if (profileLoading || profile === null) return 'Cargando perfil de usuario…';
    if (!hasPermission('documents.create'))
      return 'No tienes permiso para crear programaciones (documents.create).';
    return null;
  }, [profile, profileLoading, hasPermission]);

  // ── Carga de opciones de plantilla al elegir módulo ────────────────────────
  useEffect(() => {
    if (!formModuleId) {
      setCreationOptions([]);
      setCreationMode(null);
      setCreationMessage(null);
      setPreviewOption(null);
      setPreviewBlocks([]);
      setCreationError(null);
      return;
    }

    let cancelled = false;
    const loadOptions = async () => {
      try {
        setLoadingCreationOptions(true);
        setCreationError(null);
        const data = await fetchDocumentCreationOptions(formModuleId);
        if (cancelled) return;
        setCreationOptions(data.options);
        setCreationMode(data.mode);
        setCreationMessage(data.message);
      } catch (err) {
        if (!cancelled) {
          setCreationError(
            err instanceof Error ? err.message : 'No se pudieron cargar las plantillas.',
          );
          setCreationOptions([]);
          setCreationMode('none');
          setCreationMessage('No se pudieron cargar las plantillas disponibles.');
        }
      } finally {
        if (!cancelled) setLoadingCreationOptions(false);
      }
    };
    void loadOptions();
    return () => { cancelled = true; };
  }, [formModuleId]);

  // ── Banner de envío a revisión ─────────────────────────────────────────────
  useEffect(() => {
    const state = location.state as { documentSubmittedForReview?: boolean } | null;
    if (!state?.documentSubmittedForReview) return;
    setShowSubmittedForReviewBanner(true);
    void reload();
    navigate(location.pathname, { replace: true, state: {} });
  }, [location.state, location.pathname, navigate, reload]);

  // ── Handlers de filtros del listado ───────────────────────────────────────
  const handleClear = () =>
    startTransition(() =>
      setActiveFilters({ studyTypeId: '', studyId: '', moduleId: '' }),
    );

  const handleChange = (filters: CascadeDocumentFilters) =>
    startTransition(() => setActiveFilters(filters));

  // ── Handlers del formulario de creación ───────────────────────────────────
  const resetCreationForm = () => {
    setFormStudyTypeId('');
    setFormStudyId('');
    setFormModuleId('');
    setCascadeErrors({});
    setCreationOptions([]);
    setCreationMode(null);
    setCreationMessage(null);
    setPreviewOption(null);
    setPreviewBlocks([]);
    setPreviewLoading(false);
    setPreviewError(null);
    setCreationError(null);
  };

  const closeSelector = () => {
    setShowSelector(false);
    resetCreationForm();
  };

  const validateCascade = (): boolean => {
    const errs: typeof cascadeErrors = {};
    if (!formStudyTypeId) errs.studyTypeId = 'Selecciona un tipo de estudio.';
    if (!formStudyId) errs.studyId = 'Selecciona un estudio.';
    if (!formModuleId) errs.moduleId = 'Selecciona un módulo.';
    setCascadeErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleCreateFromModule = async (templateVersionId?: string) => {
    if (!validateCascade()) return;
    setCreatingDocument(true);
    setCreationError(null);
    try {
      const created = await createDocumentFromModule({
        module_id: formModuleId,
        ...(templateVersionId ? { template_version_id: templateVersionId } : {}),
      });
      navigate(`/documents/${created.id}/editor`);
      void reload();
    } catch (err) {
      setCreationError(
        err instanceof Error ? err.message : 'No se pudo crear la programación.',
      );
    } finally {
      setCreatingDocument(false);
    }
  };

  const openTemplatePreview = async (option: DocumentCreationOption) => {
    setPreviewOption(option);
    setPreviewLoading(true);
    setPreviewError(null);
    setPreviewBlocks([]);
    try {
      const version = await fetchTemplateVersion(option.template_version_id);
      const snap = Array.isArray(version.blocks_snapshot) ? [...version.blocks_snapshot] : [];
      snap.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
      setPreviewBlocks(snap);
    } catch (e) {
      setPreviewError(e instanceof Error ? e.message : 'No se pudo cargar la vista previa.');
      setPreviewBlocks([]);
    } finally {
      setPreviewLoading(false);
    }
  };

  const backToTemplateList = () => {
    setPreviewOption(null);
    setPreviewBlocks([]);
    setPreviewError(null);
    setPreviewLoading(false);
  };

  const handleNewProgrammingClick = () => {
    setShowSelector(true);
  };

  // ── Render ─────────────────────────────────────────────────────────────────
  return (
    <div className="p-6">
      <CascadeFilters onClear={handleClear} onFilterChange={handleChange} />

      {showSubmittedForReviewBanner && (
        <div
          className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-success/30 bg-success/10 px-4 py-3 dark:bg-success/15 dark:border-success/40"
          role="status"
        >
          <p className="text-sm font-medium text-success-dark dark:text-success">
            Documento enviado a validar.
          </p>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="shrink-0 text-success-dark dark:text-success"
            onClick={() => setShowSubmittedForReviewBanner(false)}
          >
            Cerrar
          </Button>
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Programaciones Didácticas
          </h2>
          <div className="flex items-center gap-3">
            {!loading && (
              <span className="text-xs text-text-muted dark:text-text-dark-muted">
                {filtered.length} {filtered.length === 1 ? 'documento' : 'documentos'}
              </span>
            )}
            <Button
              type="button"
              size="sm"
              disabled={newProgrammingDisabledReason !== null || showSelector}
              onClick={handleNewProgrammingClick}
              title={
                showSelector
                  ? 'Ya estás eligiendo una plantilla.'
                  : newProgrammingDisabledReason ?? undefined
              }
            >
              Nueva Programación
            </Button>
          </div>
        </div>

        {newProgrammingDisabledReason && (
          <p className="px-5 py-2 text-xs text-text-muted dark:text-text-dark-muted border-b border-ui-border-l dark:border-ui-dark-border-l">
            {newProgrammingDisabledReason}
          </p>
        )}

        {/* ── Formulario de creación inline ──────────────────────────────── */}
        {showSelector && (
          <div className="px-5 py-4 border-b border-ui-border-l dark:border-ui-dark-border-l bg-ui-body dark:bg-ui-dark-bg flex flex-col gap-4">

            {/* Cascada obligatoria */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <FieldLabel required>Tipo de Estudio</FieldLabel>
                <Select
                  fieldSize="sm"
                  value={formStudyTypeId}
                  disabled={hierarchyLoading}
                  onChange={(e) => {
                    setFormStudyTypeId(e.target.value);
                    setFormStudyId('');
                    setFormModuleId('');
                    setCascadeErrors((prev) => ({ ...prev, studyTypeId: undefined, studyId: undefined, moduleId: undefined }));
                  }}
                  error={!!cascadeErrors.studyTypeId}
                >
                  <option value="">
                    {hierarchyLoading ? 'Cargando…' : '— Seleccionar —'}
                  </option>
                  {hierarchy.map((t) => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </Select>
                {cascadeErrors.studyTypeId && (
                  <p className="mt-0.5 text-xs text-danger-dark dark:text-danger">
                    {cascadeErrors.studyTypeId}
                  </p>
                )}
              </div>

              <div>
                <FieldLabel required>Estudio</FieldLabel>
                <Select
                  fieldSize="sm"
                  value={formStudyId}
                  disabled={hierarchyLoading || !formStudyTypeId}
                  onChange={(e) => {
                    setFormStudyId(e.target.value);
                    setFormModuleId('');
                    setCascadeErrors((prev) => ({ ...prev, studyId: undefined, moduleId: undefined }));
                  }}
                  error={!!cascadeErrors.studyId}
                >
                  <option value="">— Seleccionar —</option>
                  {formStudies.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </Select>
                {cascadeErrors.studyId && (
                  <p className="mt-0.5 text-xs text-danger-dark dark:text-danger">
                    {cascadeErrors.studyId}
                  </p>
                )}
              </div>

              <div>
                <FieldLabel required>Módulo</FieldLabel>
                <Select
                  fieldSize="sm"
                  value={formModuleId}
                  disabled={hierarchyLoading || !formStudyId}
                  onChange={(e) => {
                    setFormModuleId(e.target.value);
                    setCascadeErrors((prev) => ({ ...prev, moduleId: undefined }));
                    setPreviewOption(null);
                    setPreviewBlocks([]);
                  }}
                  error={!!cascadeErrors.moduleId}
                >
                  <option value="">— Seleccionar —</option>
                  {formModules.map((m) => (
                    <option key={m.id} value={m.id}>{m.name}</option>
                  ))}
                </Select>
                {cascadeErrors.moduleId && (
                  <p className="mt-0.5 text-xs text-danger-dark dark:text-danger">
                    {cascadeErrors.moduleId}
                  </p>
                )}
              </div>
            </div>

            {/* Plantillas disponibles — solo si hay módulo */}
            {formModuleId && (
              <>
                {loadingCreationOptions && (
                  <p className="text-xs text-text-muted dark:text-text-dark-muted">
                    Cargando plantillas disponibles…
                  </p>
                )}

                {creationError && !loadingCreationOptions && (
                  <p className="text-xs text-warning-dark dark:text-warning-light">
                    {creationError}
                  </p>
                )}

                {!loadingCreationOptions && creationMode === 'none' && (
                  <p className="text-xs text-text-muted dark:text-text-dark-muted italic">
                    {creationMessage ?? 'No hay plantillas publicadas disponibles para este módulo.'}
                  </p>
                )}

                {!loadingCreationOptions && !previewOption && creationMode === 'select' && (
                  <>
                    <p className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
                      Elige una plantilla
                    </p>
                    <ul className="flex flex-col gap-2 rounded-md border border-ui-border dark:border-ui-dark-border divide-y divide-ui-border-l dark:divide-ui-dark-border-l overflow-hidden bg-ui-card dark:bg-ui-dark-card">
                      {creationOptions.map((option) => (
                        <li key={option.template_version_id}>
                          <button
                            type="button"
                            onClick={() => void openTemplatePreview(option)}
                            disabled={creatingDocument}
                            className="w-full text-left px-4 py-3 hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <span className="text-sm font-medium text-text-primary dark:text-text-dark-primary block">
                              {option.name}
                            </span>
                            {option.description ? (
                              <span className="text-xs text-text-muted dark:text-text-dark-muted mt-1 block line-clamp-2">
                                {option.description}
                              </span>
                            ) : null}
                          </button>
                        </li>
                      ))}
                    </ul>
                  </>
                )}

                {!loadingCreationOptions && !previewOption && creationMode === 'auto' && creationOptions.length > 0 && (
                  <p className="text-xs text-text-muted dark:text-text-dark-muted">
                    Plantilla: <span className="font-medium text-text-primary dark:text-text-dark-primary">{creationOptions[0]?.name}</span>
                  </p>
                )}

                {previewOption && (
                  <div className="rounded-md border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card p-4 max-h-[min(70vh,520px)] overflow-y-auto">
                    <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary mb-1">
                      {previewOption.name}
                    </h3>
                    {previewOption.description ? (
                      <p className="text-xs text-text-muted dark:text-text-dark-muted mb-4">
                        {previewOption.description}
                      </p>
                    ) : null}
                    {previewLoading && (
                      <p className="text-sm text-text-muted dark:text-text-dark-muted">
                        Cargando vista previa…
                      </p>
                    )}
                    {previewError && !previewLoading && (
                      <p className="text-sm text-warning-dark dark:text-warning-light">
                        {previewError}
                      </p>
                    )}
                    {!previewLoading && !previewError && previewBlocks.length === 0 && (
                      <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                        Esta plantilla no tiene bloques en el snapshot publicado.
                      </p>
                    )}
                    {!previewLoading && !previewError && previewBlocks.length > 0 && (
                      <div className="space-y-8 preview-content">
                        {previewBlocks.map((block) => {
                          const nodes = snapshotNodesForPreview(block);
                          const hasContent = nodes.length > 0;
                          return (
                            <section key={block.id}>
                              <div className="flex flex-wrap items-baseline gap-2 mb-2">
                                {block.title ? (
                                  <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                                    {block.title}
                                  </h4>
                                ) : null}
                                <span className="text-[10px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                                  {snapshotBlockStateLabel(block.block_state)}
                                </span>
                              </div>
                              {hasContent ? (
                                <BlockContentHtml content={nodes as unknown[]} />
                              ) : (
                                <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                                  Sin contenido por defecto en este bloque.
                                </p>
                              )}
                            </section>
                          );
                        })}
                      </div>
                    )}
                  </div>
                )}
              </>
            )}

            {/* Botones del formulario */}
            <div className="flex items-center justify-end gap-2">
              {previewOption && (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={backToTemplateList}
                  disabled={creatingDocument || previewLoading}
                >
                  Elegir otra plantilla
                </Button>
              )}

              <Button
                type="button"
                variant="secondary"
                onClick={closeSelector}
                disabled={creatingDocument}
              >
                Cancelar
              </Button>

              {previewOption && (
                <Button
                  type="button"
                  loading={creatingDocument}
                  disabled={previewLoading || !!previewError || !previewOption}
                  onClick={() => void handleCreateFromModule(previewOption.template_version_id)}
                >
                  Usar esta plantilla
                </Button>
              )}

              {formModuleId && !previewOption && creationMode === 'auto' && (
                <Button
                  type="button"
                  loading={creatingDocument}
                  disabled={loadingCreationOptions}
                  onClick={() => void handleCreateFromModule(creationOptions[0]?.template_version_id)}
                >
                  Crear programación
                </Button>
              )}
            </div>

          </div>
        )}

        {/* ── Listado de documentos ──────────────────────────────────────────── */}
        {!showSelector && (
          <div className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l">
            {loading && (
              <p className="px-5 py-4 text-sm text-text-muted dark:text-text-dark-muted">
                Cargando documentos…
              </p>
            )}
            {error && (
              <p className="px-5 py-4 text-sm text-warning-dark dark:text-warning-light">
                Error al cargar documentos: {error.message}
              </p>
            )}
            {!loading && !error && filtered.length === 0 && (
              <p className="px-5 py-8 text-sm text-center text-text-muted dark:text-text-dark-muted">
                No hay programaciones didácticas con los filtros actuales.
              </p>
            )}
            {filtered.map((doc) => (
              <button
                key={doc.id}
                type="button"
                onClick={() => navigate(`/documents/${doc.id}`)}
                className="w-full text-left px-5 py-3 flex items-center justify-between gap-4 hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors cursor-pointer"
              >
                <div className="min-w-0">
                  <p className="text-sm font-medium text-text-primary dark:text-text-dark-primary truncate">
                    {doc.title}
                  </p>
                  <p className="text-xs text-text-muted dark:text-text-dark-muted mt-0.5">
                    v{doc.current_version}
                  </p>
                </div>
                <span
                  className={`shrink-0 text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_CLASS[doc.status]}`}
                >
                  {STATUS_LABELS[doc.status]}
                </span>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
