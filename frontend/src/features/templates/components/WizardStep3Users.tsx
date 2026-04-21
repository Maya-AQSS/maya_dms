import React, { useEffect, useState } from 'react';
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
import { searchUsers } from '../../../api/users';
import { useUserProfile } from '../../../features/user-profile';

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
}: {
  entry: ValidatorEntry;
  index: number;
  isOrdered: boolean;
  onRemove: (userId: string) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: entry.userId,
  });
  const [removeConfirm, setRemoveConfirm] = useState(false);

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
        <span className="shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-odoo-purple text-white text-[9px] font-bold">
          {index + 1}
        </span>
      )}
      <span className="shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-[10px] font-black border border-odoo-purple/20">
        {initials}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">{entry.name}</p>
        {entry.role && (
          <p className="text-[10px] text-text-secondary dark:text-text-dark-secondary uppercase tracking-tight">{entry.role}</p>
        )}
      </div>
      <div className="flex items-center gap-1">
        {removeConfirm ? (
          <div className="flex items-center gap-1 px-2 py-1 bg-danger-light/20 rounded border border-danger/20 animate-in fade-in">
            <span className="text-[10px] text-danger-dark font-bold">¿Eliminar?</span>
            <button type="button" className="text-[10px] font-bold underline text-danger-dark" onClick={() => onRemove(entry.userId)}>Sí</button>
            <button type="button" className="text-[10px] underline text-text-secondary" onClick={() => setRemoveConfirm(false)}>No</button>
          </div>
        ) : (
          <button type="button" onClick={() => setRemoveConfirm(true)} className="w-6 h-6 flex items-center justify-center rounded-full hover:bg-danger/10 text-text-muted hover:text-danger transition-colors text-xs">✕</button>
        )}
      </div>
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
  target,
}: {
  title: string;
  validators: ValidatorEntry[];
  onValidatorsChange: (v: ValidatorEntry[]) => void;
  validationType?: 'libre' | 'ordenada';
  onValidationTypeChange?: (t: 'libre' | 'ordenada') => void;
  target: 'template' | 'document';
}) {
  const sensors = useSensors(useSensor(PointerSensor));

  const handleRemove = (userId: string) => {
    onValidatorsChange(validators.filter((v) => v.userId !== userId));
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = validators.findIndex((v) => v.userId === active.id);
    const newIndex = validators.findIndex((v) => v.userId === over.id);
    onValidatorsChange(arrayMove(validators, oldIndex, newIndex));
  };

  return (
    <div className="flex-1 min-h-0 flex flex-col border-b border-ui-border dark:border-ui-dark-border last:border-b-0 overflow-hidden">
      <div className="px-4 py-2 flex items-center gap-2 shrink-0 bg-ui-card/50 dark:bg-ui-dark-card/50">
        <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary flex-1 min-w-0">
          {title} ({validators.length})
        </span>
        {validationType && onValidationTypeChange && (
          <div className="flex gap-1 shrink-0">
            {(['libre', 'ordenada'] as const).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => onValidationTypeChange(t)}
                className={`px-2 py-0.5 rounded text-[10px] font-bold transition-all border ${
                  validationType === t
                    ? 'bg-odoo-purple text-white border-odoo-purple'
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
          <p className="text-[10px] text-warning-dark font-bold">Validación ordenada — arrastra para reordenar.</p>
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-2">
        {validators.length === 0 ? (
          <p className="text-[10px] text-text-muted italic">Sin validadores asignados.</p>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
            <SortableContext items={validators.map((v) => v.userId)} strategy={verticalListSortingStrategy}>
              {validators.map((v, i) => (
                <SortableValidatorItem
                  key={v.userId}
                  entry={v}
                  index={i}
                  isOrdered={target === 'template' && validationType === 'ordenada'}
                  onRemove={handleRemove}
                />
              ))}
            </SortableContext>
          </DndContext>
        )}
      </div>
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
  onAdd,
}: {
  title: string;
  searchQuery: string;
  onSearchQueryChange: (q: string) => void;
  filteredUsers: User[];
  searching: boolean;
  searchError: string | null;
  canSearchUsers: boolean;
  onAdd: (user: User) => void;
}) {
  return (
    <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
      <div className="px-4 py-2 border-b border-ui-border dark:border-ui-dark-border shrink-0 space-y-1.5">
        <span className="block text-[10px] font-bold uppercase tracking-widest text-text-secondary">{title}</span>
        {!canSearchUsers && (
          <p className="text-xs text-text-muted dark:text-text-dark-muted">
            No tienes permiso para buscar usuarios (users.search).
          </p>
        )}
        <div className="relative">
          <input
            type="text"
            disabled={!canSearchUsers}
            className="w-full bg-white dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            placeholder="Filtrar usuarios..."
            value={searchQuery}
            onChange={(e) => onSearchQueryChange(e.target.value)}
          />
          <svg className="absolute left-3 top-2.5 w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      <div className="flex-1 min-h-0 overflow-y-auto p-3 space-y-1.5">
        {searching && <p className="text-xs text-text-muted italic p-2">Cargando usuarios…</p>}
        {searchError && <p className="text-xs text-danger-dark p-2">{searchError}</p>}
        {!searching && !searchError && searchQuery.trim().length > 0 && filteredUsers.length === 0 && (
          <p className="text-xs text-text-muted italic p-2">No se encontraron usuarios.</p>
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
                {u.role && <p className="text-[10px] text-text-secondary dark:text-text-dark-secondary uppercase tracking-tight">{u.role}</p>}
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
  validators,
  onValidatorsChange,
  validationType,
  onValidationTypeChange,
  documentValidators,
  onDocumentValidatorsChange,
  documentValidationType,
  onDocumentValidationTypeChange,
}: Props) {
  const { hasPermission } = useUserProfile();
  const canSearchUsers = hasPermission('users.search');

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

  useEffect(() => {
    if (!canSearchUsers) { setSearchResultsTemplate([]); return; }
    const timer = setTimeout(() => {
      setSearchingTemplate(true);
      setSearchErrorTemplate(null);
      searchUsers(searchQueryTemplate.trim())
        .then((res) => setSearchResultsTemplate(res.data))
        .catch(() => setSearchErrorTemplate('No se pudo completar la búsqueda. Inténtalo de nuevo.'))
        .finally(() => setSearchingTemplate(false));
    }, searchQueryTemplate.trim().length === 0 ? 0 : 300);
    return () => clearTimeout(timer);
  }, [searchQueryTemplate, canSearchUsers]);

  useEffect(() => {
    if (!canSearchUsers) { setSearchResultsDocument([]); return; }
    const timer = setTimeout(() => {
      setSearchingDocument(true);
      setSearchErrorDocument(null);
      searchUsers(searchQueryDocument.trim())
        .then((res) => setSearchResultsDocument(res.data))
        .catch(() => setSearchErrorDocument('No se pudo completar la búsqueda. Inténtalo de nuevo.'))
        .finally(() => setSearchingDocument(false));
    }, searchQueryDocument.trim().length === 0 ? 0 : 300);
    return () => clearTimeout(timer);
  }, [searchQueryDocument, canSearchUsers]);

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
          title="Validadores de la plantilla"
          validators={validators}
          onValidatorsChange={onValidatorsChange}
          validationType={validationType}
          onValidationTypeChange={onValidationTypeChange}
          target="template"
        />
        <ValidatorSection
          title="Validadores del documento"
          validators={documentValidators}
          onValidatorsChange={onDocumentValidatorsChange}
          validationType={documentValidationType}
          onValidationTypeChange={onDocumentValidationTypeChange}
          target="document"
        />
      </div>

      {/* Columna Derecha — 70%: Dos paneles de búsqueda independientes */}
      <div className="flex-1 min-w-0 flex flex-col overflow-hidden divide-y divide-ui-border dark:divide-ui-dark-border bg-ui-body/30 dark:bg-ui-dark-bg">
        <UserAddPanel
          title="Añadir a Plantilla"
          searchQuery={searchQueryTemplate}
          onSearchQueryChange={setSearchQueryTemplate}
          filteredUsers={filteredTemplateUsers}
          searching={searchingTemplate}
          searchError={searchErrorTemplate}
          canSearchUsers={canSearchUsers}
          onAdd={handleAddToTemplate}
        />
        <UserAddPanel
          title="Añadir a Documento"
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
