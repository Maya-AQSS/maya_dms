import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Button, PageTitle } from '@maya/shared-ui-react';
import { ThemeForm, type ThemeFormValue } from '../components/ThemeForm';
import { ThemeAssetsSection } from '../components/ThemeAssetsSection';
import { useTheme } from '../hooks/useTheme';
import { useThemes } from '../hooks/useThemes';
import type { Theme } from '../../../types/themes';

export function ThemeEditPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const { theme: serverTheme, loading, error } = useTheme(id);
  const { updateTheme, actionError, clearActionError, actionInfo, clearActionInfo } = useThemes();

  // Estado local del theme para reflejar uploads de assets sin re-fetch.
  const [localTheme, setLocalTheme] = useState<Theme | null>(null);
  const theme = localTheme ?? serverTheme;

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

  const initial: ThemeFormValue = {
    name: theme.name,
    description: theme.description ?? '',
    status: theme.status,
    palette: theme.palette,
    typography: theme.typography,
    accessibility: theme.accessibility,
  };

  const handleSubmit = async (value: ThemeFormValue) => {
    await updateTheme(theme.id, {
      name: value.name,
      description: value.description || null,
      status: value.status,
      palette: value.palette,
      typography: value.typography,
      accessibility: value.accessibility,
    });
  };

  return (
    <>
      <PageTitle
        title={theme.name}
        subtitle="Editar theme"
        actions={
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/themes/${theme.id}/layout`)}
          >
            Editar layout (drag-and-drop)
          </Button>
        }
      />

      {actionError && (
        <div className="my-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="ml-3 underline">
            cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="my-3 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-700">
          <span>{actionInfo}</span>
          <button type="button" onClick={clearActionInfo} className="ml-3 underline">
            cerrar
          </button>
        </div>
      )}

      <ThemeAssetsSection theme={theme} onUploaded={setLocalTheme} />

      <ThemeForm
        initial={initial}
        submitLabel="Guardar cambios"
        onSubmit={handleSubmit}
        onCancel={() => navigate('/themes')}
        showStatus
        showLayoutTodo
      />
    </>
  );
}
