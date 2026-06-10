import type { ReactElement } from 'react';
import { render, screen, fireEvent, act, waitFor, cleanup } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import i18n from '../../i18n';
import { VersionComparePanel, type CompareVersionOption } from '../VersionComparePanel';
import type { ComparableBlock } from '../../features/documents/lib/versionBlockCompare';

const mockLoad = vi.fn();

vi.mock('../../features/documents/lib/loadVersionComparable', () => ({
  loadVersionComparable: (...args: unknown[]) => mockLoad(...args),
}));

const para = (text: string) => [
  { type: 'paragraph', content: [{ type: 'text', text }] },
];

const cblock = (over: Partial<ComparableBlock> & { key: string }): ComparableBlock => ({
  title: null,
  content: null,
  sortOrder: 0,
  ...over,
});

/** Dos versiones publicadas, orden descendente (como las pasa VersionHistoryPanel). */
const TWO: CompareVersionOption[] = [
  { id: 'vB', versionNumber: 2 },
  { id: 'vA', versionNumber: 1 },
];

async function renderPanel(versions: CompareVersionOption[], ui?: ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  let result: ReturnType<typeof render> | null = null;
  await act(async () => {
    result = render(
      <QueryClientProvider client={queryClient}>
        {ui ?? (
          <VersionComparePanel entityType="document" entityId="doc-1" versions={versions} />
        )}
      </QueryClientProvider>,
    );
  });
  return result!;
}

describe('VersionComparePanel', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('es');
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    cleanup();
  });

  it('shows the "need two versions" message and skips fetching with <2 versions', async () => {
    await renderPanel([{ id: 'vA', versionNumber: 1 }]);
    expect(screen.getByText(/al menos dos versiones publicadas/i)).toBeTruthy();
    expect(mockLoad).not.toHaveBeenCalled();
  });

  it('renders the block diff between the two selected versions', async () => {
    mockLoad.mockImplementation((_type: string, _id: string, versionId: string) =>
      Promise.resolve(
        versionId === 'vA'
          ? { versionNumber: 1, blocks: [cblock({ key: 't1', title: 'Intro', content: para('Viejo') })] }
          : { versionNumber: 2, blocks: [cblock({ key: 't1', title: 'Intro', content: para('Nuevo') })] },
      ),
    );

    await renderPanel(TWO);

    // Dirección del diff: de la versión menor (1) a la mayor (2).
    await waitFor(() => expect(screen.getByText(/Cambios de v1 a v2/i)).toBeTruthy());
    expect(screen.getByText(/Bloque 1: Intro/i)).toBeTruthy();
    expect(screen.getByText('Modificado')).toBeTruthy();
    expect(screen.getByText('Viejo')).toBeTruthy();
    expect(screen.getByText('Nuevo')).toBeTruthy();
    // Una carga por lado seleccionado.
    expect(mockLoad).toHaveBeenCalledWith('document', 'doc-1', 'vA');
    expect(mockLoad).toHaveBeenCalledWith('document', 'doc-1', 'vB');
  });

  it('prompts to pick two distinct versions when both selects match', async () => {
    mockLoad.mockResolvedValue({ versionNumber: 1, blocks: [cblock({ key: 't1', content: para('x') })] });
    await renderPanel(TWO);

    const [selectA] = screen.getAllByRole('combobox') as HTMLSelectElement[];
    await act(async () => {
      fireEvent.change(selectA, { target: { value: 'vB' } });
    });

    await waitFor(() => expect(screen.getByText(/dos versiones distintas/i)).toBeTruthy());
    expect(screen.queryByText(/Cambios de v/i)).toBeNull();
  });

  it('shows an error message when a version fails to load', async () => {
    mockLoad.mockRejectedValue(new Error('boom'));
    await renderPanel(TWO);
    await waitFor(() =>
      expect(screen.getByText(/No se pudieron cargar las versiones/i)).toBeTruthy(),
    );
  });

  it('reports no differences when both versions have identical content', async () => {
    mockLoad.mockResolvedValue({
      versionNumber: 1,
      blocks: [cblock({ key: 't1', title: 'Intro', content: para('Igual') })],
    });
    await renderPanel(TWO);
    await waitFor(() => expect(screen.getByText(/No hay diferencias de contenido/i)).toBeTruthy());
  });
});
