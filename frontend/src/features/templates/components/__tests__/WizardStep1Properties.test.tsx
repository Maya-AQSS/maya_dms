import type { ReactElement } from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import { FormProvider, useForm } from 'react-hook-form';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { describe, it, expect, vi, beforeAll, beforeEach } from 'vitest';
import i18n from '../../../../i18n';
import { WizardStep1Properties } from '../WizardStep1Properties';
import { UserProfileProvider } from '../../../../features/user-profile';
import {
  emptyTemplateStep1,
  type TemplateStep1Input,
} from '../../schemas/templateStep1';

vi.mock('../../../../api/users', () => ({
  fetchMe: vi.fn().mockResolvedValue({
    data: {
      id: 'usr_test_fixture',
      email: null,
      name: null,
      department: null,
      study_type_ids: [],
      study_ids: [],
      module_ids: [],
      team_ids: [],
      permissions: [],
      locale: 'es',
      source: 'fdw' as const,
    },
  }),
}));

import { useHierarchy } from '../../../../features/hierarchy';

vi.mock('../../../../features/hierarchy', () => ({
  useHierarchy: vi.fn(() => ({ hierarchy: [], teams: [], loading: false, error: null })),
  HierarchyProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

function Harness({
  defaults,
  errors,
  capture,
}: {
  defaults?: Partial<TemplateStep1Input>;
  errors?: { api?: string };
  capture?: (values: TemplateStep1Input) => void;
}) {
  const methods = useForm<TemplateStep1Input>({
    defaultValues: { ...emptyTemplateStep1, ...defaults },
  });
  if (capture) {
    capture(methods.getValues());
    methods.watch((values) => capture(values as TemplateStep1Input));
  }
  return (
    <FormProvider {...methods}>
      <WizardStep1Properties errors={errors} />
    </FormProvider>
  );
}

async function renderWithProfile(ui: ReactElement) {
  let renderResult: ReturnType<typeof render> | null = null;
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  await act(async () => {
    renderResult = render(
      <QueryClientProvider client={queryClient}>
        <UserProfileProvider>{ui}</UserProfileProvider>
      </QueryClientProvider>,
    );
  });
  return renderResult!;
}

describe('WizardStep1Properties', () => {
  // `usePublishedThemes` usa react-query y el componente resuelve textos vía
  // `t('templates:...')`; sin QueryClient ni i18n el render aborta o devuelve
  // claves. jsdom expone `navigator.language = 'en-US'`, así que forzamos 'es'.
  beforeAll(async () => {
    await i18n.changeLanguage('es');
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders correctly with default props', async () => {
    await renderWithProfile(<Harness />);
    expect(screen.getAllByPlaceholderText(/Nombre de la plantilla/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Visibilidad/i)).toBeTruthy();
  });

  it('shows api error message when passed via prop', async () => {
    await renderWithProfile(<Harness errors={{ api: 'Algo falló' }} />);
    expect(screen.getByText(/Algo falló/i)).toBeTruthy();
  });

  it('updates name via RHF register on input change', async () => {
    const ref: { current: TemplateStep1Input | null } = { current: null };
    await renderWithProfile(<Harness capture={(v) => { ref.current = v; }} />);
    const [input] = screen.getAllByPlaceholderText(/Nombre de la plantilla/i);
    await act(async () => {
      fireEvent.input(input, { target: { value: 'Nueva Plantilla' } });
    });
    expect(ref.current?.name).toBe('Nueva Plantilla');
  });

  it('hides academic hierarchy block when visibility is personal', async () => {
    await renderWithProfile(<Harness defaults={{ visibility: 'personal' }} />);
    expect(screen.queryByText('— Seleccionar —')).toBeNull();
  });

  it('shows academic hierarchy block when visibility requires it', async () => {
    await renderWithProfile(<Harness defaults={{ visibility: 'study_type' }} />);
    expect(screen.getByText('No tienes tipos de estudio asignados, contacta con un administrador')).toBeTruthy();
  });

  it('auto-selects study type if only one is available', async () => {
    vi.mocked(useHierarchy).mockReturnValue({
      hierarchy: [{ id: 'ST_1', name: 'Único Tipo', studies: [] }],
      loading: false,
      error: null,
    } as any);

    const ref: { current: TemplateStep1Input | null } = { current: null };
    await renderWithProfile(
      <Harness defaults={{ visibility: 'study_type' }} capture={(v) => { ref.current = v; }} />,
    );
    // useEffect autoselect runs after mount
    await act(async () => {
      await Promise.resolve();
    });
    expect(ref.current?.studyTypeId).toBe('ST_1');
  });
});
