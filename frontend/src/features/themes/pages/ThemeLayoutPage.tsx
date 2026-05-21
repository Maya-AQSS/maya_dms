import { useNavigate, useParams } from 'react-router-dom';
import { PageTitle } from '@maya/shared-ui-react';
import { ThemeLayoutEditor } from '../components/ThemeLayoutEditor';
import { useTheme } from '../hooks/useTheme';
import { useThemes } from '../hooks/useThemes';
import type { ThemeLayoutRegion } from '../../../types/themes';

export function ThemeLayoutPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { theme, loading, error, refetch } = useTheme(id);
  const { updateTheme } = useThemes();

  const handleSave = async (regions: ThemeLayoutRegion[]) => {
    if (!theme) return;
    await updateTheme(theme.id, {
      layout: { ...theme.layout, regions },
    });
    await refetch();
  };

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

  return (
    <>
      <PageTitle title="Layout del theme" subtitle={theme.name} />
      <ThemeLayoutEditor
        theme={theme}
        onSave={handleSave}
        onClose={() => navigate(`/themes/${theme.id}/edit`)}
      />
    </>
  );
}
