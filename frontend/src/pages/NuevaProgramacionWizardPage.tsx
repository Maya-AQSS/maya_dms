import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchTemplate, fetchTemplateVersionSummaries } from '../api/templates';
import { createDocumentFromModule } from '../api/documents';
import { useHierarchy } from '../features/hierarchy';
import type { Template } from '../types/templates';
import { Button, FieldLabel, Select } from '../ui';

export function NuevaProgramacionWizardPage() {
  const { templateId } = useParams<{ templateId: string }>();
  const navigate = useNavigate();
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();

  const [template, setTemplate] = useState<Template | null>(null);
  const [loadingTemplate, setLoadingTemplate] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [studyTypeId, setStudyTypeId] = useState('');
  const [studyId, setStudyId] = useState('');
  const [moduleId, setModuleId] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  useEffect(() => {
    if (!templateId) {
      setLoadingTemplate(false);
      setLoadError('Identificador de plantilla no válido.');
      return;
    }
    let cancelled = false;
    const load = async () => {
      try {
        setLoadingTemplate(true);
        setLoadError(null);
        const res = await fetchTemplate(templateId);
        if (!cancelled) {
          setTemplate(res.data);
        }
      } catch (e) {
        if (!cancelled) {
          setLoadError(e instanceof Error ? e.message : 'No se pudo cargar la plantilla.');
        }
      } finally {
        if (!cancelled) setLoadingTemplate(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [templateId]);

  // Prefill cascade selectors from the template's own hierarchy context
  useEffect(() => {
    if (!template || hierarchyLoading || hierarchy.length === 0) return;
    if (template.study_type_id) {
      const stId = String(template.study_type_id);
      const found = hierarchy.find((t) => String(t.id) === stId);
      if (found) {
        setStudyTypeId(stId);
        if (template.study_id) {
          const sId = String(template.study_id);
          const foundStudy = found.studies.find((s) => String(s.id) === sId);
          if (foundStudy) {
            setStudyId(sId);
            if (template.module_id) {
              const mId = String(template.module_id);
              const foundModule = foundStudy.course_modules.find((m) => String(m.id) === mId);
              if (foundModule) setModuleId(mId);
            }
          }
        }
      }
    }
  }, [template, hierarchy, hierarchyLoading]);

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const filteredStudies = studyTypeId
    ? (hierarchy.find((t) => String(t.id) === studyTypeId)?.studies ?? [])
    : [];
  const filteredModules = studyId
    ? (allStudies.find((s) => String(s.id) === studyId)?.course_modules ?? [])
    : [];

  const handleCreate = async () => {
    const newErrors: Record<string, string> = {};
    if (!studyTypeId) newErrors.studyTypeId = 'Selecciona un tipo de estudio.';
    if (!studyId) newErrors.studyId = 'Selecciona un estudio.';
    if (!moduleId) newErrors.moduleId = 'Selecciona un módulo.';
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    if (!templateId) return;

    setCreating(true);
    setCreateError(null);
    setErrors({});
    try {
      const versions = await fetchTemplateVersionSummaries(templateId);
      const latestVersion = versions
        .slice()
        .sort((a, b) => b.version_number - a.version_number)[0];

      const created = await createDocumentFromModule({
        module_id: moduleId,
        ...(latestVersion ? { template_version_id: latestVersion.id } : {}),
      });
      navigate(`/documents/${created.id}/editor`);
    } catch (e) {
      setCreateError(e instanceof Error ? e.message : 'No se pudo crear la programación.');
    } finally {
      setCreating(false);
    }
  };

  if (loadingTemplate) {
    return (
      <div className="p-6 text-sm text-text-muted dark:text-text-dark-muted">
        Cargando plantilla…
      </div>
    );
  }

  if (loadError || !template) {
    return (
      <div className="p-6 space-y-3">
        <p className="text-sm text-warning-dark dark:text-warning-light">
          {loadError ?? 'Plantilla no encontrada.'}
        </p>
        <Button type="button" variant="secondary" onClick={() => navigate('/nueva-programacion')}>
          ← Seleccionar plantilla
        </Button>
      </div>
    );
  }

  return (
    <div className="min-h-full bg-ui-body dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 h-[52px] bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 px-6">
        <button
          type="button"
          onClick={() =>
            navigate(`/templates/${templateId}`, {
              state: { selectionMode: true, backTo: '/nueva-programacion' },
            })
          }
          className="shrink-0 w-9 h-9 rounded-full text-text-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-all flex items-center justify-center border border-transparent hover:border-ui-border active:scale-95"
          aria-label="Volver a la vista previa"
        >
          ←
        </button>
        <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
          Nueva Programación
        </span>
      </header>

      <div className="max-w-lg mx-auto px-6 py-8 space-y-6">
        <div className="bg-ui-card dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-card p-6 space-y-6">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-1">
              Plantilla base
            </p>
            <div className="flex items-center justify-between gap-3">
              <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                {template.name}
              </span>
              <button
                type="button"
                onClick={() => navigate('/nueva-programacion')}
                className="text-xs text-odoo-purple dark:text-odoo-dark-purple hover:underline cursor-pointer shrink-0"
              >
                Cambiar plantilla
              </button>
            </div>
          </div>

          <div className="border-t border-ui-border dark:border-ui-dark-border pt-5 space-y-4">
            <p className="text-xs text-text-muted dark:text-text-dark-muted">
              Selecciona el contexto académico donde se archivará esta programación.
            </p>

            <div className="space-y-1">
              <FieldLabel required>Tipo de Estudio</FieldLabel>
              <Select
                fieldSize="comfortable"
                value={studyTypeId}
                disabled={hierarchyLoading}
                onChange={(e) => {
                  setStudyTypeId(e.target.value);
                  setStudyId('');
                  setModuleId('');
                  setErrors((prev) => ({ ...prev, studyTypeId: '', studyId: '', moduleId: '' }));
                }}
                error={!!errors.studyTypeId}
              >
                <option value="">
                  {hierarchyLoading ? 'Cargando…' : '— Seleccionar —'}
                </option>
                {hierarchy.map((t) => (
                  <option key={t.id} value={t.id}>{t.name}</option>
                ))}
              </Select>
              {errors.studyTypeId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.studyTypeId}</p>
              )}
            </div>

            <div className="space-y-1">
              <FieldLabel required>Estudio</FieldLabel>
              <Select
                fieldSize="comfortable"
                value={studyId}
                disabled={hierarchyLoading || !studyTypeId}
                onChange={(e) => {
                  setStudyId(e.target.value);
                  setModuleId('');
                  setErrors((prev) => ({ ...prev, studyId: '', moduleId: '' }));
                }}
                error={!!errors.studyId}
              >
                <option value="">— Seleccionar —</option>
                {filteredStudies.map((s) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </Select>
              {errors.studyId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
              )}
            </div>

            <div className="space-y-1">
              <FieldLabel required>Módulo</FieldLabel>
              <Select
                fieldSize="comfortable"
                value={moduleId}
                disabled={hierarchyLoading || !studyId}
                onChange={(e) => {
                  setModuleId(e.target.value);
                  setErrors((prev) => ({ ...prev, moduleId: '' }));
                }}
                error={!!errors.moduleId}
              >
                <option value="">— Seleccionar —</option>
                {filteredModules.map((m) => (
                  <option key={m.id} value={m.id}>{m.name}</option>
                ))}
              </Select>
              {errors.moduleId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.moduleId}</p>
              )}
            </div>
          </div>

          {createError && (
            <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
              {createError}
            </div>
          )}

          <div className="flex items-center justify-end gap-3 pt-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() =>
                navigate(`/templates/${templateId}`, {
                  state: { selectionMode: true, backTo: '/nueva-programacion' },
                })
              }
              disabled={creating}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="primary"
              loading={creating}
              onClick={() => void handleCreate()}
            >
              Crear programación
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
