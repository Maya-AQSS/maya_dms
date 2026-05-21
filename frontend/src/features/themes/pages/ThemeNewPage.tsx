import { useNavigate } from 'react-router-dom';
import { PageTitle } from '@maya/shared-ui-react';
import { ThemeForm, type ThemeFormValue } from '../components/ThemeForm';
import { useThemes } from '../hooks/useThemes';

const DEFAULTS: ThemeFormValue = {
  name: '',
  description: '',
  palette: {
    primary: '#0b5394',
    secondary: '#666666',
    text: '#1a1a1a',
    background: '#ffffff',
    accent: '#f59e0b',
  },
  typography: {
    heading_font: 'DejaVu Sans, Liberation Sans, sans-serif',
    body_font: 'DejaVu Sans, Liberation Sans, sans-serif',
    base_size_pt: 11,
    line_height: 1.5,
  },
  accessibility: {
    language: 'es',
    title: null,
    subject: null,
    author: 'CEEDCV',
  },
};

export function ThemeNewPage() {
  const navigate = useNavigate();
  const { createTheme, actionError, clearActionError } = useThemes();

  const handleSubmit = async (value: ThemeFormValue) => {
    const created = await createTheme({
      name: value.name,
      description: value.description || null,
      palette: value.palette,
      typography: value.typography,
      accessibility: value.accessibility,
    });
    navigate(`/themes/${created.id}/edit`);
  };

  return (
    <>
      <PageTitle title="Nuevo theme" subtitle="Define identidad visual reutilizable" />

      {actionError && (
        <div className="my-3 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="ml-3 underline">
            cerrar
          </button>
        </div>
      )}

      <ThemeForm
        initial={DEFAULTS}
        submitLabel="Crear theme"
        onSubmit={handleSubmit}
        onCancel={() => navigate('/themes')}
        showLayoutTodo
      />
    </>
  );
}
