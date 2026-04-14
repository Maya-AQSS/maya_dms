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
import type { DocumentStatus } from '../types/documents';
import { Button, Select } from '../ui';

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
  const [selectedTemplateVersionId, setSelectedTemplateVersionId] = useState('');
  const [, startTransition] = useTransition();

  const { documents, loading, error, reload } = useDocuments();
  const { hierarchy } = useHierarchy();
  const filtered = useFilteredDocuments(documents, activeFilters, hierarchy);
  const selectedModuleId = activeFilters.moduleId;

  const newProgrammingDisabledReason = useMemo(() => {
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
  }, [selectedModuleId, loadingCreationOptions, creationMode, creationMessage]);

  useEffect(() => {
    if (!selectedModuleId) {
      setCreationOptions([]);
      setCreationMode(null);
      setCreationMessage(null);
      setSelectedTemplateVersionId('');
      setShowSelector(false);
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
        setSelectedTemplateVersionId(data.options[0]?.template_version_id ?? '');
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
      await reload();
      navigate(`/documents/${created.id}/editor`);
    } catch (err) {
      setCreationError(err instanceof Error ? err.message : 'No se pudo crear la programación.');
    } finally {
      setCreatingDocument(false);
    }
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
          <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l bg-ui-body dark:bg-ui-dark-bg flex flex-col gap-2">
            <p className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
              Selecciona una plantilla para esta programación
            </p>
            <Select
              value={selectedTemplateVersionId}
              onChange={(e) => setSelectedTemplateVersionId(e.target.value)}
            >
              {creationOptions.map((option) => (
                <option key={option.template_version_id} value={option.template_version_id}>
                  {option.name}
                  {option.description ? ` — ${option.description}` : ''}
                </option>
              ))}
            </Select>
            <div className="flex items-center justify-end gap-2">
              <Button
                type="button"
                variant="secondary"
                onClick={() => setShowSelector(false)}
                disabled={creatingDocument}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                loading={creatingDocument}
                disabled={!selectedTemplateVersionId}
                onClick={() => void handleCreateFromModule(selectedTemplateVersionId)}
              >
                Crear
              </Button>
            </div>
          </div>
        )}

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
            <div
              key={doc.id}
              className="px-5 py-3 flex items-center justify-between gap-4 hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors"
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
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
