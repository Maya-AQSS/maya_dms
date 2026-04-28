import { useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDocuments } from '../hooks/useDocuments';
import { Button, FieldLabel, Select, TextInput, Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '../../../ui';
import type { Document, DocumentStatus } from '../../../types/documents';

const STATUS_BADGE: Record<DocumentStatus, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};

const STATUS_LABEL: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Aprobado',
};

const STATUS_FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'in_review', label: 'En revisión' },
  { value: 'published', label: 'Aprobado' },
];

const VISIBILITY_FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: '', label: 'Todas' },
  { value: 'personal', label: 'Personal' },
  { value: 'shared', label: 'Compartida' },
];

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

const PER_PAGE = 20;

type Filters = {
  visibility: string;
  status: string;
  authorName: string;
  date: string;
};

function applyClientFilters(docs: Document[], filters: Filters): Document[] {
  return docs.filter((doc) => {
    if (filters.status && doc.status !== filters.status) return false;
    if (filters.visibility === 'personal' && doc.is_shared_with_me) return false;
    if (filters.visibility === 'shared' && !doc.is_shared_with_me) return false;
    if (filters.authorName) {
      const name = (doc.owner_name ?? '').toLowerCase();
      if (!name.includes(filters.authorName.toLowerCase())) return false;
    }
    if (filters.date && doc.delivery_deadline) {
      if (!doc.delivery_deadline.startsWith(filters.date)) return false;
    } else if (filters.date && !doc.delivery_deadline) {
      return false;
    }
    return true;
  });
}

export function DocumentsTable() {
  const navigate = useNavigate();
  const { documents, loading, error, reload } = useDocuments();

  const [filters, setFilters] = useState<Filters>({
    visibility: '',
    status: '',
    authorName: '',
    date: '',
  });
  const [authorInput, setAuthorInput] = useState('');
  const [page, setPage] = useState(1);
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const filtered = useMemo(() => applyClientFilters(documents, filters), [documents, filters]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
  const safePage = Math.min(page, totalPages);
  const pageSlice = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

  const handleFilterChange = (patch: Partial<Filters>) => {
    setFilters((f) => ({ ...f, ...patch }));
    setPage(1);
  };

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      handleFilterChange({ authorName: value });
    }, 400);
  };

  const clearFilters = () => {
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setAuthorInput('');
    setFilters({ visibility: '', status: '', authorName: '', date: '' });
    setPage(1);
  };

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          Error al cargar documentos: {error.message}
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4 space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
            Filtros
          </h3>
          <div className="flex items-center gap-2">
            <Button type="button" variant="outline" size="xs" onClick={() => void reload()} disabled={loading}>
              Actualizar
            </Button>
            <Button type="button" variant="secondary" size="sm" onClick={clearFilters}>
              Limpiar filtros
            </Button>
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <FieldLabel>Visibilidad</FieldLabel>
            <Select
              fieldSize="sm"
              value={filters.visibility}
              onChange={(e) => handleFilterChange({ visibility: e.target.value })}
            >
              {VISIBILITY_FILTER_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <FieldLabel>Estado</FieldLabel>
            <Select
              fieldSize="sm"
              value={filters.status}
              onChange={(e) => handleFilterChange({ status: e.target.value })}
            >
              {STATUS_FILTER_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <FieldLabel>Autor</FieldLabel>
            <TextInput
              fieldSize="sm"
              placeholder="Nombre del autor..."
              value={authorInput}
              onChange={handleAuthorChange}
            />
          </div>
          <div>
            <FieldLabel>Fecha</FieldLabel>
            <TextInput
              fieldSize="sm"
              type="date"
              value={filters.date}
              onChange={(e) => handleFilterChange({ date: e.target.value })}
            />
          </div>
        </div>
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="overflow-x-auto">
          <Table>
            <TableHead>
              <TableRow>
                <TableHeader>Nombre</TableHeader>
                <TableHeader>Visibilidad</TableHeader>
                <TableHeader>Autor</TableHeader>
                <TableHeader>Estado</TableHeader>
                <TableHeader>Fecha</TableHeader>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading && documents.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                    Cargando documentos…
                  </TableCell>
                </TableRow>
              )}
              {!loading && pageSlice.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                    No hay documentos con los filtros actuales.
                  </TableCell>
                </TableRow>
              )}
              {pageSlice.map((doc) => {
                const status = doc.status as DocumentStatus;
                const isShared = doc.is_shared_with_me === true;
                return (
                  <TableRow
                    key={doc.id}
                    className="hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors cursor-pointer group"
                    onClick={() => navigate(`/documents/${doc.id}`)}
                  >
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary group-hover:text-odoo-purple dark:group-hover:text-odoo-dark-purple transition-colors">
                      {doc.title}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${isShared ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary'}`}>
                        {isShared ? 'Compartida' : 'Personal'}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {doc.owner_name ?? '—'}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[status] ?? ''}`}>
                        {STATUS_LABEL[status] ?? status}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {formatDate(doc.delivery_deadline)}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-ui-border dark:border-ui-dark-border text-xs text-text-muted dark:text-text-dark-muted">
          <span>
            Página {safePage} de {totalPages} — {filtered.length} documentos
          </span>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="xs"
              disabled={loading || safePage <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Anterior
            </Button>
            <Button
              type="button"
              variant="outline"
              size="xs"
              disabled={loading || safePage >= totalPages}
              onClick={() => setPage((p) => p + 1)}
            >
              Siguiente
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
