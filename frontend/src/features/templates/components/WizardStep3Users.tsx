import { useEffect, useState } from 'react';
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
import { Button } from '../../../ui';
import type { User } from '../../../types/users';
import { searchUsers } from '../../../api/users';

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
      className={`flex items-center gap-3 px-4 py-3 rounded-lg border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card transition-shadow ${
        isDragging ? 'shadow-lg border-odoo-purple/50' : 'border-ui-border shadow-sm'
      }`}
    >
      {isOrdered && (
        <button
          type="button"
          className="shrink-0 w-6 h-6 flex items-center justify-center cursor-grab active:cursor-grabbing text-text-muted hover:text-text-primary transition-colors focus:outline-none"
          {...attributes}
          {...listeners}
        >
          ⠿
        </button>
      )}

      {isOrdered && (
        <span className="shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-odoo-purple text-white text-[10px] font-bold">
          {index + 1}
        </span>
      )}

      <span className="shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20">
        {initials}
      </span>

      <div className="flex-1 min-w-0">
        <p className="text-sm font-bold text-text-primary dark:text-text-dark-primary truncate">
          {entry.name}
        </p>
        {entry.role && (
          <p className="text-[10px] text-text-secondary dark:text-text-dark-secondary uppercase tracking-tight">{entry.role}</p>
        )}
      </div>

      <div className="flex items-center gap-2">
        {removeConfirm ? (
          <div className="flex items-center gap-2 px-2 py-1 bg-danger-light/20 rounded border border-danger/20 animate-in fade-in slide-in-from-top-1">
            <span className="text-[10px] text-danger-dark font-bold">¿Eliminar?</span>
            <button
              type="button"
              className="text-[10px] font-bold underline text-danger-dark"
              onClick={() => onRemove(entry.userId)}
            >
              Sí
            </button>
            <button
              type="button"
              className="text-[10px] underline text-text-secondary"
              onClick={() => setRemoveConfirm(false)}
            >
              No
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setRemoveConfirm(true)}
            className="w-7 h-7 flex items-center justify-center rounded-full hover:bg-danger/10 text-text-muted hover:text-danger transition-colors text-xs"
          >
            ✕
          </button>
        )}
      </div>
    </div>
  );
}


// ── User search result row ───────────────────────────────────────────────────

function UserSearchResult({
  user,
  alreadyAdded,
  onAdd,
}: {
  user: User;
  alreadyAdded: boolean;
  onAdd: (user: User) => void;
}) {
  const initials = user.name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div className="flex items-center gap-3 px-4 py-3 rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card shadow-sm hover:border-odoo-purple/30 transition-all group">
      <span className="shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-ui-body dark:bg-ui-dark-bg text-text-secondary text-xs font-bold border border-ui-border">
        {initials}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-bold text-text-primary dark:text-text-dark-primary truncate">
          {user.name}
        </p>
        {user.role && (
          <p className="text-[10px] text-text-secondary dark:text-text-dark-secondary uppercase tracking-tight">{user.role}</p>
        )}
      </div>
      {alreadyAdded ? (
        <span className="text-[10px] font-bold text-text-muted italic px-2 py-1 bg-ui-body rounded">
          Ya añadido
        </span>
      ) : (
        <Button 
          type="button" 
          variant="secondary" 
          size="xs" 
          onClick={() => onAdd(user)}
          className="opacity-0 group-hover:opacity-100 transition-opacity"
        >
          + Añadir
        </Button>
      )}
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

type Props = {
  validators: ValidatorEntry[];
  onValidatorsChange: (validators: ValidatorEntry[]) => void;
  validationType: 'libre' | 'ordenada';
  onValidationTypeChange: (type: 'libre' | 'ordenada') => void;
};

export function WizardStep3Users({
  validators,
  onValidatorsChange,
  validationType,
  onValidationTypeChange,
}: Props) {
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<User[]>([]);
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);

  const sensors = useSensors(useSensor(PointerSensor));

  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchQuery.trim().length < 2) {
        setSearchResults([]);
        setSearchError(null);
        return;
      }
      setSearching(true);
      setSearchError(null);
      searchUsers(searchQuery.trim())
        .then((res) => setSearchResults(res.data))
        .catch(() => setSearchError('No se pudo completar la búsqueda. Inténtalo de nuevo.'))
        .finally(() => setSearching(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  const handleAdd = (user: User) => {
    if (validators.some(v => v.userId === user.id)) return;
    onValidatorsChange([...validators, { userId: user.id, name: user.name, role: user.role }]);
  };

  const handleRemove = (userId: string) => {
    onValidatorsChange(validators.filter((v: ValidatorEntry) => v.userId !== userId));
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = validators.findIndex((v: ValidatorEntry) => v.userId === active.id);
    const newIndex = validators.findIndex((v: ValidatorEntry) => v.userId === over.id);
    onValidatorsChange(arrayMove(validators, oldIndex, newIndex));
  };

  return (
    <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
      {/* Columna Izquierda — 25%: Validadores */}
      <div className="md:w-1/4 min-w-0 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border overflow-hidden bg-white dark:bg-ui-dark-card">
        <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 flex items-center justify-between gap-3 shrink-0">
          <div className="flex items-center gap-3 min-w-0">
            <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary shrink-0">
              VALIDADORES ({validators.length})
            </span>
            <div className="flex gap-1">
              {(['libre', 'ordenada'] as const).map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => onValidationTypeChange(t)}
                  className={`px-2.5 py-1 rounded text-[10px] font-bold transition-all border ${
                    validationType === t
                      ? 'bg-odoo-purple text-white border-odoo-purple'
                      : 'bg-transparent text-text-secondary border-ui-border hover:border-odoo-purple/50'
                  }`}
                >
                  {t === 'libre' ? 'Libre' : 'Ordenada'}
                </button>
              ))}
            </div>
          </div>
          <Button variant="ghost" size="xs" onClick={() => document.getElementById('search-input')?.focus()} className="shrink-0">
            + Añadir
          </Button>
        </div>

        {validationType === 'ordenada' && (
          <div className="px-5 py-2 border-b border-warning/20 bg-warning-light/10 shrink-0">
            <p className="text-[11px] text-warning-dark font-bold">
              Validación ordenada activa — arrastra para reordenar.
            </p>
          </div>
        )}

        <div className="flex-1 overflow-y-auto p-5">
          {validators.length === 0 ? (
            <div className="pt-4">
              <p className="text-xs text-text-muted">No hay validadores asignados.</p>
              <p className="text-xs text-text-muted mt-0.5">Busca usuarios en el panel derecho para añadirlos.</p>
            </div>
          ) : (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext 
                items={validators.map((v: ValidatorEntry) => v.userId)} 
                strategy={verticalListSortingStrategy}
              >
                <div className="space-y-3">
                  {validators.map((v, i) => (
                    <SortableValidatorItem
                      key={v.userId}
                      entry={v}
                      index={i}
                      isOrdered={validationType === 'ordenada'}
                      onRemove={handleRemove}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          )}
        </div>
      </div>

      {/* Columna Derecha — 75%: Buscador */}
      <div className="flex-1 min-w-0 flex flex-col overflow-hidden bg-ui-body/30 dark:bg-ui-dark-bg">
        <div className="p-4 border-b border-ui-border dark:border-ui-dark-border shrink-0">
          <div className="relative">
            <input
              id="search-input"
              type="text"
              className="w-full bg-white dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-lg pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple transition-all"
              placeholder="Buscar por nombre, rol o email..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
            <svg className="absolute left-3 top-3 w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-2">
          {!searchQuery.trim() && (
            <p className="text-xs text-text-muted italic p-2">Escribe al menos 2 caracteres para buscar.</p>
          )}
          {searchQuery.trim().length === 1 && (
            <p className="text-xs text-text-muted italic p-2">Escribe al menos 2 caracteres para buscar.</p>
          )}
          {searching && <p className="text-xs text-text-muted italic p-2">Buscando usuarios…</p>}
          {searchError && <p className="text-xs text-danger-dark p-2">{searchError}</p>}
          {!searching && !searchError && searchQuery.trim().length >= 2 && searchResults.length === 0 && (
            <p className="text-xs text-text-muted p-2">No se encontraron usuarios con ese término.</p>
          )}
          {searchResults.map((u: User) => (
            <UserSearchResult
              key={u.id}
              user={u}
              alreadyAdded={validators.some((v: ValidatorEntry) => v.userId === u.id)}
              onAdd={handleAdd}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
