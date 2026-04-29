import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchTemplates } from '../api/templates';
import { VISIBILITY_OPTIONS, visibilityLabel } from '../features/templates/constants';
import type { Template, TemplateListFilters, TemplatesListMeta } from '../types/templates';
import type { TemplateVisibilityLevel } from '../types/templates';
import {
  Button,
  FieldLabel,
  Select,
  TextInput,
  Table,
  TableHead,
  TableBody,
  TableRow,
  TableHeader,
  TableCell,
} from '../ui';

const VISIBILITY_BADGE: Record<TemplateVisibilityLevel, string> = {
  personal:   'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
  global:     'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  study_type: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
  study:      'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
  module:     'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  team:       'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
};

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

export function NuevaProgramacionSelectorPage() {
  const navigate = useNavigate();

  const [filters, setFilters] = useState<TemplateListFilters>({
    status: 'published',
    per_page: 20,
  });
  const [templates, setTemplates] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [authorInput, setAuthorInput] = useState('');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setListError(null);
      try {
        const res = await fetchTemplates({ ...filters, status: 'published' });
        if (!cancelled) {
          setTemplates(res.data);
          setMeta(res.meta);
        }
      } catch (e) {
        if (!cancelled) {
          setListError(e instanceof Error ? e.message : 'No se pudieron cargar las plantillas.');
          setTemplates([]);
          setMeta(null);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [filters]);

  const applyFilters = (patch: Partial<TemplateListFilters>) => {
    setFilters((f) => ({ ...f, ...patch, status: 'published', page: 1 }));
  };

  const goToPage = (page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  };

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      applyFilters({ author_name: value || undefined });
    }, 400);
  };

  const clearFilters = () => {
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setAuthorInput('');
    setFilters({ status: 'published', per_page: 20, page: 1 });
  };

  const filterUi = {
    visibility: filters.visibility_level ?? '',
    deliveryDeadline: filters.delivery_deadline ?? '',
  };

  return (
    <div className="min-h-full bg-ui-body dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 h-[52px] bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 px-6">
        <button
          type="button"
          onClick={() => navigate('/procesos', { state: { tab: 'documents' } })}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          ← Documentos
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          Nueva Programación — Selecciona una plantilla
        </span>
      </header>

      <div className="p-6 space-y-4">
        {listError && (
          <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
            {listError}
          </div>
        )}

        <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
              Filtros
            </h3>
            <Button type="button" variant="secondary" size="sm" onClick={clearFilters}>
              Limpiar filtros
            </Button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
              <FieldLabel>Visibilidad</FieldLabel>
              <Select
                fieldSize="sm"
                value={filterUi.visibility}
                onChange={(e) => applyFilters({ visibility_level: e.target.value || undefined })}
              >
                <option value="">Todas</option>
                {VISIBILITY_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </div>
            <div>
              <FieldLabel>Estado</FieldLabel>
              <Select fieldSize="sm" value="published" disabled>
                <option value="published">Publicada</option>
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
              <FieldLabel>Fecha límite de validación</FieldLabel>
              <TextInput
                fieldSize="sm"
                type="date"
                value={filterUi.deliveryDeadline}
                onChange={(e) => applyFilters({ delivery_deadline: e.target.value || undefined })}
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
                  <TableHeader>Fecha límite de validación</TableHeader>
                  <TableHeader>Versión</TableHeader>
                </TableRow>
              </TableHead>
              <TableBody>
                {loading && templates.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                      Cargando plantillas…
                    </TableCell>
                  </TableRow>
                )}
                {!loading && templates.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                      No hay plantillas publicadas con los filtros actuales.
                    </TableCell>
                  </TableRow>
                )}
                {templates.map((t) => (
                  <TableRow
                    key={t.id}
                    className="hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors cursor-pointer group"
                    onClick={() =>
                      navigate(`/templates/${t.id}`, {
                        state: { selectionMode: true, backTo: '/nueva-programacion' },
                      })
                    }
                  >
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary group-hover:text-odoo-purple dark:group-hover:text-odoo-dark-purple transition-colors">
                      {t.name}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${VISIBILITY_BADGE[t.visibility_level]}`}>
                        {visibilityLabel(t.visibility_level)}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {t.author_name ?? '—'}
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {formatDate(t.delivery_deadline)}
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      v{t.version}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {meta && (
            <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-ui-border dark:border-ui-dark-border text-xs text-text-muted dark:text-text-dark-muted">
              <span>
                {meta.total} {meta.total === 1 ? 'plantilla disponible' : 'plantillas disponibles'}
              </span>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="xs"
                  disabled={loading || meta.current_page <= 1}
                  onClick={() => goToPage(meta.current_page - 1)}
                >
                  ← Anterior
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="xs"
                  disabled={loading || meta.current_page >= meta.last_page}
                  onClick={() => goToPage(meta.current_page + 1)}
                >
                  Siguiente →
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
