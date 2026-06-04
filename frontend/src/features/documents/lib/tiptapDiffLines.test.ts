import { describe, expect, it } from 'vitest';
import { extractTiptapDiffLines } from './tiptapDiffLines';

describe('extractTiptapDiffLines', () => {
  it('includes top-level image nodes', () => {
    const content = [
      { type: 'paragraph', content: [{ type: 'text', text: 'Intro' }] },
      { type: 'image', attrs: { src: 'https://cdn.example.com/media/photo.png', alt: '' } },
    ];
    expect(extractTiptapDiffLines(content)).toEqual([
      'Intro',
      '[Imagen: photo.png]',
    ]);
  });

  it('includes inline images inside a paragraph', () => {
    const content = [
      {
        type: 'paragraph',
        content: [
          { type: 'text', text: 'Antes ' },
          { type: 'image', attrs: { src: '/uploads/a.jpg' } },
          { type: 'text', text: ' después' },
        ],
      },
    ];
    expect(extractTiptapDiffLines(content)).toEqual([
      'Antes [Imagen: a.jpg] después',
    ]);
  });

  it('includes bullet lists', () => {
    const content = [
      {
        type: 'bulletList',
        content: [
          {
            type: 'listItem',
            content: [
              { type: 'paragraph', content: [{ type: 'text', text: 'Uno' }] },
            ],
          },
          {
            type: 'listItem',
            content: [
              { type: 'paragraph', content: [{ type: 'text', text: 'Dos' }] },
            ],
          },
        ],
      },
    ];
    expect(extractTiptapDiffLines(content)).toEqual(['• Uno', '• Dos']);
  });

  it('includes table rows with cell text', () => {
    const content = [
      {
        type: 'table',
        content: [
          {
            type: 'tableRow',
            content: [
              {
                type: 'tableHeader',
                content: [
                  { type: 'paragraph', content: [{ type: 'text', text: 'A' }] },
                ],
              },
              {
                type: 'tableCell',
                content: [
                  { type: 'paragraph', content: [{ type: 'text', text: 'B' }] },
                ],
              },
            ],
          },
        ],
      },
    ];
    expect(extractTiptapDiffLines(content)).toEqual(['[Tabla fila 1] A | B']);
  });

  it('detects image-only edits in line diff', () => {
    const base = [{ type: 'paragraph', content: [{ type: 'text', text: 'Igual' }] }];
    const withImage = [
      ...base,
      { type: 'image', attrs: { src: 'https://x.test/nueva.png' } },
    ];
    const a = extractTiptapDiffLines(base);
    const b = extractTiptapDiffLines(withImage);
    expect(b).not.toEqual(a);
    expect(b).toContain('[Imagen: nueva.png]');
  });
});
