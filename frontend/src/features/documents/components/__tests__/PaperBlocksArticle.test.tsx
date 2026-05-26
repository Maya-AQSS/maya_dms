import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

// Mockear BlockContentHtml para evitar instanciar BlockNote en JSDOM.
vi.mock('../../../templates/components/BlockContentHtml', () => ({
  BlockContentHtml: ({ content }: { content: unknown[] }) => (
    <div data-testid="block-content-html">{`nodes:${(content as unknown[]).length}`}</div>
  ),
}));

import { PaperBlocksArticle, type PaperArticleBlock } from '../PaperBlocksArticle';

const baseBlocks: PaperArticleBlock[] = [
  { id: 'b1', title: 'Bloque 1', mandatory: true, isLocked: false, nodes: [{}] },
  { id: 'b2', title: 'Bloque 2', mandatory: false, isLocked: true, nodes: [] },
];

describe('PaperBlocksArticle', () => {
  it('renderiza H1 con el título y un BlockContentHtml por bloque por defecto', () => {
    render(<PaperBlocksArticle title="Mi documento" blocks={baseBlocks} />);
    expect(screen.getByRole('heading', { level: 1, name: 'Mi documento' })).toBeTruthy();
    expect(screen.getAllByTestId('block-content-html')).toHaveLength(1); // solo el bloque con nodes>0
    expect(screen.getByText('Sin contenido.')).toBeTruthy();
  });

  it('aplica renderBlockBody cuando devuelve un nodo', () => {
    const renderBlockBody = (block: PaperArticleBlock) =>
      block.id === 'b1' ? <span data-testid="custom-body">EDITOR ACTIVO</span> : undefined;
    render(
      <PaperBlocksArticle
        title="x"
        blocks={baseBlocks}
        renderBlockBody={renderBlockBody}
      />,
    );
    expect(screen.getByTestId('custom-body').textContent).toBe('EDITOR ACTIVO');
    // El bloque b2 sigue mostrando el fallback "Sin contenido."
    expect(screen.getByText('Sin contenido.')).toBeTruthy();
  });

  it('aplica renderBlockSection y reemplaza el contenedor section por defecto', () => {
    const renderBlockSection = (block: PaperArticleBlock) =>
      block.id === 'b1' ? <article data-testid={`custom-section-${block.id}`}>{block.title}</article> : undefined;
    render(
      <PaperBlocksArticle
        title="x"
        blocks={baseBlocks}
        renderBlockSection={renderBlockSection}
      />,
    );
    expect(screen.getByTestId('custom-section-b1').textContent).toBe('Bloque 1');
    // El bloque b2 mantiene el render por defecto.
    expect(screen.getByText('Bloqueado')).toBeTruthy();
  });

  it('si renderBlockBody devuelve undefined cae al render por defecto', () => {
    const renderBlockBody = () => undefined;
    render(
      <PaperBlocksArticle title="x" blocks={baseBlocks} renderBlockBody={renderBlockBody} />,
    );
    expect(screen.getAllByTestId('block-content-html')).toHaveLength(1);
  });
});
