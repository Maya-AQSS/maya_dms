import { type MouseEvent, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { fetchDashboard, type TemplateReviewInboxItem } from '../api/dashboard';
import { Button, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui';

/**
 * Portada con métricas placeholder y tabla de documentos recientes.
 * Adaptado al diseño unificado de Maya Dashboard.
 */
const STATS = [
  {
    key: 'total_documents',
    label: 'Total documentos',
    value: '—',
    colorClass: 'bg-odoo-purple/10 dark:bg-odoo-purple/40 text-odoo-purple-d dark:text-white border-odoo-purple/20 dark:border-odoo-purple/50',
  },
  {
    key: 'uploaded_today',
    label: 'Subidos hoy',
    value: '—',
    colorClass: 'bg-odoo-teal/10 dark:bg-odoo-teal/40 text-odoo-teal-d dark:text-white border-odoo-teal/20 dark:border-odoo-teal/50',
  },
  {
    key: 'pending_reviews',
    label: 'Pendientes revisión',
    value: '—',
    colorClass: 'bg-warning-light dark:bg-warning-dark/50 text-warning-dark dark:text-white border-warning/20 dark:border-warning/50',
  },
  {
    key: 'archived',
    label: 'Archivados',
    value: '—',
    colorClass: 'bg-ui-body dark:bg-ui-dark-card text-text-secondary dark:text-text-dark-secondary border-ui-border dark:border-ui-dark-border',
  },
] as const;

export function DashboardPage() {
  const navigate = useNavigate();
  const [templateInbox, setTemplateInbox] = useState<TemplateReviewInboxItem[]>([]);
  const [isLoadingInbox, setIsLoadingInbox] = useState(false);

  useEffect(() => {
    let isMounted = true;
    setIsLoadingInbox(true);
    fetchDashboard()
      .then((data) => {
        if (!isMounted) return;
        setTemplateInbox(data.template_review_inbox ?? []);
      })
      .finally(() => {
        if (!isMounted) return;
        setIsLoadingInbox(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const urgencyClass = (daysRemaining: number | null) => {
    if (daysRemaining === null) {
      return 'bg-ui-body dark:bg-ui-dark-bg text-text-secondary dark:text-text-dark-secondary';
    }
    if (daysRemaining <= 3) {
      return 'bg-error-light/20 text-error-dark dark:bg-error-dark/30 dark:text-white';
    }
    if (daysRemaining <= 7) {
      return 'bg-warning-light text-warning-dark dark:bg-warning-dark/40 dark:text-white';
    }
    return 'bg-ui-body dark:bg-ui-dark-bg text-text-secondary dark:text-text-dark-secondary';
  };

  const remainingLabel = (daysRemaining: number | null) => {
    if (daysRemaining === null) return 'Sin plazo';
    if (daysRemaining < 0) return `Vencida hace ${Math.abs(daysRemaining)} días`;
    if (daysRemaining === 0) return 'Vence hoy';
    if (daysRemaining === 1) return '1 día restante';
    return `${daysRemaining} días restantes`;
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
      return { ...stat, value: String(templateInbox.length) };
    }
    return stat;
  });

  return (
    <div className="p-6">
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
                    onClick={() => navigate(`/templates/${item.template_id}/review`)}
                  >
                    <TableCell className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary">
                      <Link
                        to={`/templates/${item.template_id}/review`}
                        className="hover:underline"
                        onClick={(e: MouseEvent<HTMLAnchorElement>) => e.stopPropagation()}
                      >
                        {item.title}
                      </Link>
                    </TableCell>
                    <TableCell className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary">
                      {item.author_id}
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
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Documentos recientes
          </h2>
          <Button
            type="button"
            variant="ghost"
            className="text-xs font-semibold underline-offset-2 dark:text-odoo-dark-teal dark:hover:text-odoo-dark-teal-d focus-visible:ring-odoo-dark-teal/50 -mx-1 px-1"
          >
            Ver todos
          </Button>
        </div>

        <div className="overflow-x-auto">
          <Table>
            <TableHead>
              <TableRow>
                {['Nombre', 'Tipo', 'Tamaño', 'Fecha', 'Estado'].map((h) => (
                  <TableHeader key={h}>
                    {h}
                  </TableHeader>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              <TableRow>
                <TableCell
                  colSpan={5}
                  className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-primary/85"
                >
                  No hay documentos todavía.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}
