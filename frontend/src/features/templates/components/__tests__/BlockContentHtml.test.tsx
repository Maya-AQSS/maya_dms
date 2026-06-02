import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

const blocksToHTMLLossyMock = vi.fn();

vi.mock('@blocknote/core', () => ({
  BlockNoteEditor: {
    create: vi.fn(() => ({ blocksToHTMLLossy: blocksToHTMLLossyMock })),
  },
}));

vi.mock('../../../utils/blockNoteRepair', () => ({
  repairBlockNoteBlocks: (b: any) => (Array.isArray(b) ? b : []),
}));

import { BlockContentHtml } from '../BlockContentHtml';

describe('BlockContentHtml', () => {
  it('renders empty for null content without error', async () => {
    render(<BlockContentHtml content={null as any} />);
    await waitFor(() => expect(blocksToHTMLLossyMock).not.toHaveBeenCalled());
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders empty for empty array without error', async () => {
    render(<BlockContentHtml content={[]} />);
    await waitFor(() => expect(blocksToHTMLLossyMock).not.toHaveBeenCalled());
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders empty for whitespace-only block content', async () => {
    const whitespaceBlock = {
      id: 'x',
      type: 'paragraph',
      props: {},
      content: [{ type: 'text', text: '   ', styles: {} }],
      children: [],
    };
    render(<BlockContentHtml content={[whitespaceBlock]} />);
    await waitFor(() => expect(blocksToHTMLLossyMock).not.toHaveBeenCalled());
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders a bare TipTap content array without falling back to BlockNote', async () => {
    // Shape emitted by MayaEditorPanel and stored in default_content:
    // a bare array of ProseMirror nodes (no `props`/`children`).
    const tiptapContentArray = [
      { type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'Introducción' }] },
      { type: 'paragraph', content: [{ type: 'text', text: 'Contenido importado.' }] },
    ];
    render(<BlockContentHtml content={tiptapContentArray} />);
    await waitFor(() => expect(screen.getByText('Introducción')).toBeTruthy());
    expect(screen.getByText('Contenido importado.')).toBeTruthy();
    // Must NOT be routed to the BlockNote headless editor.
    expect(blocksToHTMLLossyMock).not.toHaveBeenCalled();
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('calls blocksToHTMLLossy for non-empty content', () => {
    blocksToHTMLLossyMock.mockReturnValue('<p>hello</p>');
    const block = {
      id: 'x',
      type: 'paragraph',
      props: {},
      content: [{ type: 'text', text: 'hello', styles: {} }],
      children: [],
    };
    render(<BlockContentHtml content={[block]} />);
    expect(blocksToHTMLLossyMock).toHaveBeenCalled();
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });
});
