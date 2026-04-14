import { useMemo, useState } from 'react';
import { Button, ConfirmDialog } from '../../../ui';
import type { UpdateTemplatePayload } from '../../../api/templates';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import { VISIBILITY_OPTIONS, visibilityLabel } from '../constants';
import {
  datetimeLocalToIso,
  isoToDatetimeLocal,
  templateEditIsDirty,
  type TemplateEditFields,
} from '../templateFormUtils';
import { FieldLabel, Select, TextArea, TextInput } from '../../../ui';
import { TemplateHierarchyFields, type TemplateHierarchyFieldKey } from './TemplateHierarchyFields';
import { TemplateBlockEditor } from './TemplateBlockEditor';

type Props = {
  template: Template;
  onUpdate: (id: string, payload: UpdateTemplatePayload) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
  onClone: (id: string) => Promise<void>;
};

export function TemplateCard({ template: t, onUpdate, onDelete, onClone }: Props) {
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
  const [showBlockEditor, setShowBlockEditor] = useState(false);
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
              <TextInput
                type="text"
                fieldSize="md"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Nombre"
              />
              <TextArea
                fieldSize="md"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={2}
                placeholder="Descripción"
              />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div>
                  <FieldLabel>Visibilidad</FieldLabel>
                  <Select
                    fieldSize="sm"
                    value={visibilityLevel}
                    onChange={(e) => setVisibilityLevel(e.target.value as TemplateVisibilityLevel)}
                  >
                    {VISIBILITY_OPTIONS.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </Select>
                </div>
                <div>
                  <FieldLabel>Plazo de entrega (opcional)</FieldLabel>
                  <TextInput
                    type="datetime-local"
                    fieldSize="sm"
                    value={deliveryDeadline}
                    onChange={(e) => setDeliveryDeadline(e.target.value)}
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
                  <FieldLabel>Estado</FieldLabel>
                  <Select
                    fieldSize="sm"
                    value={status}
                    onChange={(e) => setStatus(e.target.value as Template['status'])}
                  >
                    <option value="draft">Borrador</option>
                    <option value="published">Publicada</option>
                    <option value="archived">Archivada</option>
                  </Select>
                </div>
                <div>
                  <FieldLabel>Etapas de revisión</FieldLabel>
                  <TextInput
                    type="number"
                    fieldSize="sm"
                    min={0}
                    value={reviewStages}
                    onChange={(e) => setReviewStages(e.target.value)}
                  />
                </div>
                <div>
                  <FieldLabel>Modo revisión</FieldLabel>
                  <Select
                    fieldSize="sm"
                    value={reviewMode}
                    onChange={(e) => setReviewMode(e.target.value as Template['review_mode'])}
                  >
                    <option value="sequential">Secuencial</option>
                    <option value="parallel">Paralelo</option>
                  </Select>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  disabled={busy || !name.trim()}
                  onClick={() => void handleSave()}
                >
                  Guardar
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={busy}
                  onClick={() => {
                    if (!editDirty) {
                      setEditing(false);
                      resetFromProps();
                      return;
                    }
                    setDialog('discard');
                  }}
                >
                  Cancelar
                </Button>
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
            <Button
              type="button"
              variant="outline"
              size="xs"
              disabled={busy || dialog !== null}
              onClick={() => setEditing(true)}
            >
              Editar
            </Button>
            <Button
              type="button"
              variant="outline"
              size="xs"
              disabled={busy || dialog !== null}
              onClick={() => setShowBlockEditor(true)}
            >
              Bloques
            </Button>
            <Button
              type="button"
              variant="outlineTeal"
              size="xs"
              disabled={busy || dialog !== null}
              onClick={() => setDialog('clone')}
            >
              Clonar
            </Button>
            <Button
              type="button"
              variant="outlineWarning"
              size="xs"
              disabled={busy || dialog !== null}
              onClick={() => setDialog('delete')}
            >
              Eliminar
            </Button>
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

      {showBlockEditor && (
        <TemplateBlockEditor template={t} onClose={() => setShowBlockEditor(false)} />
      )}
    </div>
  );
}
