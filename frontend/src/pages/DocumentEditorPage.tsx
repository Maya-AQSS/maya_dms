import { Link, useParams } from 'react-router-dom';
import { Button } from '../ui';

/**
 * Pantalla mínima de editor para soportar redirección tras crear documento.
 * Se sustituirá por el editor completo en iteraciones siguientes.
 */
export function DocumentEditorPage() {
  const { documentId } = useParams<{ documentId: string }>();

  return (
    <div className="p-6">
      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6 space-y-4">
        <h2 className="text-lg font-semibold text-text-primary dark:text-text-dark-primary">
          Editor de Programación
        </h2>
        <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
          Documento: <span className="font-mono">{documentId}</span>
        </p>
        <p className="text-sm text-text-muted dark:text-text-dark-muted">
          Pantalla mínima de destino tras crear. El editor completo se implementará en otra tarea.
        </p>
        <Link to="/documents">
          <Button variant="secondary">Volver al listado</Button>
        </Link>
      </div>
    </div>
  );
}

