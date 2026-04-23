import { useEffect, useMemo, useState, useTransition } from 'react';
import { useNavigate } from 'react-router-dom';
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
import { Button } from '../ui';

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

/**
 * Componente para mostrar el contenido de los documentos.
 */
export function DocumentsContent() {
  const navigate = useNavigate();
  const [activeFilters, setActiveFilters] = useState<CascadeDocumentFilters>({
    studyTypeId: '',
    studyId: '',
    moduleId: '',
  });
  const [creationOptions, setCreationOptions] = useState<DocumentCreationOption[]>([]);
  const [creationMode, setCreationMode] = useState<'none' | 'auto' | 'select' | null>(null);
  const [creationMessage, setCreationMessage] = useState<string | null>(null);
  const [loadingCreationOptions, setLoadingCreationOptions] = useState(false);
  const [creatingDocument, setCreatingDocument] = useState(false);
  const [creationError, setCreationError] = useState<string | null>(null);
  const [showSelector, setShowSelector] = useState(false);
  const [previewOption, setPreviewOption] = useState<DocumentCreationOption | null>(null);
  const [previewBlocks, setPreviewBlocks] = useState<TemplateVersionSnapshotBlock[]>([]);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [, startTransition] = useTransition();

  const { documents, loading, error, reload } = useDocuments();
  const { hierarchy } = useHierarchy();
  const { hasPermission, loading: profileLoading, profile } = useUserProfile();
  const filtered = useFilteredDocuments(documents, activeFilters, hierarchy);
  const selectedModuleId = activeFilters.moduleId;

  const newProgrammingDisabledReason = useMemo(() => {
    if (profileLoading || profile === null) {
      return 'Cargando perfil de usuario…';
    }
    if (!hasPermission('documents.create')) {
      return 'No tienes permiso para crear programaciones (documents.create).';
    }
    if (!selectedModuleId) {
      return 'Selecciona un módulo para crear una nueva programación.';
    }
    if (loadingCreationOptions) {
      return 'Cargando plantillas disponibles...';
    }
    if (creationMode === 'none') {
      return creationMessage ?? 'No hay plantillas publicadas disponibles para este módulo.';
    }
    return null;
  }, [
    profile,
    profileLoading,
    hasPermission,
    selectedModuleId,
    loadingCreationOptions,
    creationMode,
    creationMessage,
  ]);

  useEffect(() => {
    if (!selectedModuleId) {
      setCreationOptions([]);
      setCreationMode(null);
      setCreationMessage(null);
      setShowSelector(false);
      setPreviewOption(null);
      setPreviewBlocks([]);
      setPreviewLoading(false);
      setPreviewError(null);
      setCreationError(null);
      return;
    }

    let cancelled = false;
    const loadOptions = async () => {
      try {
        setLoadingCreationOptions(true);
        setCreationError(null);
        const data = await fetchDocumentCreationOptions(selectedModuleId);
        if (cancelled) return;
        setCreationOptions(data.options);
        setCreationMode(data.mode);
        setCreationMessage(data.message);
      } catch (err) {
        if (!cancelled) {
          setCreationError(err instanceof Error ? err.message : 'No se pudieron cargar las plantillas.');
          setCreationOptions([]);
          setCreationMode('none');
          setCreationMessage('No se pudieron cargar las plantillas disponibles.');
        }
      } finally {
        if (!cancelled) setLoadingCreationOptions(false);
      }
    };

    void loadOptions();

    return () => {
      cancelled = true;
    };
  }, [selectedModuleId]);

  const handleClear = () =>
    startTransition(() =>
      setActiveFilters({ studyTypeId: '', studyId: '', moduleId: '' })
    );

  const handleChange = (filters: CascadeDocumentFilters) =>
    startTransition(() => setActiveFilters(filters));

  const handleCreateFromModule = async (templateVersionId?: string) => {
    if (!selectedModuleId) return;
    setCreatingDocument(true);
    setCreationError(null);
    try {
      const created = await createDocumentFromModule({
        module_id: selectedModuleId,
        ...(templateVersionId ? { template_version_id: templateVersionId } : {}),
      });
      navigate(`/documents/${created.id}/editor`);
      void reload();
    } catch (err) {
      setCreationError(err instanceof Error ? err.message : 'No se pudo crear la programación.');
    } finally {
      setCreatingDocument(false);
    }
  };

  const closeSelector = () => {
    setShowSelector(false);
    setPreviewOption(null);
    setPreviewBlocks([]);
    setPreviewLoading(false);
    setPreviewError(null);
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

  const handleNewProgrammingClick = async () => {
    if (!selectedModuleId || loadingCreationOptions || creatingDocument) return;
    if (creationMode === 'none') return;
    if (creationMode === 'auto') {
      const versionId = creationOptions[0]?.template_version_id;
      await handleCreateFromModule(versionId);
      return;
    }
    setShowSelector(true);
  };

  return (
    <div className="p-6">
      <CascadeFilters onClear={handleClear} onFilterChange={handleChange} />

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
              loading={creatingDocument}
              disabled={newProgrammingDisabledReason !== null}
              onClick={() => void handleNewProgrammingClick()}
              title={newProgrammingDisabledReason ?? undefined}
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
        {creationError && (
          <p className="px-5 py-2 text-xs text-warning-dark dark:text-warning-light border-b border-ui-border-l dark:border-ui-dark-border-l">
            {creationError}
          </p>
        )}
        {showSelector && creationMode === 'select' && (
          <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l bg-ui-body dark:bg-ui-dark-bg flex flex-col gap-3">
            {!previewOption ? (
              <>
                <p className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
                  Elige una plantilla para verla en previsualización
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
                <div className="flex items-center justify-end">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={closeSelector}
                    disabled={creatingDocument}
                  >
                    Cancelar
                  </Button>
                </div>
              </>
            ) : (
              <>
                <div className="flex flex-wrap items-center gap-2">
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    onClick={backToTemplateList}
                    disabled={creatingDocument || previewLoading}
                  >
                    ← Elegir otra plantilla
                  </Button>
                </div>
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
                    <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando vista previa…</p>
                  )}
                  {previewError && !previewLoading && (
                    <p className="text-sm text-warning-dark dark:text-warning-light">{previewError}</p>
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
                <div className="flex items-center justify-end gap-2">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={backToTemplateList}
                    disabled={creatingDocument || previewLoading}
                  >
                    Elegir otra plantilla
                  </Button>
                  <Button
                    type="button"
                    loading={creatingDocument}
                    disabled={previewLoading || !!previewError || !previewOption}
                    onClick={() => void handleCreateFromModule(previewOption.template_version_id)}
                  >
                    Usar esta plantilla
                  </Button>
                </div>
              </>
            )}
          </div>
        )}

        {!(showSelector && creationMode === 'select') && (
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
