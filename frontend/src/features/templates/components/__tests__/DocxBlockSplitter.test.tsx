import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { DocxBlockSplitter } from '../DocxBlockSplitter';

// --- Mocks del paquete compartido (conversión + troceo dominio-agnósticos) ---
const docxToHtmlResult = vi.fn();
const splitHtmlIntoBlocks = vi.fn();
vi.mock('@ceedcv-maya/shared-editor-react', () => ({
  docxToHtmlResult: (file: File) => docxToHtmlResult(file),
  splitHtmlIntoBlocks: (html: string) => splitHtmlIntoBlocks(html),
}));

// --- i18n: t devuelve la clave (suficiente para aserciones) ---
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (k: string) => k }),
}));

// --- Sub-componentes: dobles mínimos que exponen los callbacks clave ---
vi.mock('../DocxBlockSplitter/Header', () => ({
  Header: ({ filename }: { filename: string | null }) => <div data-testid="header">{filename}</div>,
}));
vi.mock('../DocxBlockSplitter/FileUploadSection', () => ({
  FileUploadSection: ({
    error,
    onPickFile,
  }: {
    error: string | null;
    onPickFile: (f: File) => void;
  }) => (
    <div data-testid="upload-section">
      {error && <p data-testid="upload-error">{error}</p>}
      <button type="button" onClick={() => onPickFile(new File(['x'], 'doc.docx'))}>
        pick
      </button>
    </div>
  ),
}));
vi.mock('../DocxBlockSplitter/ReadyContentPanel', () => ({
  ReadyContentPanel: () => <div data-testid="ready-panel" />,
}));
vi.mock('../DocxBlockSplitter/Footer', () => ({
  Footer: ({ canConfirm, onConfirm }: { canConfirm: boolean; onConfirm: () => void }) => (
    <button type="button" data-testid="confirm" disabled={!canConfirm} onClick={onConfirm}>
      confirm
    </button>
  ),
}));

function setup(overrides: Partial<Parameters<typeof DocxBlockSplitter>[0]> = {}) {
  const onCancel = vi.fn();
  const onConfirm = vi.fn().mockResolvedValue({ createdCount: 1 });
  render(<DocxBlockSplitter open onCancel={onCancel} onConfirm={onConfirm} {...overrides} />);
  return { onCancel, onConfirm };
}

describe('DocxBlockSplitter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    docxToHtmlResult.mockResolvedValue({ html: '<h1>A</h1><p>b</p>', messages: [] });
    splitHtmlIntoBlocks.mockReturnValue([
      { index: 0, type: 'heading', html: '<h1>A</h1>', isEmpty: false },
      { index: 1, type: 'paragraph', html: '<p>b</p>', isEmpty: false },
    ]);
  });

  it('renders nothing when closed', () => {
    const onCancel = vi.fn();
    const onConfirm = vi.fn().mockResolvedValue({ createdCount: 0 });
    const { container } = render(
      <DocxBlockSplitter open={false} onCancel={onCancel} onConfirm={onConfirm} />,
    );
    expect(container.firstChild).toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('renders the dialog with the upload section when open', () => {
    setup();
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByTestId('upload-section')).toBeTruthy();
  });

  it('parses a picked file and transitions to the ready panel', async () => {
    setup();
    fireEvent.click(screen.getByText('pick'));
    await waitFor(() => {
      expect(docxToHtmlResult).toHaveBeenCalledTimes(1);
      expect(splitHtmlIntoBlocks).toHaveBeenCalledWith('<h1>A</h1><p>b</p>');
      expect(screen.getByTestId('ready-panel')).toBeTruthy();
    });
    // El nombre del fichero pasa a la cabecera.
    expect(screen.getByTestId('header').textContent).toBe('doc.docx');
  });

  it('shows an error and stays on the upload section when the DOCX is corrupt', async () => {
    docxToHtmlResult.mockRejectedValueOnce(new Error('corrupt zip'));
    setup();
    fireEvent.click(screen.getByText('pick'));
    await waitFor(() => {
      expect(screen.getByTestId('upload-error').textContent).toBe('docx.readError');
    });
    expect(screen.queryByTestId('ready-panel')).toBeNull();
  });

  it('keeps confirm disabled until at least one target block exists', () => {
    setup();
    expect((screen.getByTestId('confirm') as HTMLButtonElement).disabled).toBe(true);
  });
});
