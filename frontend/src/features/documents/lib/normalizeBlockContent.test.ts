import { describe, it, expect } from 'vitest';
import { normalizeBlockContentForEditor } from './normalizeBlockContent';

/**
 * Invariante (DMS-F09): conviven tres formas de contenido de bloque
 * (array pelado de nodos, raíz `{type:'doc', content:[...]}` y formas
 * desconocidas/legacy). normalizeBlockContentForEditor SIEMPRE debe devolver
 * un array de nodos para el editor. Tres bugs reales de producción (render PDF,
 * descripción aplanada, accessor) nacieron de no respetar esta invariante.
 */
describe('normalizeBlockContentForEditor', () => {
  it('passes through a non-empty node array unchanged', () => {
    const nodes = [{ type: 'paragraph', content: [{ type: 'text', text: 'hello' }] }];
    expect(normalizeBlockContentForEditor(nodes)).toEqual(nodes);
  });

  it('unwraps a {type:"doc", content:[...]} root to its content array', () => {
    const content = [{ type: 'paragraph', content: [{ type: 'text', text: 'x' }] }];
    expect(normalizeBlockContentForEditor({ type: 'doc', content })).toEqual(content);
  });

  it('returns [] for an empty array', () => {
    expect(normalizeBlockContentForEditor([])).toEqual([]);
  });

  it('returns [] for null and undefined', () => {
    expect(normalizeBlockContentForEditor(null)).toEqual([]);
    expect(normalizeBlockContentForEditor(undefined)).toEqual([]);
  });

  it('returns [] for a doc wrapper without a content array', () => {
    expect(normalizeBlockContentForEditor({ type: 'doc' })).toEqual([]);
    expect(normalizeBlockContentForEditor({ type: 'doc', content: 'nope' })).toEqual([]);
  });

  it('returns [] for primitive and unknown object shapes', () => {
    expect(normalizeBlockContentForEditor('text')).toEqual([]);
    expect(normalizeBlockContentForEditor(42)).toEqual([]);
    expect(normalizeBlockContentForEditor({ foo: 'bar' })).toEqual([]);
  });

  it('always returns an array (the canonical invariant)', () => {
    for (const input of [null, undefined, 0, '', 'x', {}, { type: 'doc' }, [], [{ type: 'p' }]]) {
      expect(Array.isArray(normalizeBlockContentForEditor(input))).toBe(true);
    }
  });
});
