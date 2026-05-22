import { Card } from '@maya/shared-ui-react';

/**
 * Secciones del menú aún sin implementar (subir, búsqueda, etc.).
 */
export function PlaceholderPage() {
  return (
    <div className="p-6">
      <Card padding="lg">
        <p className="text-text-muted dark:text-text-dark-muted text-sm">
          Selecciona una opción del menú lateral.
        </p>
      </Card>
    </div>
  );
}
