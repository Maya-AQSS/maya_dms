import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { BlockContentHtml } from '../BlockContentHtml';

describe('BlockContentHtml', () => {
  it('renders empty for null content without error', () => {
    render(<BlockContentHtml content={null as any} />);
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders empty for empty array without error', () => {
    render(<BlockContentHtml content={[]} />);
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders a bare TipTap content array', () => {
    // Shape emitted by MayaEditorPanel and stored in default_content:
    // a bare array of ProseMirror nodes.
    const tiptapContentArray = [
      { type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'Introducción' }] },
      { type: 'paragraph', content: [{ type: 'text', text: 'Contenido importado.' }] },
    ];
    render(<BlockContentHtml content={tiptapContentArray} />);
    expect(screen.getByText('Introducción')).toBeTruthy();
    expect(screen.getByText('Contenido importado.')).toBeTruthy();
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });

  it('renders a wrapped TipTap doc', () => {
    const tiptapDoc = {
      type: 'doc' as const,
      content: [
        { type: 'paragraph', content: [{ type: 'text', text: 'Hello world.' }] },
      ],
    };
    render(<BlockContentHtml content={tiptapDoc} />);
    expect(screen.getByText('Hello world.')).toBeTruthy();
    expect(screen.queryByText(/Error al renderizar/)).toBeNull();
  });
});
