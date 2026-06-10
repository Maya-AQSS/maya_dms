import { describe, expect, it } from 'vitest';
import { compareVersionBlocks, type ComparableBlock } from './versionBlockCompare';

const para = (text: string) => [
  { type: 'paragraph', content: [{ type: 'text', text }] },
];

const block = (over: Partial<ComparableBlock> & { key: string }): ComparableBlock => ({
  title: null,
  content: null,
  sortOrder: 0,
  ...over,
});

const opts = { emptyBlockLabel: '(vacío)' };

describe('compareVersionBlocks', () => {
  it('omits blocks whose content is identical between versions', () => {
    const a = [block({ key: 't1', content: para('Hola'), sortOrder: 0 })];
    const b = [block({ key: 't1', content: para('Hola'), sortOrder: 0 })];
    expect(compareVersionBlocks(a, b, opts)).toEqual([]);
  });

  it('flags a modified block with added/removed lines', () => {
    const a = [block({ key: 't1', title: 'Intro', content: para('Viejo'), sortOrder: 0 })];
    const b = [block({ key: 't1', title: 'Intro', content: para('Nuevo'), sortOrder: 0 })];
    const result = compareVersionBlocks(a, b, opts);
    expect(result).toHaveLength(1);
    expect(result[0].status).toBe('modified');
    expect(result[0].blockNumber).toBe(1);
    expect(result[0].lines).toEqual([
      { type: 'removed', text: 'Viejo' },
      { type: 'added', text: 'Nuevo' },
    ]);
  });

  it('flags a block present only in B as added', () => {
    const a: ComparableBlock[] = [];
    const b = [block({ key: 't9', title: 'Nuevo bloque', content: para('Texto'), sortOrder: 0 })];
    const result = compareVersionBlocks(a, b, opts);
    expect(result).toHaveLength(1);
    expect(result[0].status).toBe('added');
    expect(result[0].lines).toEqual([{ type: 'added', text: 'Texto' }]);
  });

  it('flags a block present only in A as removed', () => {
    const a = [block({ key: 't5', title: 'Antiguo', content: para('Se va'), sortOrder: 0 })];
    const b: ComparableBlock[] = [];
    const result = compareVersionBlocks(a, b, opts);
    expect(result).toHaveLength(1);
    expect(result[0].status).toBe('removed');
    expect(result[0].lines).toEqual([{ type: 'removed', text: 'Se va' }]);
  });

  it('uses the empty-block label when an added block has no text', () => {
    const b = [block({ key: 'cover', title: 'Portada', content: null, sortOrder: 0 })];
    const result = compareVersionBlocks([], b, opts);
    expect(result[0].lines).toEqual([{ type: 'added', text: '(vacío)' }]);
  });

  it('numbers blocks by their position in version B', () => {
    const a = [
      block({ key: 't1', content: para('A1'), sortOrder: 0 }),
      block({ key: 't2', content: para('A2'), sortOrder: 1 }),
    ];
    const b = [
      block({ key: 't1', content: para('A1'), sortOrder: 0 }),
      block({ key: 't2', content: para('B2'), sortOrder: 1 }),
    ];
    const result = compareVersionBlocks(a, b, opts);
    expect(result).toHaveLength(1);
    expect(result[0].key).toBe('t2');
    expect(result[0].blockNumber).toBe(2);
  });

  it('shows a transition when a block goes from filled to empty (content → guía)', () => {
    // content efectivo: v1 = texto del usuario, v2 = guía de plantilla (vacío → fallback)
    const a = [block({ key: 't1', content: para('Texto del usuario'), sortOrder: 0 })];
    const b = [block({ key: 't1', content: para('Guía de plantilla'), sortOrder: 0 })];
    const result = compareVersionBlocks(a, b, opts);
    expect(result).toHaveLength(1);
    expect(result[0].status).toBe('modified');
    expect(result[0].lines).toEqual([
      { type: 'removed', text: 'Texto del usuario' },
      { type: 'added', text: 'Guía de plantilla' },
    ]);
  });

  it('treats structural/empty blocks (content null) as unchanged when both empty', () => {
    const a = [block({ key: 'cover', title: 'Portada', content: null, sortOrder: 0 })];
    const b = [block({ key: 'cover', title: 'Portada', content: null, sortOrder: 0 })];
    expect(compareVersionBlocks(a, b, opts)).toEqual([]);
  });

  it('reports both changes even when blocks share the same sortOrder', () => {
    const a = [
      block({ key: 't1', content: para('A1'), sortOrder: 0 }),
      block({ key: 't2', content: para('A2'), sortOrder: 0 }),
    ];
    const b = [
      block({ key: 't1', content: para('B1'), sortOrder: 0 }),
      block({ key: 't2', content: para('B2'), sortOrder: 0 }),
    ];
    const result = compareVersionBlocks(a, b, opts);
    expect(result.map((c) => c.key).sort()).toEqual(['t1', 't2']);
    expect(result.every((c) => c.status === 'modified')).toBe(true);
  });

  it('orders changes by version-B sort order, removed blocks last', () => {
    const a = [
      block({ key: 'keep', content: para('x'), sortOrder: 0 }),
      block({ key: 'gone', content: para('bye'), sortOrder: 1 }),
    ];
    const b = [
      block({ key: 'keep', content: para('x2'), sortOrder: 0 }),
      block({ key: 'new', content: para('hi'), sortOrder: 1 }),
    ];
    const result = compareVersionBlocks(a, b, opts);
    expect(result.map((c) => `${c.key}:${c.status}`)).toEqual([
      'keep:modified',
      'new:added',
      'gone:removed',
    ]);
  });
});
