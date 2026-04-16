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
import { Button, FieldLabel } from '../../../ui';
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
    opacity: isDragging ? 0.5 : 1,
  };

  const initials = entry.name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="flex items-center gap-2 px-3 py-2.5 rounded border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card min-h-[44px]"
    >
      {isOrdered && (
        <span className="shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-odoo-purple dark:bg-odoo-dark-purple text-white text-[10px] font-bold">
          {index + 1}
        </span>
      )}

      <span className="shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-odoo-purple/15 dark:bg-odoo-dark-purple/25 text-odoo-purple dark:text-odoo-dark-purple text-xs font-bold">
        {initials}
      </span>

      <div className="flex-1 min-w-0">
        <p className="text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
          {entry.name}
        </p>
        {entry.role && (
          <p className="text-[10px] text-text-muted dark:text-text-dark-muted">{entry.role}</p>
        )}
      </div>

      {removeConfirm ? (
        <span className="flex items-center gap-1 text-xs text-warning-dark dark:text-warning-light shrink-0">
          <button
            type="button"
            className="underline font-semibold focus:outline-none"
            onClick={() => { onRemove(entry.userId); setRemoveConfirm(false); }}
          >
            Confirmar
          </button>
          <span>/</span>
          <button
            type="button"
            className="underline focus:outline-none"
            onClick={() => setRemoveConfirm(false)}
          >
            No
          </button>
        </span>
      ) : (
        <button
          type="button"
          onClick={() => setRemoveConfirm(true)}
          className="shrink-0 text-text-muted dark:text-text-dark-muted hover:text-danger text-xs font-bold focus:outline-none w-5 h-5 flex items-center justify-center"
          aria-label="Eliminar validador"
        >
          ✕
        </button>
      )}

      {isOrdered && (
        <button
          type="button"
          className="shrink-0 cursor-grab active:cursor-grabbing text-text-muted dark:text-text-dark-muted hover:text-text-secondary focus:outline-none select-none"
          aria-label="Arrastrar para reordenar"
          {...attributes}
          {...listeners}
        >
          ⠿
        </button>
      )}
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
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div className="flex items-center gap-3 px-3 py-2.5 rounded border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card min-h-[44px]">
      <span className="shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-odoo-purple/15 dark:bg-odoo-dark-purple/25 text-odoo-purple dark:text-odoo-dark-purple text-xs font-bold">
        {initials}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
          {user.name}
        </p>
        {user.role && (
          <p className="text-[10px] text-text-muted dark:text-text-dark-muted">{user.role}</p>
        )}
      </div>
      {alreadyAdded ? (
        <span className="shrink-0 text-xs text-text-muted dark:text-text-dark-muted italic">
          Ya añadido
        </span>
      ) : (
        <Button type="button" variant="outline" size="xs" onClick={() => onAdd(user)}>
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

  // Debounced search — all setState calls are inside the timer callback to avoid
  // triggering cascading renders directly in the effect body.
  useEffect(() => {
    const timer = setTimeout(() => {
      if (!searchQuery.trim()) {
        setSearchResults([]);
        setSearchError(null);
        return;
      }
      setSearching(true);
      setSearchError(null);
      searchUsers(searchQuery)
        .then((res) => setSearchResults(res.data))
        .catch(() => {
          setSearchError('No se pudo buscar usuarios.');
          setSearchResults([]);
        })
        .finally(() => setSearching(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  const isAlreadyAdded = (userId: string) => validators.some((v) => v.userId === userId);

  const handleAdd = (user: User) => {
    if (isAlreadyAdded(user.id)) return;
    onValidatorsChange([
      ...validators,
      { userId: user.id, name: user.name, role: user.role },
    ]);
  };

  const handleRemove = (userId: string) => {
    onValidatorsChange(validators.filter((v) => v.userId !== userId));
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = validators.findIndex((v) => v.userId === String(active.id));
    const newIndex = validators.findIndex((v) => v.userId === String(over.id));
    if (oldIndex !== -1 && newIndex !== -1) {
      onValidatorsChange(arrayMove(validators, oldIndex, newIndex));
    }
  };

  const validatorIds = validators.map((v) => v.userId);

  return (
    <div className="flex flex-1 overflow-hidden flex-col md:flex-row">
      {/* Left column — assigned validators */}
      <div className="md:w-1/2 flex flex-col border-b md:border-b-0 md:border-r border-ui-border dark:border-ui-dark-border overflow-hidden">
        {/* Column header */}
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 flex items-center justify-between shrink-0">
          <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
            Validadores ({validators.length})
          </span>
        </div>

        {/* Validation type toggle */}
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border shrink-0 space-y-2">
          <FieldLabel>Tipo de validación</FieldLabel>
          <div className="flex gap-2">
            {(['libre', 'ordenada'] as const).map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => onValidationTypeChange(t)}
                className={[
                  'px-3 py-1.5 rounded text-xs font-medium transition-all border min-h-9',
                  'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35',
                  validationType === t
                    ? 'border-odoo-purple bg-odoo-purple text-white dark:border-odoo-dark-purple dark:bg-odoo-dark-purple'
                    : 'border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:border-odoo-purple/50',
                ].join(' ')}
              >
                {t === 'libre' ? 'Libre' : 'Ordenada'}
              </button>
            ))}
          </div>
          {validationType === 'ordenada' && (
            <p className="text-xs text-warning-dark dark:text-warning-light">
              La validación ordenada está activa. Arrastra los usuarios para definir el orden.
            </p>
          )}
        </div>

        {/* Validators list */}
        <div className="flex-1 overflow-y-auto p-3">
          {validators.length === 0 ? (
            <p className="text-xs text-text-muted dark:text-text-dark-muted p-2">
              No hay validadores asignados. Busca y añade usuarios desde el panel de la derecha.
            </p>
          ) : validationType === 'ordenada' ? (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={handleDragEnd}
            >
              <SortableContext items={validatorIds} strategy={verticalListSortingStrategy}>
                <div className="space-y-2">
                  {validators.map((entry, i) => (
                    <SortableValidatorItem
                      key={entry.userId}
                      entry={entry}
                      index={i}
                      isOrdered
                      onRemove={handleRemove}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          ) : (
            <div className="space-y-2">
              {validators.map((entry, i) => (
                <SortableValidatorItem
                  key={entry.userId}
                  entry={entry}
                  index={i}
                  isOrdered={false}
                  onRemove={handleRemove}
                />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Right column — user search */}
      <div className="md:w-1/2 flex flex-col overflow-hidden bg-white dark:bg-ui-dark-bg">
        {/* Search input */}
        <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/30 dark:bg-ui-dark-card/30 shrink-0">
          <input
            type="text"
            className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary px-3 py-2 text-sm placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35"
            placeholder="Buscar por nombre, rol…"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>

        {/* Results */}
        <div className="flex-1 overflow-y-auto p-3 space-y-2">
          {searching && (
            <p className="text-xs text-text-muted dark:text-text-dark-muted px-2 py-1">
              Buscando…
            </p>
          )}
          {searchError && (
            <p className="text-xs text-warning-dark dark:text-warning-light px-2 py-1">
              {searchError}
            </p>
          )}
          {!searching && !searchError && searchQuery.trim() && searchResults.length === 0 && (
            <p className="text-xs text-text-muted dark:text-text-dark-muted px-2 py-1">
              Sin resultados para «{searchQuery}».
            </p>
          )}
          {!searchQuery.trim() && (
            <p className="text-xs text-text-muted dark:text-text-dark-muted px-2 py-1">
              Escribe un nombre o rol para buscar usuarios.
            </p>
          )}
          {searchResults.map((user) => (
            <UserSearchResult
              key={user.id}
              user={user}
              alreadyAdded={isAlreadyAdded(user.id)}
              onAdd={handleAdd}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
