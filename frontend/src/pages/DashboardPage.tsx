import { Button } from '../ui';

/**
 * Portada con métricas placeholder y tabla de documentos recientes.
 * Adaptado al diseño unificado de Maya Dashboard.
 */
const STATS = [
  {
    label: 'Total documentos',
    value: '—',
    colorClass: 'bg-odoo-purple/10 dark:bg-odoo-purple/40 text-odoo-purple-d dark:text-white border-odoo-purple/20 dark:border-odoo-purple/50',
  },
  {
    label: 'Subidos hoy',
    value: '—',
    colorClass: 'bg-odoo-teal/10 dark:bg-odoo-teal/40 text-odoo-teal-d dark:text-white border-odoo-teal/20 dark:border-odoo-teal/50',
  },
  {
    label: 'Pendientes revisión',
    value: '—',
    colorClass: 'bg-warning-light dark:bg-warning-dark/50 text-warning-dark dark:text-white border-warning/20 dark:border-warning/50',
  },
  {
    label: 'Archivados',
    value: '—',
    colorClass: 'bg-ui-body dark:bg-ui-dark-card text-text-secondary dark:text-text-dark-secondary border-ui-border dark:border-ui-dark-border',
  },
] as const;

export function DashboardPage() {
  return (
    <div className="p-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {STATS.map((s) => (
          <div
            key={s.label}
            className={`rounded-lg border p-5 shadow-card ${s.colorClass}`}
          >
            <p className="text-xs uppercase tracking-wide font-medium opacity-75">
              {s.label}
            </p>
            <p className="text-3xl font-bold mt-1 tabular-nums">{s.value}</p>
          </div>
        ))}
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
          <table className="min-w-full">
            <thead className="bg-ui-body dark:bg-ui-dark-bg">
              <tr>
                {['Nombre', 'Tipo', 'Tamaño', 'Fecha', 'Estado'].map((h) => (
                  <th
                    key={h}
                    className="px-4 py-2.5 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-primary/90 font-semibold"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l bg-ui-card dark:bg-ui-dark-card">
              <tr>
                <td
                  colSpan={5}
                  className="px-4 py-8 text-center text-sm text-text-secondary dark:text-text-dark-primary/85"
                >
                  No hay documentos todavía.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
