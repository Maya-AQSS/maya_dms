import { describe, expect, it } from 'vitest';
import { concatTiptapContent } from './tiptapContentConcat';

const para = (text: string) => ({
  type: 'paragraph',
  content: [{ type: 'text', text }],
});
const emptyPara = { type: 'paragraph' };

describe('concatTiptapContent', () => {
  it('returns empty array when both are empty', () => {
    expect(concatTiptapContent(null, [])).toEqual([]);
  });

  it('returns next when prev is empty', () => {
    expect(concatTiptapContent([], [para('b')])).toEqual([para('b')]);
  });

  it('returns prev when next is empty', () => {
    expect(concatTiptapContent([para('a')], null)).toEqual([para('a')]);
  });

  it('concatenates prev then next', () => {
    expect(concatTiptapContent([para('a')], [para('b')])).toEqual([
      para('a'),
      para('b'),
    ]);
  });

  it('trims trailing empty paragraphs of prev at the junction', () => {
    expect(
      concatTiptapContent([para('a'), emptyPara], [para('b')]),
    ).toEqual([para('a'), para('b')]);
  });

  it('accepts the legacy doc wrapper on either side', () => {
    expect(
      concatTiptapContent(
        { type: 'doc', content: [para('a')] },
        { type: 'doc', content: [para('b')] },
      ),
    ).toEqual([para('a'), para('b')]);
  });
});
