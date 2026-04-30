import { useRef, useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS, visibilityLabel } from '../constants';
import { Button, FieldLabel, Select, TextInput, Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '../../../ui';
import { useUserProfile } from '../../../features/user-profile';
const STATUS_BADGE = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    archived: 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
};
const STATUS_LABEL = {
    draft: 'Borrador',
    in_review: 'En revisión',
    published: 'Publicada',
    archived: 'Archivada',
};
const VISIBILITY_BADGE = {
    personal: 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
    global: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    study_type: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
    study: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
    module: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    team: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
};
function formatDate(iso) {
    if (!iso)
        return '—';
    return iso.slice(0, 10);
}
export function TemplatesTable() {
    const navigate = useNavigate();
    const { profile } = useUserProfile();
    const { templates, meta, filters, loading, listError, actionError, clearActionError, applyFilters, goToPage, } = useTemplates();
    const [nameInput, setNameInput] = useState('');
    const [nameFilter, setNameFilter] = useState('');
    const nameDebounceRef = useRef(null);
    const [authorInput, setAuthorInput] = useState(filters.author_name ?? '');
    const authorDebounceRef = useRef(null);
    const filteredTemplates = useMemo(() => {
        if (!nameFilter)
            return templates;
        const needle = nameFilter.toLowerCase();
        return templates.filter((t) => (t.name ?? '').toLowerCase().includes(needle));
    }, [templates, nameFilter]);
    const filterUi = useMemo(() => ({
        visibility: filters.visibility_level ?? '',
        status: filters.status ?? '',
        deliveryDeadline: filters.delivery_deadline ?? '',
    }), [filters]);
    const handleNameChange = (e) => {
        const value = e.target.value;
        setNameInput(value);
        if (nameDebounceRef.current)
            clearTimeout(nameDebounceRef.current);
        nameDebounceRef.current = setTimeout(() => {
            setNameFilter(value);
        }, 400);
    };
    const handleAuthorChange = (e) => {
        const value = e.target.value;
        setAuthorInput(value);
        if (authorDebounceRef.current)
            clearTimeout(authorDebounceRef.current);
        authorDebounceRef.current = setTimeout(() => {
            applyFilters({ author_name: value || undefined });
        }, 400);
    };
    const clearFilters = () => {
        if (nameDebounceRef.current)
            clearTimeout(nameDebounceRef.current);
        if (authorDebounceRef.current)
            clearTimeout(authorDebounceRef.current);
        setNameInput('');
        setNameFilter('');
        setAuthorInput('');
        applyFilters({
            visibility_level: undefined,
            status: undefined,
            study_type_id: undefined,
            study_id: undefined,
            module_id: undefined,
            team_id: undefined,
            author_name: undefined,
            delivery_deadline: undefined,
        });
    };
    return (<div className="space-y-4">
      {listError && (<div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>)}
      {actionError && (<div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary flex justify-between gap-4">
          <span>{actionError}</span>
          <Button type="button" variant="ghost" size="xs" onClick={clearActionError} className="shrink-0">
            Cerrar
          </Button>
        </div>)}

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
            <FieldLabel>Nombre</FieldLabel>
            <TextInput fieldSize="sm" placeholder="Buscar por nombre..." value={nameInput} onChange={handleNameChange}/>
          </div>
          <div>
            <FieldLabel>Visibilidad</FieldLabel>
            <Select fieldSize="sm" value={filterUi.visibility} onChange={(e) => applyFilters({ visibility_level: e.target.value || undefined })}>
              <option value="">Todas</option>
              {VISIBILITY_OPTIONS.map((o) => (<option key={o.value} value={o.value}>
                  {o.label}
                </option>))}
            </Select>
          </div>
          <div>
            <FieldLabel>Estado</FieldLabel>
            <Select fieldSize="sm" value={filterUi.status} onChange={(e) => applyFilters({ status: e.target.value || undefined })}>
              {STATUS_OPTIONS.map((o) => (<option key={o.value || 'all'} value={o.value}>
                  {o.label}
                </option>))}
            </Select>
          </div>
          <div>
            <FieldLabel>Autor</FieldLabel>
            <TextInput fieldSize="sm" placeholder="Nombre del autor..." value={authorInput} onChange={handleAuthorChange}/>
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
                <TableHeader>Fecha límite</TableHeader>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading && templates.length === 0 && (<TableRow>
                  <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                    Cargando plantillas…
                  </TableCell>
                </TableRow>)}
              {!loading && templates.length === 0 && (<TableRow>
                  <TableCell colSpan={5} className="px-4 py-6 text-sm text-center text-text-muted dark:text-text-dark-muted">
                    No hay plantillas con los filtros actuales.
                  </TableCell>
                </TableRow>)}
              {filteredTemplates.map((t) => {
            const visLevel = t.visibility_level;
            const status = t.status;
            return (<TableRow key={t.id} className="hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors cursor-pointer group" onClick={() => {
                    const isReviewer = t.status === 'in_review' && t.reviewers?.some(r => r.user_id === profile?.id);
                    if (isReviewer) {
                        navigate(`/templates/${t.id}/review`);
                    }
                    else {
                        navigate(`/templates/${t.id}`);
                    }
                }}>
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary group-hover:text-odoo-purple dark:group-hover:text-odoo-dark-purple transition-colors">
                      <span className="flex items-center gap-2 min-w-0">
                        <span className="truncate">{t.name}</span>
                        {t.has_review_comments && t.status === 'draft' && profile && t.created_by === profile.id && (<span className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20" title="Esta plantilla tiene bloques con comentarios de revisión pendientes.">
                            ⚠ Revisión
                          </span>)}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${VISIBILITY_BADGE[visLevel]}`}>
                        {visibilityLabel(visLevel)}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {t.author_name ?? '—'}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[status] ?? ''}`}>
                        {STATUS_LABEL[status] ?? status}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-xs text-text-secondary dark:text-text-dark-secondary">
                      {formatDate(t.delivery_deadline)}
                    </TableCell>
                  </TableRow>);
        })}
            </TableBody>
          </Table>
        </div>

        {meta && (<div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-ui-border dark:border-ui-dark-border text-xs text-text-muted dark:text-text-dark-muted">
            <span>
              Página {meta.current_page} de {meta.last_page} — {meta.total} plantillas
            </span>
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="xs" disabled={loading || meta.current_page <= 1} onClick={() => goToPage(meta.current_page - 1)}>
                Anterior
              </Button>
              <Button type="button" variant="outline" size="xs" disabled={loading || meta.current_page >= meta.last_page} onClick={() => goToPage(meta.current_page + 1)}>
                Siguiente
              </Button>
            </div>
          </div>)}
      </div>
    </div>);
}
