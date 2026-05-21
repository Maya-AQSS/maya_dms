import { useParams } from 'react-router-dom';
import { ThemeWizard } from '../components/ThemeWizard';
import { useTheme } from '../hooks/useTheme';

export function ThemeEditPage() {
  const { id } = useParams<{ id: string }>();
  const { theme, loading, error } = useTheme(id);

  if (loading && !theme) {
    return <p className="p-4 text-sm">Cargando theme…</p>;
  }

  if (error || !theme) {
    return (
      <div className="m-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
        {error || 'No se ha podido cargar el theme.'}
      </div>
    );
  }

  return <ThemeWizard initial={theme} />;
}
