import { describe, expect, it } from 'vitest';
import { diffTiptapContentLines } from './tiptapLineDiff';

describe('diffTiptapContentLines', () => {
  it('marks all lines as added when there is no previous snapshot', () => {
    const cur = [{ type: 'paragraph', content: [{ type: 'text', text: 'Nuevo' }] }];
    expect(diffTiptapContentLines(null, cur)).toEqual([{ type: 'added', text: 'Nuevo' }]);
  });

  it('detects image line changes between cycles', () => {
    const prev = [{ type: 'paragraph', content: [{ type: 'text', text: 'Igual' }] }];
    const cur = [
      ...prev,
      { type: 'image', attrs: { src: 'https://cdn.test/a.png' } },
    ];
    const diff = diffTiptapContentLines(prev, cur);
    expect(diff.some((l) => l.type === 'added' && l.text.includes('[Imagen:'))).toBe(true);
  });
});
