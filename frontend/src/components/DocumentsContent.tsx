import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import {
  Button,
  Card,
  DataTable,
  PageTitle,
  Pagination,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { CascadeFilters } from './CascadeFilters';
import { useServerDocumentsTable } from '../features/documents/hooks/useServerDocumentsTable';
import type { CascadeDocumentFilters } from '../features/documents/types';
import {
  createDocumentFromModule,
  fetchDocumentCreationOptions,
  type DocumentCreationOption,
} from '../api/documents';
import { fetchTemplateVersion, type TemplateVersionSnapshotBlock } from '../api/templates';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { StructuralBlockPreview, isStructuralBlockType } from '../features/documents/components/StructuralBlockPreview';
import { useUserProfile } from '../features/user-profile';
import { DMS_PERMISSIONS } from '../permissions';
import type { Document, DocumentStatus } from '../types/documents';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { BLOCK_STATE_LABELS, type BlockState } from '../types/blocks';

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
  rejected: 'Rechazado',
};

const STATUS_CLASS: Record<DocumentStatus, string> = {
  published:
    'bg-odoo-teal/10 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
  in_review:
    'bg-warning-light text-warning-dark dark:bg-warning-dark/20 dark:text-warning-light',
  draft:
    'bg-ui-border dark:bg-ui-dark-border text-text-secondary dark:text-text-dark-secondary',
  rejected:
    'bg-danger-light text-danger-dark dark:bg-danger-dark/20 dark:text-danger-light',
};

/**
 * Componente para mostrar el contenido de los documentos.
 *
 * Listado 100% server-side: filtros (estado, búsqueda, contexto académico en
 * cascada, favoritos), paginación clásica y ordenación los resuelve el backend
 * vía {@link useServerDocumentsTable} (estándar unificado useServerTable).
 */
export function DocumentsContent() {
  const { t } = useTranslation('documents');
  const navigate = useNavigate();
  const location = useLocation();
  const [showSubmittedForReviewBanner, setShowSubmittedForReviewBanner] = useState(false);

  const table = useServerDocumentsTable();
  const {
    filters,
    setFilters,
    resetFilters,
    filtersActiveCount,
    sortBy,
    onSortChange,
    page,
    onPageChange,
    pageSize,
    onPageSizeChange,
    rows: serverDocuments,
    meta,
    loading,
    error,
    canIndex,
    refetch,
  } = table;

  // Puente entre el filtro en cascada (camelCase) y los params del backend (snake_case).
  const activeFilters: CascadeDocumentFilters = {
    studyTypeId: filters.study_type_id,
    studyId: filters.study_id,
    moduleId: filters.module_id,
  };
  const selectedModuleId = activeFilters.moduleId;

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

  const {
    hiddenIds: hiddenColumnIds,
    toggleHidden: toggleColumn,
  } = useTablePreferences({ storageKey: 'maya:dms:documents-table' });

  const { hasPermission, loading: profileLoading, profile } = useUserProfile();

  // Expansión de filas: un documento con versión publicada distinta del head
  // se muestra como dos filas (viva + publicada). Server-side: la expansión
  // ocurre sobre la página servida por el backend.
  const displayDocuments = useMemo(() => {
    const out: Document[] = [];
    for (const d of serverDocuments) {
      const hasPublishedFallback =
        d.status !== 'published' &&
        !!d.latest_published_version_id;
      const isAssignedReviewer =
        d.status === 'in_review' &&
        d.is_assigned_reviewer === true;
      const canSeeLive =
        (profile?.id != null && (profile.id === d.created_by || profile.id === d.owner_id)) ||
        d.share_permission === 'edit' ||
        isAssignedReviewer;

      if (!hasPublishedFallback) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
        continue;
      }

      const publishedFallback: Document = {
        ...d,
        title: d.latest_published_title ?? d.title,
        status: 'published',
        current_version: d.latest_published_version_number ?? d.current_version,
        list_variant: 'published_fallback',
        list_row_id: `${d.id}:published`,
      };

      if (canSeeLive) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
      }
      out.push(publishedFallback);
    }
    return out;
  }, [serverDocuments, profile?.id]);

  const isSelectingTemplate = showSelector && creationMode === 'select';

  const showSelectModuleHint =
    !profileLoading &&
    profile !== null &&
    hasPermission(DMS_PERMISSIONS.documentCreate) &&
    !selectedModuleId;

  const newProgrammingDisabledReason = useMemo(() => {
    if (profileLoading || profile === null) {
      return 'Cargando perfil de usuario…';
    }
    if (!hasPermission(DMS_PERMISSIONS.documentCreate)) {
      return 'No tienes permiso para crear programaciones (document.create).';
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

  useEffect(() => {
    const state = location.state as { documentSubmittedForReview?: boolean } | null;
    if (!state?.documentSubmittedForReview) return;
    setShowSubmittedForReviewBanner(true);
    refetch();
    navigate(location.pathname, { replace: true, state: {} });
  }, [location.state, location.pathname, navigate, refetch]);

  const handleClear = () => resetFilters();

  const handleChange = (next: CascadeDocumentFilters) =>
    setFilters({
      study_type_id: next.studyTypeId,
      study_id: next.studyId,
      module_id: next.moduleId,
    });

  const handleCreateFromModule = async (templateVersionId?: string, processId?: string) => {
    if (!selectedModuleId) return;
    if (!processId) {
      setCreationError('No se pudo resolver el proceso de la plantilla seleccionada.');
      return;
    }
    setCreatingDocument(true);
    setCreationError(null);
    try {
      const created = await createDocumentFromModule({
        module_id: selectedModuleId,
        process_id: processId,
        ...(templateVersionId ? { template_version_id: templateVersionId } : {}),
      });
      navigate(`/documents/${created.id}/editor`);
      refetch();
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

  const columns = useMemo<ColumnDef<Document>[]>(
    () => [
      {
        id: 'title',
        header: t('list.titleColumn'),
        sortable: true,
        alwaysVisible: true,
        cell: (d: Document) => (
          <span className="flex items-center gap-2 min-w-0">
            <span className="font-medium text-text-primary dark:text-text-dark-primary truncate">
              {d.title}
            </span>
            {d.has_review_comments && d.status === 'draft' && profile && (d.owner_id === profile.id || d.created_by === profile.id) && (
              <span
                className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                title={t('table.rejectedTitle')}
              >
                ⚠ Revisión
              </span>
            )}
          </span>
        ),
      },
      {
        id: 'version',
        header: t('list.versionColumn'),
        align: 'left',
        cell: (d: Document) => (
          <span className="text-xs text-text-muted dark:text-text-dark-muted">
            v{d.current_version}
          </span>
        ),
      },
      {
        id: 'status',
        header: t('tables.status'),
        sortable: true,
        align: 'left',
        cell: (d: Document) => (
          <span
            className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_CLASS[d.status]}`}
          >
            {STATUS_LABELS[d.status]}
          </span>
        ),
      },
      {
        id: 'delivery_deadline',
        header: t('tables.date'),
        sortable: true,
        align: 'left',
        cell: (d: Document) => (
          <span className="text-xs text-text-muted dark:text-text-dark-muted">
            {d.delivery_deadline ? formatCalendarDateForBrowser(d.delivery_deadline) : '—'}
          </span>
        ),
      },
      {
        id: 'actions',
        header: '',
        align: 'right',
        alwaysVisible: true,
        visibilityLabel: t('tables.actions'),
        cell: (d: Document) => (
          <Button
            type="button"
            variant="ghost"
            size="xs"
            onClick={(e: React.MouseEvent) => {
              e.stopPropagation();
              if (d.list_variant === 'published_fallback' && d.latest_published_version_id) {
                navigate(`/documents/${d.id}?documentVersionId=${encodeURIComponent(d.latest_published_version_id)}`);
                return;
              }
              const isReviewerForDoc =
                d.status === 'in_review' &&
                d.is_assigned_reviewer === true;
              if (isReviewerForDoc) {
                navigate(`/documents/${d.id}/validate`);
                return;
              }
              navigate(`/documents/${d.id}`);
            }}
          >
            Abrir
          </Button>
        ),
      },
    ],
    [navigate, profile, t],
  );

  const handleNewProgrammingClick = async () => {
    if (!selectedModuleId || loadingCreationOptions || creatingDocument) return;
    if (creationMode === 'none') return;
    if (creationMode === 'auto') {
      const templateId = creationOptions[0]?.template_id;
      const templateVersionId = creationOptions[0]?.template_version_id;
      navigate(`/documentos/nuevo/${templateId}/wizard`, {
        state: {
          moduleId: selectedModuleId,
          templateVersionId: templateVersionId ?? null,
          ...buildBackState(location),
        }
      });
      return;
    }
    navigate('/documentos/nuevo', {
      state: { moduleId: selectedModuleId, ...buildBackState(location) }
    });
  };

  if (!canIndex) {
    return (
      <div className="p-6">
        <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
          No tienes permiso para listar documentos (document.index).
        </p>
      </div>
    );
  }

  const totalDocuments = meta?.total ?? 0;

  return (
    <div className="p-6">
      <PageTitle
        title="Documentos"
        actions={
          <Button
            type="button"
            size="sm"
            loading={creatingDocument}
            disabled={newProgrammingDisabledReason !== null || isSelectingTemplate}
            onClick={() => void handleNewProgrammingClick()}
            title={
              isSelectingTemplate
                ? 'Ya estás eligiendo una plantilla.'
                : newProgrammingDisabledReason ?? undefined
            }
          >
            Nueva Programación
          </Button>
        }
      />
      {showSelectModuleHint && (
        <p className="mb-3 text-xs text-text-muted dark:text-text-dark-muted">
          Selecciona un módulo para crear una nueva programación.
        </p>
      )}

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

      <Card className="overflow-hidden">
        {newProgrammingDisabledReason && !showSelectModuleHint && (
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
                        <span className="text-2xs text-text-muted dark:text-text-dark-muted mt-1 block">
                          Visibilidad: {option.visibility_level ?? '—'}
                          {option.team_name ? ` · Equipo: ${option.team_name}` : ''}
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
                              <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                                {snapshotBlockStateLabel(block.block_state)}
                              </span>
                            </div>
                            {isStructuralBlockType(block.block_type) ? (
                              <StructuralBlockPreview block={block} allBlocks={previewBlocks} />
                            ) : hasContent ? (
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
                    onClick={() => void handleCreateFromModule(previewOption.template_version_id, previewOption.process_id)}
                  >
                    Usar esta plantilla
                  </Button>
                </div>
              </>
            )}
          </div>
        )}

        {!(showSelector && creationMode === 'select') && (
          <div>
            {error && (
              <p className="px-5 py-4 text-sm text-warning-dark dark:text-warning-light">
                Error al cargar documentos: {error.message}
              </p>
            )}
            {!error && (
              <>
                <DataTable<Document>
                  title={t('pages.scheduleTitle')}
                  description={
                    <span>
                      {totalDocuments}{' '}
                      {totalDocuments === 1 ? 'documento' : 'documentos'}
                    </span>
                  }
                  columns={columns}
                  rows={displayDocuments}
                  rowKey={(d) => d.list_row_id ?? d.id}
                  loading={loading}
                  pageSize={pageSize}
                  onPageSizeChange={onPageSizeChange}
                  hiddenColumnIds={hiddenColumnIds}
                  onToggleHiddenColumn={toggleColumn}
                  sortBy={sortBy}
                  onSortChange={onSortChange}
                  onRowClick={(d) => {
                    if (d.list_variant === 'published_fallback' && d.latest_published_version_id) {
                      navigate(`/documents/${d.id}?documentVersionId=${encodeURIComponent(d.latest_published_version_id)}`);
                      return;
                    }
                    const isReviewerForDoc =
                      d.status === 'in_review' &&
                      d.is_assigned_reviewer === true;
                    if (isReviewerForDoc) {
                      navigate(`/documents/${d.id}/validate`);
                      return;
                    }
                    navigate(`/documents/${d.id}`);
                  }}
                  emptyMessage="No hay programaciones didácticas con los filtros actuales."
                  className="rounded-none border-0 shadow-none"
                  filtersStorageKey="maya:dms:documents-table"
                  filtersPanel={
                    <CascadeFilters
                      value={activeFilters}
                      onFilterChange={handleChange}
                    />
                  }
                  filtersActiveCount={filtersActiveCount}
                  onClearFilters={handleClear}
                  filtersDefaultOpen={false}
                />
                {meta && meta.last_page > 1 && (
                  <div className="px-5 py-3 border-t border-ui-border-l dark:border-ui-dark-border-l">
                    <Pagination
                      currentPage={page}
                      totalPages={meta.last_page}
                      onChange={onPageChange}
                      info={`${totalDocuments} documentos`}
                    />
                  </div>
                )}
              </>
            )}
          </div>
        )}
      </Card>
    </div>
  );
}
