import { useMemo, useState } from 'react';
import { ConfirmDialog } from '../../../components/ConfirmDialog';
import type { UpdateTemplatePayload } from '../../../api/templates';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import { useTemplates } from '../hooks/useTemplates';
import {
  TemplateHierarchyFields,
  type TemplateHierarchyFieldKey,
} from './TemplateHierarchyFields';

const VISIBILITY_OPTIONS: { value: TemplateVisibilityLevel; label: string }[] = [
  { value: 'personal', label: 'Personal' },
  { value: 'global', label: 'Global' },
  { value: 'study_type', label: 'Tipo de estudio' },
  { value: 'study', label: 'Estudio' },
  { value: 'module', label: 'Módulo' },
  { value: 'group', label: 'Grupo' },
];

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'published', label: 'Publicada' },
  { value: 'archived', label: 'Archivada' },
];

function visibilityLabel(v: TemplateVisibilityLevel): string {
  return VISIBILITY_OPTIONS.find((o) => o.value === v)?.label ?? v;
}

function isoToDatetimeLocal(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function datetimeLocalToIso(s: string): string | null {
  const t = s.trim();
  if (!t) return null;
  const d = new Date(t);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString();
}

function templateEditIsDirty(t: Template, fields: TemplateEditFields): boolean {
  const descDraftNorm = fields.description.trim();
  const descStoredNorm = (t.description ?? '').trim();
  if (descDraftNorm !== descStoredNorm) return true;
  if (fields.name.trim() !== t.name) return true;
  if (fields.visibilityLevel !== t.visibility_level) return true;
  if (fields.deliveryDeadline !== isoToDatetimeLocal(t.delivery_deadline)) return true;
  if (fields.studyTypeId.trim() !== (t.study_type_id ?? '')) return true;
  if (fields.studyId.trim() !== (t.study_id ?? '')) return true;
  if (fields.moduleId.trim() !== (t.module_id ?? '')) return true;
  if (fields.groupId.trim() !== (t.group_id ?? '')) return true;
  if (fields.status !== t.status) return true;
  if ((Number.parseInt(fields.reviewStages, 10) || 0) !== t.review_stages) return true;
  if (fields.reviewMode !== t.review_mode) return true;
  return false;
}

type TemplateEditFields = {
  name: string;
  description: string;
  visibilityLevel: TemplateVisibilityLevel;
  deliveryDeadline: string;
  studyTypeId: string;
  studyId: string;
  moduleId: string;
  groupId: string;
  status: Template['status'];
  reviewStages: string;
  reviewMode: Template['review_mode'];
};

function TemplateCard({
  template: t,
  onUpdate,
  onDelete,
  onClone,
}: {
  template: Template;
  onUpdate: (id: string, payload: UpdateTemplatePayload) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
  onClone: (id: string) => Promise<void>;
}) {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(t.name);
  const [description, setDescription] = useState(t.description ?? '');
  const [visibilityLevel, setVisibilityLevel] = useState<TemplateVisibilityLevel>(t.visibility_level);
  const [deliveryDeadline, setDeliveryDeadline] = useState(isoToDatetimeLocal(t.delivery_deadline));
  const [studyTypeId, setStudyTypeId] = useState(t.study_type_id ?? '');
  const [studyId, setStudyId] = useState(t.study_id ?? '');
  const [moduleId, setModuleId] = useState(t.module_id ?? '');
  const [groupId, setGroupId] = useState(t.group_id ?? '');
  const [status, setStatus] = useState(t.status);
  const [reviewStages, setReviewStages] = useState(String(t.review_stages));
  const [reviewMode, setReviewMode] = useState(t.review_mode);
  const [busy, setBusy] = useState(false);
  const [dialog, setDialog] = useState<'delete' | 'clone' | 'discard' | null>(null);
  const [dialogLoading, setDialogLoading] = useState(false);

  const editFields: TemplateEditFields = useMemo(
    () => ({
      name,
      description,
      visibilityLevel,
      deliveryDeadline,
      studyTypeId,
      studyId,
      moduleId,
      groupId,
      status,
      reviewStages,
      reviewMode,
    }),
    [
      name,
      description,
      visibilityLevel,
      deliveryDeadline,
      studyTypeId,
      studyId,
      moduleId,
      groupId,
      status,
      reviewStages,
      reviewMode,
    ],
  );

  const editDirty = templateEditIsDirty(t, editFields);

  const resetFromProps = () => {
    setName(t.name);
    setDescription(t.description ?? '');
    setVisibilityLevel(t.visibility_level);
    setDeliveryDeadline(isoToDatetimeLocal(t.delivery_deadline));
    setStudyTypeId(t.study_type_id ?? '');
    setStudyId(t.study_id ?? '');
    setModuleId(t.module_id ?? '');
    setGroupId(t.group_id ?? '');
    setStatus(t.status);
    setReviewStages(String(t.review_stages));
    setReviewMode(t.review_mode);
  };

  const handleSave = async () => {
    setBusy(true);
    try {
      const deadlineIso = datetimeLocalToIso(deliveryDeadline);
      await onUpdate(t.id, {
        name: name.trim(),
        description: description.trim() === '' ? null : description.trim(),
        visibility_level: visibilityLevel,
        delivery_deadline: deadlineIso,
        study_type_id: studyTypeId.trim() === '' ? null : studyTypeId.trim(),
        study_id: studyId.trim() === '' ? null : studyId.trim(),
        module_id: moduleId.trim() === '' ? null : moduleId.trim(),
        group_id: groupId.trim() === '' ? null : groupId.trim(),
        status,
        review_stages: Number.parseInt(reviewStages, 10) || 0,
        review_mode: reviewMode,
      });
      setEditing(false);
    } finally {
      setBusy(false);
    }
  };

  const closeDialog = () => {
    if (dialogLoading) return;
    setDialog(null);
  };

  const confirmDelete = async () => {
    setDialogLoading(true);
    try {
      await onDelete(t.id);
    } catch {
      /* el hook ya registró actionError */
    } finally {
      setDialogLoading(false);
      setDialog(null);
    }
  };

  const confirmClone = async () => {
    setDialogLoading(true);
    try {
      await onClone(t.id);
    } catch {
      /* el hook ya registró actionError */
    } finally {
      setDialogLoading(false);
      setDialog(null);
    }
  };

  const confirmDiscard = () => {
    resetFromProps();
    setEditing(false);
    setDialog(null);
  };

  const onHierarchyChange = (key: TemplateHierarchyFieldKey, value: string) => {
    if (key === 'study_type_id') setStudyTypeId(value);
    else if (key === 'study_id') setStudyId(value);
    else if (key === 'module_id') setModuleId(value);
    else setGroupId(value);
  };

  return (
    <div className="rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card p-4 shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1 space-y-2">
          {editing ? (
            <div className="space-y-2 max-w-2xl">
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-1.5 text-sm"
                placeholder="Nombre"
              />
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={2}
                className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-1.5 text-sm"
                placeholder="Descripción"
              />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
                    Visibilidad
                  </label>
                  <select
                    value={visibilityLevel}
                    onChange={(e) => setVisibilityLevel(e.target.value as TemplateVisibilityLevel)}
                    className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
                  >
                    {VISIBILITY_OPTIONS.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
                    Plazo de entrega (opcional)
                  </label>
                  <input
                    type="datetime-local"
                    value={deliveryDeadline}
                    onChange={(e) => setDeliveryDeadline(e.target.value)}
                    className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
                  />
                </div>
                <div className="sm:col-span-2">
                  <TemplateHierarchyFields
                    values={{
                      study_type_id: studyTypeId,
                      study_id: studyId,
                      module_id: moduleId,
                      group_id: groupId,
                    }}
                    onFieldChange={onHierarchyChange}
                  />
                </div>
                <div>
                  <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
                    Estado
                  </label>
                  <select
                    value={status}
                    onChange={(e) => setStatus(e.target.value as Template['status'])}
                    className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
                  >
                    <option value="draft">Borrador</option>
                    <option value="published">Publicada</option>
                    <option value="archived">Archivada</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
                    Etapas de revisión
                  </label>
                  <input
                    type="number"
                    min={0}
                    value={reviewStages}
                    onChange={(e) => setReviewStages(e.target.value)}
                    className="w-full rounded border border-ui-border px-2 py-1.5 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
                    Modo revisión
                  </label>
                  <select
                    value={reviewMode}
                    onChange={(e) => setReviewMode(e.target.value as Template['review_mode'])}
                    className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
                  >
                    <option value="sequential">Secuencial</option>
                    <option value="parallel">Paralelo</option>
                  </select>
                </div>
              </div>
              <div className="flex gap-2">
                <button
                  type="button"
                  disabled={busy || !name.trim()}
                  onClick={() => void handleSave()}
                  className="rounded bg-odoo-purple px-3 py-1 text-xs font-medium text-white hover:opacity-90 disabled:opacity-50"
                >
                  Guardar
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => {
                    if (!editDirty) {
                      setEditing(false);
                      resetFromProps();
                      return;
                    }
                    setDialog('discard');
                  }}
                  className="rounded border border-ui-border px-3 py-1 text-xs text-text-secondary dark:text-text-dark-secondary"
                >
                  Cancelar
                </button>
              </div>
            </div>
          ) : (
            <>
              <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                {t.name}
              </h3>
              {t.description ? (
                <p className="text-xs text-text-secondary dark:text-text-dark-secondary">{t.description}</p>
              ) : null}
              <div className="flex flex-wrap gap-2 text-xs text-text-muted dark:text-text-dark-muted">
                <span className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5">
                  {visibilityLabel(t.visibility_level)}
                </span>
                <span className="rounded bg-ui-body dark:bg-ui-dark-bg px-2 py-0.5">{t.status}</span>
                <span>v{t.version}</span>
              </div>
            </>
          )}
        </div>
        {!editing && (
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={busy || dialog !== null}
              onClick={() => setEditing(true)}
              className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-1 text-xs text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg"
            >
              Editar
            </button>
            <button
              type="button"
              disabled={busy || dialog !== null}
              onClick={() => setDialog('clone')}
              className="rounded border border-odoo-teal/40 px-2 py-1 text-xs text-odoo-teal hover:bg-odoo-teal/10"
            >
              Clonar
            </button>
            <button
              type="button"
              disabled={busy || dialog !== null}
              onClick={() => setDialog('delete')}
              className="rounded border border-warning/40 px-2 py-1 text-xs text-warning-dark hover:bg-warning-light/30"
            >
              Eliminar
            </button>
          </div>
        )}
      </div>

      <ConfirmDialog
        open={dialog === 'delete'}
        title="¿Eliminar plantilla?"
        description={
          <>
            Se eliminará por completo la plantilla «<strong className="font-medium">{t.name}</strong>» si no tiene
            documentos vinculados; en caso contrario se archivará.
          </>
        }
        confirmLabel="Eliminar"
        variant="danger"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmDelete}
      />
      <ConfirmDialog
        open={dialog === 'clone'}
        title="¿Clonar plantilla?"
        description={
          <>
            Se creará un <strong className="font-medium">borrador</strong> copiando «{t.name}», con el sufijo «(copia)»
            en el nombre.
          </>
        }
        confirmLabel="Clonar"
        cancelLabel="No clonar"
        variant="teal"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmClone}
      />
      <ConfirmDialog
        open={dialog === 'discard'}
        title="¿Descartar cambios?"
        description="Los cambios que no hayas guardado se perderán."
        confirmLabel="Descartar"
        cancelLabel="Seguir editando"
        variant="danger"
        loading={false}
        onCancel={closeDialog}
        onConfirm={confirmDiscard}
      />
    </div>
  );
}

/**
 * Gestión de plantillas normativas: datos vía {@link useTemplates}.
 */
export function TemplatesContent() {
  const {
    templates,
    meta,
    filters,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
    refetch,
    applyFilters,
    goToPage,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    cloneTemplate,
  } = useTemplates();

  const [newName, setNewName] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [newVisibility, setNewVisibility] = useState<TemplateVisibilityLevel>('personal');
  const [newDeadline, setNewDeadline] = useState('');
  const [newStudyTypeId, setNewStudyTypeId] = useState('');
  const [newStudyId, setNewStudyId] = useState('');
  const [newModuleId, setNewModuleId] = useState('');
  const [newGroupId, setNewGroupId] = useState('');
  const [creating, setCreating] = useState(false);

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      status: filters.status ?? '',
      studyTypeId: filters.study_type_id ?? '',
      studyId: filters.study_id ?? '',
      moduleId: filters.module_id ?? '',
      groupId: filters.group_id ?? '',
    }),
    [filters],
  );

  const hasHierarchyIdFilters = useMemo(
    () =>
      Boolean(
        filterUi.studyTypeId.trim() ||
          filterUi.studyId.trim() ||
          filterUi.moduleId.trim() ||
          filterUi.groupId.trim(),
      ),
    [filterUi],
  );

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      await createTemplate({
        name: newName.trim(),
        description: newDesc.trim() === '' ? null : newDesc.trim(),
        visibility_level: newVisibility,
        delivery_deadline: datetimeLocalToIso(newDeadline),
        study_type_id: newStudyTypeId.trim() === '' ? null : newStudyTypeId.trim(),
        study_id: newStudyId.trim() === '' ? null : newStudyId.trim(),
        module_id: newModuleId.trim() === '' ? null : newModuleId.trim(),
        group_id: newGroupId.trim() === '' ? null : newGroupId.trim(),
      });
      setNewName('');
      setNewDesc('');
      setNewVisibility('personal');
      setNewDeadline('');
      setNewStudyTypeId('');
      setNewStudyId('');
      setNewModuleId('');
      setNewGroupId('');
    } catch {
      /* el hook ya dejó el mensaje en actionError */
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Plantillas normativas
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1">
            Listado según tu visibilidad en la API (máx. 20 por página). La visibilidad compartida en alta/edición
            requiere roles de coordinación.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={loading}
          className="rounded border border-ui-border dark:border-ui-dark-border px-3 py-1.5 text-xs text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg disabled:opacity-50"
        >
          Actualizar
        </button>
      </div>

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary flex justify-between gap-4">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="text-xs text-text-link shrink-0">
            Cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="rounded-lg border border-odoo-teal/30 bg-odoo-teal/5 px-4 py-3 text-sm flex justify-between gap-4">
          <span>{actionInfo}</span>
          <button type="button" onClick={clearActionInfo} className="text-xs text-text-link shrink-0">
            Cerrar
          </button>
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4 space-y-3">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
          Filtros
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div>
            <label className="block text-xs text-text-muted mb-1">Visibilidad</label>
            <select
              value={filterUi.visibility}
              onChange={(e) =>
                applyFilters({
                  visibility_level: e.target.value || undefined,
                })
              }
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
            >
              <option value="">Todas</option>
              {VISIBILITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Estado</label>
            <select
              value={filterUi.status}
              onChange={(e) => applyFilters({ status: e.target.value || undefined })}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
            >
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value || 'all'} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div className="lg:col-span-3">
            <TemplateHierarchyFields
              values={{
                study_type_id: filterUi.studyTypeId,
                study_id: filterUi.studyId,
                module_id: filterUi.moduleId,
                group_id: filterUi.groupId,
              }}
              onFieldChange={(key, value) =>
                applyFilters({ [key]: value.trim() === '' ? undefined : value.trim() })
              }
              gridClassName="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
            />
          </div>
        </div>
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-3">
          Nueva plantilla
        </h3>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <div>
            <label className="block text-xs text-text-muted mb-1">Nombre</label>
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Visibilidad</label>
            <select
              value={newVisibility}
              onChange={(e) => setNewVisibility(e.target.value as TemplateVisibilityLevel)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            >
              {VISIBILITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div className="lg:col-span-2">
            <label className="block text-xs text-text-muted mb-1">Descripción</label>
            <input
              type="text"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Plazo de entrega (opcional)</label>
            <input
              type="datetime-local"
              value={newDeadline}
              onChange={(e) => setNewDeadline(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
          <div className="lg:col-span-2">
            <TemplateHierarchyFields
              values={{
                study_type_id: newStudyTypeId,
                study_id: newStudyId,
                module_id: newModuleId,
                group_id: newGroupId,
              }}
              onFieldChange={(key, value) => {
                if (key === 'study_type_id') setNewStudyTypeId(value);
                else if (key === 'study_id') setNewStudyId(value);
                else if (key === 'module_id') setNewModuleId(value);
                else setNewGroupId(value);
              }}
            />
          </div>
        </div>
        <button
          type="button"
          disabled={creating || !newName.trim()}
          onClick={() => void handleCreate()}
          className="mt-3 rounded bg-odoo-purple px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50 inline-flex items-center gap-2"
        >
          {creating ? (
            <>
              <span
                className="inline-block size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                aria-hidden
              />
              Creando…
            </>
          ) : (
            'Crear plantilla'
          )}
        </button>
      </div>

      {loading && templates.length === 0 ? (
        <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantillas…</p>
      ) : null}

      {!loading && templates.length === 0 && !listError ? (
        <div className="text-center py-8 space-y-2 max-w-lg mx-auto">
          <p className="text-sm text-text-muted dark:text-text-dark-muted">
            No hay plantillas visibles con los filtros actuales.
          </p>
          {hasHierarchyIdFilters ? (
            <p className="text-xs text-text-muted dark:text-text-dark-muted leading-relaxed">
              Si acabas de crear una plantilla, los filtros por <span className="font-mono">study_type_id</span>,{' '}
              <span className="font-mono">study_id</span>, etc. deben coincidir con la plantilla. Si filtras por un ID
              pero la creaste sin ese campo (p. ej. personal sin jerarquía), no aparecerá: vacía esos campos o repite
              los mismos UUID que al crear.
            </p>
          ) : null}
        </div>
      ) : null}

      {meta ? (
        <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-text-muted dark:text-text-dark-muted">
          <span>
            Página {meta.current_page} de {meta.last_page} — {meta.total} plantillas
          </span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={loading || meta.current_page <= 1}
              onClick={() => goToPage(meta.current_page - 1)}
              className="rounded border border-ui-border px-2 py-1 disabled:opacity-40"
            >
              Anterior
            </button>
            <button
              type="button"
              disabled={loading || meta.current_page >= meta.last_page}
              onClick={() => goToPage(meta.current_page + 1)}
              className="rounded border border-ui-border px-2 py-1 disabled:opacity-40"
            >
              Siguiente
            </button>
          </div>
        </div>
      ) : null}

      <div className="space-y-4">
        {templates.map((t) => (
          <TemplateCard
            key={t.id}
            template={t}
            onUpdate={async (id, payload) => {
              await updateTemplate(id, payload);
            }}
            onDelete={deleteTemplate}
            onClone={cloneTemplate}
          />
        ))}
      </div>
    </div>
  );
}
