/**
 * Secciones del menú aún sin implementar (subir, búsqueda, etc.).
 */
export function PlaceholderPage() {
  return (
    <div className="p-6">
      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-8 text-center">
        <p className="text-text-muted dark:text-text-dark-muted text-sm">
          Selecciona una opción del menú lateral.
        </p>
      </div>
    </div>
  );
}
