import { Navigate, useParams } from 'react-router-dom';

/**
 * Legacy URL — el editor de layout ahora vive como paso 2 del wizard de Themes.
 * Redirigimos a la página de edición para no romper enlaces antiguos.
 */
export function ThemeLayoutPage() {
  const { id } = useParams<{ id: string }>();
  if (!id) return <Navigate to="/themes" replace />;
  return <Navigate to={`/themes/${id}/edit`} replace />;
}
