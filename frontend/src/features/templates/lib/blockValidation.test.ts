import { describe, expect, it } from 'vitest';
import {
  hasMeaningfulTiptapNode,
  tiptapContentHasMeaning,
  type TiptapNode,
} from './blockValidation';

describe('hasMeaningfulTiptapNode', () => {
  it('un nodo text con texto real es significativo', () => {
    expect(hasMeaningfulTiptapNode({ type: 'text', text: 'hola' })).toBe(true);
  });

  it('un nodo text solo-espacios NO es significativo', () => {
    expect(hasMeaningfulTiptapNode({ type: 'text', text: '   ' })).toBe(false);
    expect(hasMeaningfulTiptapNode({ type: 'text', text: '' })).toBe(false);
    expect(hasMeaningfulTiptapNode({ type: 'text' })).toBe(false);
  });

  it('un párrafo vacío NO es significativo', () => {
    expect(hasMeaningfulTiptapNode({ type: 'paragraph' })).toBe(false);
    expect(hasMeaningfulTiptapNode({ type: 'paragraph', content: [] })).toBe(false);
    expect(
      hasMeaningfulTiptapNode({ type: 'paragraph', content: [{ type: 'text', text: ' ' }] }),
    ).toBe(false);
  });

  // Fix histórico description-flatten: iframe/alert cuentan como contenido.
  it.each(['image', 'table', 'bulletList', 'orderedList', 'iframe', 'alert'])(
    'un nodo %s cuenta como contenido aunque no tenga texto',
    (type) => {
      expect(hasMeaningfulTiptapNode({ type })).toBe(true);
    },
  );

  it('recursión: párrafo con texto anidado es significativo', () => {
    expect(
      hasMeaningfulTiptapNode({ type: 'paragraph', content: [{ type: 'text', text: 'x' }] }),
    ).toBe(true);
  });

  it('recursión profunda: solo el último nodo tiene texto', () => {
    const node: TiptapNode = {
      type: 'doc',
      content: [
        { type: 'paragraph', content: [] },
        { type: 'paragraph', content: [{ type: 'text', text: 'final' }] },
      ],
    };
    expect(hasMeaningfulTiptapNode(node)).toBe(true);
  });
});

describe('tiptapContentHasMeaning', () => {
  it('array pelado de nodos: true si alguno es significativo', () => {
    expect(
      tiptapContentHasMeaning([
        { type: 'paragraph', content: [] },
        { type: 'paragraph', content: [{ type: 'text', text: 'a' }] },
      ]),
    ).toBe(true);
  });

  it('array de párrafos vacíos: false', () => {
    expect(tiptapContentHasMeaning([{ type: 'paragraph', content: [] }])).toBe(false);
    expect(tiptapContentHasMeaning([])).toBe(false);
  });

  it('doc Tiptap {type:doc}: evalúa el árbol completo', () => {
    expect(
      tiptapContentHasMeaning({
        type: 'doc',
        content: [{ type: 'paragraph', content: [{ type: 'text', text: 'hola' }] }],
      }),
    ).toBe(true);
    expect(
      tiptapContentHasMeaning({ type: 'doc', content: [{ type: 'paragraph', content: [] }] }),
    ).toBe(false);
  });

  it('null/undefined/strings: false', () => {
    expect(tiptapContentHasMeaning(null)).toBe(false);
    expect(tiptapContentHasMeaning(undefined)).toBe(false);
    expect(tiptapContentHasMeaning('texto plano')).toBe(false);
  });
});
