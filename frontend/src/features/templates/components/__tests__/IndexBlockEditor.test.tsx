import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { IndexBlockEditor, headingKey, type IndexSelectableBlock } from '../IndexBlockEditor';

const docWithHeadings = (...hs: Array<[number, string]>) => ({
  type: 'doc',
  content: hs.map(([level, text]) => ({ type: 'heading', attrs: { level }, content: [{ type: 'text', text }] })),
});

const blocks: IndexSelectableBlock[] = [
  { id: 'i1', title: 'Índice', block_type: 'index' },
  { id: 'c1', title: 'NombreBloque', block_type: 'content', default_content: docWithHeadings([1, 'Título interno'], [2, 'Subtítulo']) },
];

describe('IndexBlockEditor', () => {
  it('muestra los TÍTULOS INTERNOS de todos los bloques (no nombres de bloque)', () => {
    render(
      <IndexBlockEditor
        blocks={blocks}
        currentBlockId="i1"
        value={{ kind: 'index', excludedHeadings: [] }}
        onChange={vi.fn()}
      />,
    );
    expect(screen.getByText('Título interno')).toBeTruthy();
    expect(screen.getByText('Subtítulo')).toBeTruthy();
    expect(screen.queryByText('NombreBloque')).toBeNull();
  });

  it('desmarcar un título lo añade a la deny-list (excludedHeadings)', () => {
    const onChange = vi.fn();
    render(
      <IndexBlockEditor
        blocks={blocks}
        currentBlockId="i1"
        value={{ kind: 'index', excludedHeadings: [] }}
        onChange={onChange}
      />,
    );
    // El primer título (c1#0) está marcado; al desmarcarlo se excluye.
    const checkbox = screen.getByText('Título interno').closest('label')!.querySelector('input')!;
    fireEvent.click(checkbox);
    expect(onChange).toHaveBeenCalledWith({ kind: 'index', excludedHeadings: [headingKey('c1', 0)] });
  });

  it('usa block.content (documento) por encima de default_content', () => {
    render(
      <IndexBlockEditor
        blocks={[
          { id: 'i1', title: 'Índice', block_type: 'index' },
          { id: 'c1', title: 'A', block_type: 'content', default_content: docWithHeadings([1, 'De plantilla']), content: docWithHeadings([1, 'Del documento']) },
        ]}
        currentBlockId="i1"
        value={{ kind: 'index', excludedHeadings: [] }}
        onChange={vi.fn()}
      />,
    );
    expect(screen.getByText('Del documento')).toBeTruthy();
    expect(screen.queryByText('De plantilla')).toBeNull();
  });
});
