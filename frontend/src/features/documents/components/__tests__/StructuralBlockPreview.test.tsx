import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { StructuralBlockPreview, isStructuralBlockType } from '../StructuralBlockPreview';
import type { StructuralBlock } from '../StructuralBlockPreview';

const make = (over: Partial<StructuralBlock>): StructuralBlock => ({
  default_content: null,
  title: null,
  ...over,
});

/** Doc tiptap con encabezados, para simular el contenido de un bloque. */
const docWithHeadings = (...headings: Array<[number, string]>) => ({
  type: 'doc',
  content: headings.map(([level, text]) => ({
    type: 'heading',
    attrs: { level },
    content: [{ type: 'text', text }],
  })),
});

describe('isStructuralBlockType', () => {
  it('identifica cover/index/blank como estructurales y content como no', () => {
    expect(isStructuralBlockType('cover')).toBe(true);
    expect(isStructuralBlockType('index')).toBe(true);
    expect(isStructuralBlockType('blank')).toBe(true);
    expect(isStructuralBlockType('content')).toBe(false);
    expect(isStructuralBlockType(undefined)).toBe(false);
  });
});

describe('StructuralBlockPreview', () => {
  it('hoja en blanco muestra indicador, no "Sin contenido"', () => {
    render(<StructuralBlockPreview block={make({ block_type: 'blank' })} />);
    expect(screen.getByText('Página en blanco')).toBeTruthy();
  });

  it('índice lista los TÍTULOS INTERNOS de TODOS los bloques, no el nombre del bloque', () => {
    const all: StructuralBlock[] = [
      make({ block_type: 'index', title: 'Índice', template_block_id: 'i1' }),
      make({ block_type: 'content', title: 'Bloque A', template_block_id: 'c1', default_content: docWithHeadings([1, 'Introducción'], [2, 'Sub A']) }),
      make({ block_type: 'content', title: 'Bloque B', template_block_id: 'c2', default_content: docWithHeadings([2, 'Metodología']) }),
    ];
    render(<StructuralBlockPreview block={all[0]} allBlocks={all} />);
    // Todos los encabezados internos de todos los bloques...
    expect(screen.getByText('Introducción')).toBeTruthy();
    expect(screen.getByText('Sub A')).toBeTruthy();
    expect(screen.getByText('Metodología')).toBeTruthy();
    // ...y NO los nombres de los bloques.
    expect(screen.queryByText('Bloque A')).toBeNull();
    expect(screen.queryByText('Bloque B')).toBeNull();
  });

  it('excluye los títulos de la deny-list (excludedHeadings)', () => {
    const idx = make({
      block_type: 'index',
      template_block_id: 'i1',
      // Excluye el 2º encabezado (idx 1) del bloque c1.
      default_content: { kind: 'index', excludedHeadings: ['c1#1'] },
    });
    const all: StructuralBlock[] = [
      idx,
      make({ block_type: 'content', title: 'A', template_block_id: 'c1', default_content: docWithHeadings([1, 'Principal'], [2, 'Oculto']) }),
    ];
    render(<StructuralBlockPreview block={idx} allBlocks={all} />);
    expect(screen.getByText('Principal')).toBeTruthy();
    expect(screen.queryByText('Oculto')).toBeNull();
  });

  it('portada sin elementos no revienta y muestra estado vacío', () => {
    render(<StructuralBlockPreview block={make({ block_type: 'cover', default_content: null })} />);
    expect(screen.getByText('Portada sin elementos.')).toBeTruthy();
  });

  it('devuelve null para bloques no estructurales', () => {
    const { container } = render(<StructuralBlockPreview block={make({ block_type: 'content' })} />);
    expect(container.firstChild).toBeNull();
  });
});
