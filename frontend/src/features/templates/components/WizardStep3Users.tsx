import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  DndContext,
  closestCenter,
  type DragEndEvent,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
  arrayMove,
  useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { User } from '../../../types/users';
import { searchDocumentReviewerCandidates, searchTemplateReviewerCandidates, type ReviewerCandidateAcademicContext } from '../../../api/users';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { Button, TextInput } from '@ceedcv-maya/shared-ui-react';

// ── Types ────────────────────────────────────────────────────────────────────

export type ValidatorEntry = {
  userId: string;
  name: string;
  role?: string;
};

// ── Sortable validator row ───────────────────────────────────────────────────

function SortableValidatorItem({
  entry,
  index,
  isOrdered,
  onRemove,
  readOnly = false,
}: {
  entry: ValidatorEntry;
  index: number;
  isOrdered: boolean;
  onRemove: (userId: string) => void;
  readOnly?: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: entry.userId,
  });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 20 : 1,
    position: 'relative',
    opacity: isDragging ? 0.6 : 1,
  };

  const initials = entry.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-2 px-3 py-2 rounded-lg border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card transition-shadow ${
        isDragging ? 'shadow-lg border-odoo-purple/50' : 'border-ui-border shadow-sm'
      }`}
    >
      {isOrdered && (
        <button
          type="button"
          className="shrink-0 w-5 h-5 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary transition-colors focus:outline-none"
          {...attributes}
          {...listeners}
        >
          ⠿
        </button>
      )}
      {isOrdered && (
        <span className="shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-odoo-purple text-text-inverse text-xs font-bold">
          {index + 1}
        </span>
      )}
      <span className="shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20">
        {initials}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">{entry.name}</p>
        {entry.role && (
          <p className="text-xs text-text-secondary dark:text-text-dark-secondary uppercase tracking-wider">{entry.role}</p>
        )}
      </div>
      {!readOnly && (
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => onRemove(entry.userId)}
            className="w-6 h-6 flex items-center justify-center rounded-full hover:bg-danger/10 text-text-muted hover:text-danger transition-colors text-xs"
          >
            ✕
          </button>
        </div>
      )}
    </div>
  );
}

// ── Validator section (columna izquierda) ─────────────────────────────────────

function ValidatorSection({
  title,
  validators,
  onValidatorsChange,
  validationType,
  onValidationTypeChange,
  readOnly = false,
}: {
  title: string;
  validators: ValidatorEntry[];
  onValidatorsChange: (v: ValidatorEntry[]) => void;
  validationType?: 'libre' | 'ordenada';
  onValidationTypeChange?: (t: 'libre' | 'ordenada') => void;
  readOnly?: boolean;
}) {
  const sensors = useSensors(useSensor(PointerSensor));
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null);

  const pendingValidator = validators.find((v) => v.userId === confirmDelete) ?? null;

  const handleRequestRemove = (userId: string) => {
    setConfirmDelete(userId);
  };

  const handleConfirmRemove = () => {
    if (confirmDelete) {
      onValidatorsChange(validators.filter((v) => v.userId !== confirmDelete));
    }
    setConfirmDelete(null);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    if (readOnly) return;
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = validators.findIndex((v) => v.userId === active.id);
    const newIndex = validators.findIndex((v) => v.userId === over.id);
    onValidatorsChange(arrayMove(validators, oldIndex, newIndex));
  };

  return (
    <div className="flex-1 min-h-0 flex flex-col border-b border-ui-border dark:border-ui-dark-border last:border-b-0 overflow-hidden">
      <div className="px-4 py-2 flex items-center gap-2 shrink-0 bg-ui-card/50 dark:bg-ui-dark-card/50">
        <span className="text-xs font-bold uppercase tracking-widest text-text-secondary flex-1 min-w-0">
          {title} ({validators.length})
        </span>
        {!readOnly && validationType && onValidationTypeChange && (
          <div className="flex gap-1 shrink-0">
            {(['libre', 'ordenada'] as const).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => onValidationTypeChange(t)}
                className={`px-2 py-0.5 rounded text-xs font-bold transition-all border ${
                  validationType === t
                    ? 'bg-odoo-purple text-text-inverse border-odoo-purple'
                    : 'bg-transparent text-text-secondary border-ui-border hover:border-odoo-purple/50'
                }`}
              >
                {t === 'libre' ? 'Libre' : 'Ordenada'}
              </button>
            ))}
          </div>
        )}
      </div>

      {validationType === 'ordenada' && (
        <div className="px-4 py-1 border-b border-warning/20 bg-warning-light/10 shrink-0">
          <p className="text-xs text-warning-dark font-bold dark:text-warning-light">
            Validación ordenada — arrastra para reordenar.
            </p>
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
        {validators.length === 0 ? (
          <p className="text-xs text-text-muted italic">Sin validadores asignados.</p>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={validators.map((v) => v.userId)} strategy={verticalListSortingStrategy}>
              {validators.map((v, i) => (
                <SortableValidatorItem
                  key={v.userId}
                  entry={v}
                  index={i}
                  isOrdered={validationType === 'ordenada'}
                  onRemove={handleRequestRemove}
                  readOnly={readOnly}
                />
              ))}
            </SortableContext>
          </DndContext>
        )}
      </div>

      {readOnly && (
        <p className="px-4 py-2 text-xs text-text-muted border-t border-ui-border dark:border-ui-dark-border shrink-0">
          No tienes permiso para asignar revisores de plantilla en esta visibilidad.
        </p>
      )}

      {confirmDelete !== null && !readOnly && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 animate-in fade-in"
          onClick={(e) => { if (e.target === e.currentTarget) setConfirmDelete(null); }}
        >
          <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-xl p-6 max-w-sm mx-4 w-full animate-in zoom-in-95">
            <div className="flex justify-center mb-4">
              <span className="flex items-center justify-center w-14 h-14 rounded-full bg-danger/10 text-danger">
                <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </span>
            </div>
            <h2 className="text-base font-bold text-text-primary dark:text-text-dark-primary text-center mb-2">
              ¿Eliminar a {pendingValidator?.name ?? 'este validador'}?
            </h2>
            <p className="text-xs text-text-secondary dark:text-text-dark-secondary text-center mb-4">
              Estás a punto de eliminar este validador de la plantilla.
            </p>
            <div className="p-3 bg-danger/5 border border-danger/20 rounded-lg mb-5">
              <p className="text-xs text-danger-dark font-bold text-center">
                Esta acción es irreversible y no se puede deshacer.
              </p>
            </div>
            <div className="flex gap-3">
              <Button
                type="button"
                variant="secondary"
                size="md"
                className="flex-1"
                onClick={() => setConfirmDelete(null)}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                variant="danger"
                size="md"
                className="flex-1"
                onClick={handleConfirmRemove}
              >
                Eliminar definitivamente
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Panel de búsqueda (columna derecha — sección individual) ──────────────────

function UserAddPanel({
  title,
  searchQuery,
  onSearchQueryChange,
  filteredUsers,
  searching,
  searchError,
  canSearchUsers,
  assignDisabled = false,
  onAdd,
}: {
  title: string;
  searchQuery: string;
  onSearchQueryChange: (q: string) => void;
  filteredUsers: User[];
  searching: boolean;
  searchError: string | null;
  canSearchUsers: boolean;
  assignDisabled?: boolean;
  onAdd: (user: User) => void;
}) {
  const { t } = useTranslation('documents');
  const searchEnabled = canSearchUsers && !assignDisabled;
  return (
    <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
      <div className="px-4 py-2 border-b border-ui-border dark:border-ui-dark-border shrink-0 space-y-1.5">
        <span className="block text-xs font-bold uppercase tracking-widest text-text-secondary">{title}</span>
        {!searchEnabled && (
          <p className="text-xs text-text-muted dark:text-text-dark-muted">
            {assignDisabled
              ? 'No tienes permiso para asignar revisores de plantilla.'
              : 'No tienes permiso para buscar usuarios.'}
          </p>
        )}
        <div className="relative">
          <TextInput
            type="search"
            fieldSize="comfortable"
            disabled={!searchEnabled}
            placeholder={t('validators.filterUsersPlaceholder')}
            value={searchQuery}
            onChange={(e) => onSearchQueryChange(e.target.value)}
            className="pl-9"
          />
          <svg className="absolute left-3 top-2.5 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-1.5">
        {searching && <p className="text-xs text-text-muted italic p-2">Cargando usuarios…</p>}
        {searchError && <p className="text-xs text-danger-dark p-2">{searchError}</p>}
        {!searching &&
          !searchError &&
          searchEnabled &&
          searchQuery.trim().length > 0 &&
          searchQuery.trim().length < 2 && (
            <p className="text-xs text-text-muted italic p-2">Escribe al menos 2 caracteres para buscar.</p>
          )}
        {!searching &&
          !searchError &&
          searchQuery.trim().length >= 2 &&
          filteredUsers.length === 0 && (
            <p className="text-xs text-text-muted italic p-2">No se encontraron usuarios con permiso de revisión.</p>
          )}
        {!searching && !searchError && filteredUsers.map((u) => {
          const initials = u.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
          return (
            <button
              key={u.id}
              type="button"
              onClick={() => onAdd(u)}
              className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card shadow-sm hover:border-odoo-purple/40 hover:bg-odoo-purple/5 transition-all text-left group cursor-pointer"
            >
              <span className="shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-ui-body dark:bg-ui-dark-bg text-text-secondary text-xs font-bold border border-ui-border">
                {initials}
              </span>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-bold text-text-primary dark:text-text-dark-primary truncate">{u.name}</p>
                {u.role && <p className="text-xs text-text-secondary dark:text-text-dark-secondary uppercase tracking-wider">{u.role}</p>}
              </div>
              <span className="shrink-0 w-5 h-5 flex items-center justify-center rounded-full text-odoo-purple text-sm font-bold opacity-0 group-hover:opacity-100 transition-opacity">+</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

type Props = {
  visibilityLevel?: string;
  studyTypeId?: string;
  studyId?: string;
  moduleId?: string;
  teamId?: string;
  validators: ValidatorEntry[];
  onValidatorsChange: (validators: ValidatorEntry[]) => void;
  validationType: 'libre' | 'ordenada';
  onValidationTypeChange: (type: 'libre' | 'ordenada') => void;
  documentValidators: ValidatorEntry[];
  onDocumentValidatorsChange: (validators: ValidatorEntry[]) => void;
  documentValidationType: 'libre' | 'ordenada';
  onDocumentValidationTypeChange: (type: 'libre' | 'ordenada') => void;
};

export function WizardStep3Users({
  visibilityLevel = 'personal',
  studyTypeId,
  studyId,
  moduleId,
  teamId,
  validators,
  onValidatorsChange,
  validationType,
  onValidationTypeChange,
  documentValidators,
  onDocumentValidatorsChange,
  documentValidationType,
  onDocumentValidationTypeChange,
}: Props) {
  const { t } = useTranslation('documents');
  const { hasPermission } = useUserProfile();
  const canSearchUsers = hasPermission(DMS_PERMISSIONS.templateShow);
  const isPersonal = visibilityLevel === 'personal';
  const canAssignTemplateReviewers =
    isPersonal || hasPermission(DMS_PERMISSIONS.templateAssignReview);

  // ── Estado de búsqueda para "Añadir a Plantilla"
  const [searchQueryTemplate, setSearchQueryTemplate] = useState('');
  const [searchResultsTemplate, setSearchResultsTemplate] = useState<User[]>([]);
  const [searchingTemplate, setSearchingTemplate] = useState(false);
  const [searchErrorTemplate, setSearchErrorTemplate] = useState<string | null>(null);

  // ── Estado de búsqueda para "Añadir a Documento"
  const [searchQueryDocument, setSearchQueryDocument] = useState('');
  const [searchResultsDocument, setSearchResultsDocument] = useState<User[]>([]);
  const [searchingDocument, setSearchingDocument] = useState(false);
  const [searchErrorDocument, setSearchErrorDocument] = useState<string | null>(null);

  const reviewerAcademicContext = useMemo((): ReviewerCandidateAcademicContext | undefined => {
    if (!visibilityLevel) {
      return undefined;
    }
    return {
      visibility_level: visibilityLevel,
      study_type_id: studyTypeId || undefined,
      study_id: studyId || undefined,
      module_id: moduleId || undefined,
      team_id: teamId || undefined,
    };
  }, [visibilityLevel, studyTypeId, studyId, moduleId, teamId]);

  useEffect(() => {
    if (!canSearchUsers) {
      setSearchResultsTemplate([]);
      return;
    }
    const q = searchQueryTemplate.trim();
    if (q.length < 2) {
      setSearchResultsTemplate([]);
      return;
    }
    const timer = setTimeout(() => {
      setSearchingTemplate(true);
      setSearchErrorTemplate(null);
      searchTemplateReviewerCandidates(q, undefined, reviewerAcademicContext)
        .then((res) => setSearchResultsTemplate(res.data))
        .catch(() => setSearchErrorTemplate('No se pudo completar la búsqueda. Inténtalo de nuevo.'))
        .finally(() => setSearchingTemplate(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQueryTemplate, canSearchUsers, reviewerAcademicContext]);

  useEffect(() => {
    if (!canSearchUsers) {
      setSearchResultsDocument([]);
      return;
    }
    const q = searchQueryDocument.trim();
    if (q.length < 2) {
      setSearchResultsDocument([]);
      return;
    }
    const timer = setTimeout(() => {
      setSearchingDocument(true);
      setSearchErrorDocument(null);
      searchDocumentReviewerCandidates(q, undefined, reviewerAcademicContext)
        .then((res) => setSearchResultsDocument(res.data))
        .catch(() => setSearchErrorDocument('No se pudo completar la búsqueda. Inténtalo de nuevo.'))
        .finally(() => setSearchingDocument(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQueryDocument, canSearchUsers, reviewerAcademicContext]);

  const handleAddToTemplate = (user: User) => {
    if (!validators.some((v) => v.userId === user.id)) {
      onValidatorsChange([...validators, { userId: user.id, name: user.name, role: user.role }]);
    }
  };

  const handleAddToDocument = (user: User) => {
    if (!documentValidators.some((v) => v.userId === user.id)) {
      onDocumentValidatorsChange([...documentValidators, { userId: user.id, name: user.name, role: user.role }]);
    }
  };

  // Excluir de cada panel los usuarios ya asignados en esa sección
  const filteredTemplateUsers = searchResultsTemplate.filter(
    (u) => !validators.some((v) => v.userId === u.id),
  );
  const filteredDocumentUsers = searchResultsDocument.filter(
    (u) => !documentValidators.some((v) => v.userId === u.id),
  );

  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Columna Izquierda — 30%: Dos secciones de validadores */}
      <div className="md:w-[30%] min-w-0 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden bg-white dark:bg-ui-dark-card">
        <ValidatorSection
          title={t('validators.templateTitle')}
          validators={validators}
          onValidatorsChange={onValidatorsChange}
          validationType={validationType}
          onValidationTypeChange={onValidationTypeChange}
          readOnly={!canAssignTemplateReviewers}
        />
        <ValidatorSection
          title={t('validators.documentTitle')}
          validators={documentValidators}
          onValidatorsChange={onDocumentValidatorsChange}
          validationType={documentValidationType}
          onValidationTypeChange={onDocumentValidationTypeChange}
        />
      </div>

      {/* Columna Derecha — 70%: Dos paneles de búsqueda independientes */}
      <div className="flex-1 min-w-0 flex flex-col overflow-hidden divide-y divide-ui-border dark:divide-ui-dark-border bg-ui-body/30 dark:bg-ui-dark-bg">
        <UserAddPanel
          title={t('validators.addToTemplate')}
          searchQuery={searchQueryTemplate}
          onSearchQueryChange={setSearchQueryTemplate}
          filteredUsers={filteredTemplateUsers}
          searching={searchingTemplate}
          searchError={searchErrorTemplate}
          canSearchUsers={canSearchUsers}
          assignDisabled={!canAssignTemplateReviewers}
          onAdd={handleAddToTemplate}
        />
        <UserAddPanel
          title={t('validators.addToDocument')}
          searchQuery={searchQueryDocument}
          onSearchQueryChange={setSearchQueryDocument}
          filteredUsers={filteredDocumentUsers}
          searching={searchingDocument}
          searchError={searchErrorDocument}
          canSearchUsers={canSearchUsers}
          onAdd={handleAddToDocument}
        />
      </div>
    </div>
  );
}
