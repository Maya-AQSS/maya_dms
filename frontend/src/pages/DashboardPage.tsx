import { type MouseEvent, useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { fetchDashboard, type DocumentReviewInboxItem, type TemplateReviewInboxItem } from '../api/dashboard';
import { Button, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui';

/**
 * Portada con métricas placeholder, bandeja de plantillas y documentos pendientes de validar.
 * Adaptado al diseño unificado de Maya Dashboard.
 */
const STATS = [
  {
    key: 'total_documents',
    label: 'Total documentos',
    value: '—',
    colorClass: 'bg-odoo-purple/10 dark:bg-odoo-purple/40 text-odoo-purple-d dark:text-text-dark-primary border-odoo-purple/20 dark:border-odoo-purple/50',
  },
  {
    key: 'uploaded_today',
    label: 'Subidos hoy',
    value: '—',
    colorClass: 'bg-odoo-teal/10 dark:bg-odoo-teal/40 text-odoo-teal-d dark:text-text-dark-primary border-odoo-teal/20 dark:border-odoo-teal/50',
  },
  {
    key: 'pending_reviews',
    label: 'Pendientes revisión',
    value: '—',
    colorClass: 'bg-warning-light dark:bg-warning-dark/50 text-warning-dark dark:text-text-dark-primary border-warning/20 dark:border-warning/50',
  },
  {
    key: 'archived',
    label: 'Archivados',
    value: '—',
    colorClass: 'bg-ui-body dark:bg-ui-dark-card text-text-secondary dark:text-text-dark-secondary border-ui-border dark:border-ui-dark-border',
  },
] as const;

type DashboardLocationState = {
  documentValidationBanner?: string;
} | null;

export function DashboardPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const [templateInbox, setTemplateInbox] = useState<TemplateReviewInboxItem[]>([]);
  const [documentInbox, setDocumentInbox] = useState<DocumentReviewInboxItem[]>([]);
  const [isLoadingInbox, setIsLoadingInbox] = useState(false);
  const [validationBanner, setValidationBanner] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    setIsLoadingInbox(true);
    fetchDashboard()
      .then((data) => {
        if (!isMounted) return;
        setTemplateInbox(data.template_review_inbox ?? []);
        setDocumentInbox(data.document_review_inbox ?? []);
      })
      .finally(() => {
        if (!isMounted) return;
        setIsLoadingInbox(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    const banner = (location.state as DashboardLocationState)?.documentValidationBanner;
    if (!banner) return;
    setValidationBanner(banner);
    setIsLoadingInbox(true);
    fetchDashboard()
      .then((data) => {
        setTemplateInbox(data.template_review_inbox ?? []);
        setDocumentInbox(data.document_review_inbox ?? []);
      })
      .finally(() => {
        setIsLoadingInbox(false);
      });
    navigate(location.pathname, { replace: true, state: {} });
  }, [location.state, location.pathname, navigate]);

  /** Días calendario enteros (el API puede enviar decimales por diff horario). */
  const wholeDays = (daysRemaining: number | null): number | null =>
    daysRemaining === null ? null : Math.round(daysRemaining);

  const urgencyClass = (daysRemaining: number | null) => {
    const d = wholeDays(daysRemaining);
    if (d === null) {
      return 'bg-ui-body dark:bg-ui-dark-bg text-text-secondary dark:text-text-dark-secondary';
    }
    if (d <= 3) {
      return 'bg-danger-light/20 text-danger-dark dark:bg-danger-dark/30 dark:text-text-dark-primary';
    }
    if (d <= 7) {
      return 'bg-warning-light text-warning-dark dark:bg-warning-dark/40 dark:text-text-dark-primary';
    }
    return 'bg-ui-body dark:bg-ui-dark-bg text-text-secondary dark:text-text-dark-secondary';
  };

  const remainingLabel = (daysRemaining: number | null) => {
    const d = wholeDays(daysRemaining);
    if (d === null) return 'Sin plazo';
    if (d < 0) return `Vencida hace ${Math.abs(d)} días`;
    if (d === 0) return 'Vence hoy';
    if (d === 1) return '1 día restante';
    return `${d} días restantes`;
  };

  const formatDeadline = (deadline: string | null) => {
    if (!deadline) return 'Sin fecha';
    try {
      return new Date(deadline).toLocaleDateString('es-ES');
    } catch {
      return 'Sin fecha';
    }
  };

  const stats = STATS.map((stat) => {
    if (stat.key === 'pending_reviews') {
      return { ...stat, value: String(templateInbox.length + documentInbox.length) };
    }
    return stat;
  });

  return (
    <div className="p-6">
      {validationBanner && (
        <div
          className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-success/30 bg-success/10 px-4 py-3 dark:bg-success/15 dark:border-success/40"
          role="status"
        >
          <p className="text-sm font-medium text-success-dark dark:text-success">{validationBanner}</p>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="shrink-0 text-success-dark dark:text-success"
            onClick={() => setValidationBanner(null)}
          >
            Cerrar
          </Button>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {stats.map((s) => (
          <div
            key={s.key}
            className={`rounded-lg border p-5 shadow-card ${s.colorClass}`}
          >
            <p className="text-xs uppercase tracking-wide font-medium opacity-75">
              {s.label}
            </p>
            <p className="text-3xl font-bold mt-1 tabular-nums">{s.value}</p>
          </div>
        ))}
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden mb-6">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Bandeja de revisión de plantillas
          </h2>
        </div>

        <div className="overflow-x-auto">
          <Table>
            <TableHead>
              <TableRow>
                {['Plantilla', 'Autor', 'Fecha límite', 'Urgencia'].map((h) => (
                  <TableHeader key={h}>
                    {h}
                  </TableHeader>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {isLoadingInbox ? (
                <TableRow>
                  <TableCell
                    colSpan={4}
                    className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-secondary"
                  >
                    Cargando bandeja...
                  </TableCell>
                </TableRow>
              ) : templateInbox.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={4}
                    className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-secondary"
                  >
                    No tienes plantillas pendientes de revisar.
                  </TableCell>
                </TableRow>
              ) : (
                templateInbox.map((item: TemplateReviewInboxItem) => (
                  <TableRow
                    key={item.template_id}
                    className="cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors"
                    onClick={() => navigate(`/templates/${item.template_id}/edit`)}
                  >
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary">
                      <Link
                        to={`/templates/${item.template_id}/edit`}
                        className="hover:underline"
                        onClick={(e: MouseEvent<HTMLAnchorElement>) => e.stopPropagation()}
                      >
                        {item.title}
                      </Link>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary">
                      {item.author_name?.trim() ? item.author_name : item.author_id}
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary">
                      {formatDeadline(item.delivery_deadline)}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs px-2 py-1 rounded-full ${urgencyClass(item.days_remaining)}`}>
                        {remainingLabel(item.days_remaining)}
                      </span>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-start justify-between gap-4">
          <div>
            <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
              Documentos recientes
            </h2>
            <p className="text-[11px] text-text-muted dark:text-text-dark-muted mt-0.5">
              Incluye programaciones pendientes de validar asignadas a ti.
            </p>
          </div>
          <Button
            type="button"
            variant="ghost"
            className="shrink-0 text-xs font-semibold underline-offset-2 dark:text-odoo-dark-teal dark:hover:text-odoo-dark-teal-d focus-visible:ring-odoo-dark-teal/50 -mx-1 px-1"
            onClick={() => navigate('/documents')}
          >
            Ver todos
          </Button>
        </div>

        <div className="overflow-x-auto">
          <Table>
            <TableHead>
              <TableRow>
                {['Nombre', 'Titular', 'Fecha límite', 'Urgencia', 'Etapa'].map((h) => (
                  <TableHeader key={h}>
                    {h}
                  </TableHeader>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {isLoadingInbox ? (
                <TableRow>
                  <TableCell
                    colSpan={5}
                    className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-secondary"
                  >
                    Cargando…
                  </TableCell>
                </TableRow>
              ) : documentInbox.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={5}
                    className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-primary/85"
                  >
                    No hay documentos pendientes de validar en tu bandeja.
                  </TableCell>
                </TableRow>
              ) : (
                documentInbox.map((item: DocumentReviewInboxItem) => (
                  <TableRow
                    key={item.review_id}
                    className="cursor-pointer hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors"
                    onClick={() => navigate(`/documents/${item.document_id}/validate`)}
                  >
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary">
                      <Link
                        to={`/documents/${item.document_id}/validate`}
                        className="hover:underline"
                        onClick={(e: MouseEvent<HTMLAnchorElement>) => e.stopPropagation()}
                      >
                        {item.title}
                      </Link>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary">
                      {item.owner_name?.trim() ? item.owner_name : item.owner_id}
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary">
                      {formatDeadline(item.delivery_deadline)}
                    </TableCell>
                    <TableCell className="px-4 py-3">
                      <span className={`text-xs px-2 py-1 rounded-full ${urgencyClass(item.days_remaining)}`}>
                        {remainingLabel(item.days_remaining)}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary tabular-nums">
                      {item.review_stage}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}
