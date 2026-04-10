import { Button } from '../ui';

/**
 * Portada con métricas placeholder y tabla de documentos recientes.
 * En modo oscuro: texto siempre claro sobre tarjeta oscura + acento en borde izquierdo (mejor contraste WCAG).
 */
const STATS = [
  {
    label: 'Total documentos',
    value: '—',
    light: 'bg-odoo-purple/10 text-odoo-purple border-odoo-purple/20',
    dark: 'dark:bg-ui-dark-card dark:border-ui-dark-border dark:border-l-4 dark:border-l-odoo-purple-l dark:text-text-dark-primary',
  },
  {
    label: 'Subidos hoy',
    value: '—',
    light: 'bg-odoo-teal/10 text-odoo-teal border-odoo-teal/20',
    dark: 'dark:bg-ui-dark-card dark:border-ui-dark-border dark:border-l-4 dark:border-l-odoo-dark-teal dark:text-text-dark-primary',
  },
  {
    label: 'Pendientes revisión',
    value: '—',
    light: 'bg-warning-light text-warning-dark border-warning/25',
    dark: 'dark:bg-ui-dark-card dark:border-ui-dark-border dark:border-l-4 dark:border-l-warning dark:text-text-dark-primary',
  },
  {
    label: 'Archivados',
    value: '—',
    light: 'bg-ui-body text-text-secondary border-ui-border',
    dark: 'dark:bg-ui-dark-card dark:border-ui-dark-border dark:border-l-4 dark:border-l-zinc-500 dark:text-text-dark-primary',
  },
] as const;

export function DashboardPage() {
  return (
    <div className="p-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {STATS.map((s) => (
          <div
            key={s.label}
            className={`rounded-lg border p-4 shadow-card ${s.light} ${s.dark}`}
          >
            <p className="text-xs uppercase tracking-wide font-semibold opacity-90 dark:opacity-100 dark:text-text-dark-primary">
              {s.label}
            </p>
            <p className="text-3xl font-bold mt-1 tabular-nums dark:text-text-dark-primary">{s.value}</p>
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
