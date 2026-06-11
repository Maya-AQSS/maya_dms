import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import { useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import { ThemeWizardStepIdentity, type ThemeIdentityValue } from './ThemeWizardStepIdentity';
import { ThemeGridEditor } from './ThemeGridEditor';
import { ThemeVerificationStep } from './ThemeVerificationStep';
import { useThemes } from '../hooks/useThemes';
import type { Theme, ThemeLayoutRegion } from '../../../types/themes';

type Step = 'identity' | 'layout' | 'verification';

interface ThemeWizardProps {
  /** Theme inicial (modo edición). `null` significa creación. */
  initial: Theme | null;
}

const NEW_DEFAULTS: ThemeIdentityValue = {
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

function themeToIdentity(t: Theme): ThemeIdentityValue {
  return {
    name: t.name,
    description: t.description ?? '',
    palette: t.palette,
    typography: t.typography,
    accessibility: t.accessibility,
  };
}

/**
 * Wizard de Theme (crear/editar). Mismo patrón que `TemplateWizard`:
 *   Paso 1 — Identidad (info estática: nombre, paleta, tipografía, a11y, assets).
 *   Paso 2 — Layout (editor drag-and-drop con Puck).
 *
 * Cuando el theme aún no existe (creación), el paso 1 lo crea en backend al
 * pulsar "Guardar y continuar →" y avanza al paso 2 con el theme ya
 * persistido. En modo edición el botón guarda los cambios del paso 1 y
 * avanza al editor visual.
 */
export function ThemeWizard({ initial }: ThemeWizardProps) {
  const { t } = useTranslation('themes');
  const navigate = useNavigate();
  const location = useLocation();
  const { goBack } = useBackNavigation({ fallback: '/themes' });
  const {
    createTheme,
    updateTheme,
    publishTheme,
    archiveTheme,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
  } = useThemes();

  const [theme, setTheme] = useState<Theme | null>(initial);
  const [identity, setIdentity] = useState<ThemeIdentityValue>(
    initial ? themeToIdentity(initial) : NEW_DEFAULTS,
  );
  const [step, setStep] = useState<Step>('identity');
  const [completedSteps, setCompletedSteps] = useState<Step[]>(initial ? ['identity'] : []);
  const [saving, setSaving] = useState(false);

  const stepsData: WizardStepDef<Step>[] = [
    { id: 'identity', label: 'Propiedades', sub: 'Nombre, paleta, tipografía' },
    { id: 'layout', label: 'Layout', sub: 'Editor visual del documento' },
    { id: 'verification', label: 'Verificación', sub: 'Previsualizar y publicar' },
  ];

  const persistIdentity = async (): Promise<Theme | null> => {
    clearActionError();
    clearActionInfo();
    setSaving(true);
    try {
      if (theme) {
        const updated = await updateTheme(theme.id, {
          name: identity.name,
          description: identity.description || null,
          palette: identity.palette,
          typography: identity.typography,
          accessibility: identity.accessibility,
        });
        setTheme(updated);
        return updated;
      }
      const created = await createTheme({
        name: identity.name,
        description: identity.description || null,
        palette: identity.palette,
        typography: identity.typography,
        accessibility: identity.accessibility,
      });
      setTheme(created);
      // Sincroniza la URL con el ID real ahora que el theme existe. Reenvía el
      // state para no perder la pila backTo del listado de origen.
      navigate(`/themes/${created.id}/edit`, { replace: true, state: location.state });
      return created;
    } catch {
      // El error ya queda en `actionError` del hook compartido — el banner lo muestra.
      return null;
    } finally {
      setSaving(false);
    }
  };

  const persistLayout = async (regions: ThemeLayoutRegion[]) => {
    if (!theme) return;
    clearActionError();
    clearActionInfo();
    setSaving(true);
    try {
      const updated = await updateTheme(theme.id, {
        layout: { ...theme.layout, regions },
      });
      setTheme(updated);
    } finally {
      setSaving(false);
    }
  };

  const doPublish = async () => {
    if (!theme) return;
    clearActionError();
    clearActionInfo();
    setSaving(true);
    try {
      await publishTheme(theme.id);
      goBack({ replace: true });
    } finally {
      setSaving(false);
    }
  };

  const doArchive = async () => {
    if (!theme) return;
    clearActionError();
    clearActionInfo();
    setSaving(true);
    try {
      await archiveTheme(theme.id);
      goBack({ replace: true });
    } finally {
      setSaving(false);
    }
  };

  const handleContinue = async () => {
    if (step === 'identity') {
      if (!identity.name.trim()) {
        return;
      }
      const persisted = await persistIdentity();
      if (!persisted) return;
      setCompletedSteps((prev) => Array.from(new Set([...prev, 'identity'])) as Step[]);
      setStep('layout');
    } else if (step === 'layout') {
      setCompletedSteps((prev) => Array.from(new Set([...prev, 'identity', 'layout'])) as Step[]);
      setStep('verification');
    }
  };

  const handleGoToStep = (s: Step) => {
    if (s === 'identity') {
      setStep('identity');
      return;
    }
    if (s === 'layout' && completedSteps.includes('identity')) {
      setStep('layout');
      return;
    }
    if (s === 'verification' && completedSteps.includes('layout')) {
      setStep('verification');
    }
  };

  const handleBack = () => {
    if (step === 'verification') {
      setStep('layout');
      return;
    }
    if (step === 'layout') {
      setStep('identity');
      return;
    }
    goBack();
  };

  const headerActions = (
    <>
      {step === 'identity' && (
        <Button
          variant="primary"
          size="sm"
          loading={saving}
          disabled={!identity.name.trim()}
          onClick={() => void handleContinue()}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Guardar y continuar →
        </Button>
      )}
      {step === 'layout' && (
        <>
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate('/themes')}
            className="border-odoo-teal text-odoo-teal hover:bg-odoo-teal/10"
          >
            Guardar y salir
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={() => void handleContinue()}
            className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
          >
            Verificar →
          </Button>
        </>
      )}
      {step === 'verification' && (
        <>
          <Button
            variant="outline"
            size="sm"
            onClick={() => navigate('/themes')}
            className="border-odoo-teal text-odoo-teal hover:bg-odoo-teal/10"
          >
            Guardar y salir
          </Button>
          {theme?.status === 'draft' && (
            <Button
              variant="primary"
              size="sm"
              loading={saving}
              onClick={() => void doPublish()}
              className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
            >
              Publicar
            </Button>
          )}
          {theme?.status === 'published' && (
            <Button
              variant="outline"
              size="sm"
              loading={saving}
              onClick={() => void doArchive()}
            >
              Archivar
            </Button>
          )}
        </>
      )}
    </>
  );

  const banner = (
    <>
      {actionError && (
        <div className="flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-danger-dark">⚠️ {actionError}</span>
          <Button variant="ghost" size="xs" onClick={clearActionError} aria-label={t('closeErrorMessage')}>
            ✕
          </Button>
        </div>
      )}
      {actionInfo && step === 'identity' && (
        <div className="flex items-center gap-4 px-6 py-2 border-b border-success bg-success-light text-sm text-success-dark">
          <span className="flex-1">{actionInfo}</span>
          <button type="button" onClick={clearActionInfo} className="underline text-xs">
            cerrar
          </button>
        </div>
      )}
    </>
  );

  return (
    <WizardShell<Step>
      title={theme ? theme.name : 'Nuevo theme'}
      subtitle={theme ? 'Editar theme' : 'Identidad visual reutilizable'}
      onBack={handleBack}
      actions={headerActions}
      steps={stepsData}
      currentStep={step}
      completedSteps={completedSteps}
      onGoToStep={handleGoToStep}
      banner={banner}
    >
      {step === 'identity' && (
        <ThemeWizardStepIdentity
          value={identity}
          onChange={setIdentity}
          theme={theme}
        />
      )}
      {step === 'layout' && theme && (
        <div className="flex-1 min-h-0">
          <ThemeGridEditor theme={theme} onSave={persistLayout} embedded />
        </div>
      )}
      {step === 'verification' && theme && (
        <div className="flex flex-1 min-h-0">
          <ThemeVerificationStep theme={theme} />
        </div>
      )}
    </WizardShell>
  );
}
