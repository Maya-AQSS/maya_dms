/**
 * Portada con métricas placeholder y tabla de documentos recientes.
 */
export function DashboardPage() {
  const stats = [
    { label: 'Total documentos', value: '—', color: 'bg-odoo-purple/10 text-odoo-purple border-odoo-purple/20' },
    { label: 'Subidos hoy', value: '—', color: 'bg-odoo-teal/10 text-odoo-teal border-odoo-teal/20' },
    { label: 'Pendientes revisión', value: '—', color: 'bg-warning-light text-warning-dark border-warning/20' },
    { label: 'Archivados', value: '—', color: 'bg-ui-body text-text-secondary border-ui-border' },
  ];

  return (
    <div className="p-6">
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {stats.map((s) => (
          <div key={s.label} className={`rounded-lg border p-4 shadow-card ${s.color}`}>
            <p className="text-xs uppercase tracking-wide font-medium opacity-80">{s.label}</p>
            <p className="text-3xl font-bold mt-1">{s.value}</p>
          </div>
        ))}
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Documentos recientes
          </h2>
          <button type="button" className="text-xs text-text-link dark:text-text-dark-link hover:underline">
            Ver todos
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full">
            <thead className="bg-ui-body dark:bg-ui-dark-card">
              <tr>
                {['Nombre', 'Tipo', 'Tamaño', 'Fecha', 'Estado'].map((h) => (
                  <th
                    key={h}
                    className="px-4 py-2 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary font-medium"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l">
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-sm text-text-muted dark:text-text-dark-muted">
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
