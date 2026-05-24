import { Component, type ReactNode } from 'react';

type Props = { children: ReactNode };
type State = { message: string | null };

/**
 * Captura errores de render del listado de plantillas y muestra el mensaje real
 * (el ErrorBoundary genérico de shared-ui solo muestra texto fijo).
 */
export class TemplatesTableBoundary extends Component<Props, State> {
  state: State = { message: null };

  static getDerivedStateFromError(error: unknown): State {
    const message =
      error instanceof Error ? error.message : 'Error desconocido al renderizar plantillas.';
    return { message };
  }

  render() {
    if (this.state.message) {
      return (
        <div
          role="alert"
          className="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger-dark dark:text-danger"
        >
          Error al cargar plantillas: {this.state.message}
        </div>
      );
    }
    return this.props.children;
  }
}
