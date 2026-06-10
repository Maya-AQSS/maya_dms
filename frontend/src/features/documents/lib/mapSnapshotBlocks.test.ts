import { describe, expect, it } from 'vitest';
import {
  documentBlocksToComparable,
  effectiveDocumentBlockContent,
  mapSnapshotDocumentBlocks,
  templateSnapshotToComparable,
} from './mapSnapshotBlocks';
import type { DocumentDisplayBlock } from '../../../types/documents';
import type { TemplateVersionSnapshotBlock } from '../../../api/templates';

const para = (text: string) => [
  { type: 'paragraph', content: [{ type: 'text', text }] },
];

const docBlock = (over: Partial<DocumentDisplayBlock>): DocumentDisplayBlock => ({
  document_block_id: 'd1',
  template_block_id: 't1',
  type: 'text',
  title: null,
  default_content: null,
  block_state: 'editable',
  mandatory: true,
  sort_order: 0,
  content: null,
  is_filled: false,
  ...over,
});

describe('mapSnapshotDocumentBlocks', () => {
  it('returns [] for non-array input', () => {
    expect(mapSnapshotDocumentBlocks(null)).toEqual([]);
    expect(mapSnapshotDocumentBlocks(undefined)).toEqual([]);
    expect(mapSnapshotDocumentBlocks({})).toEqual([]);
  });

  it('falls back to id when template_block_id is absent (snapshots antiguos)', () => {
    const out = mapSnapshotDocumentBlocks([{ id: 'legacy-1', content: para('x') }]);
    expect(out).toHaveLength(1);
    expect(out[0].template_block_id).toBe('legacy-1');
  });

  it('defaults block_state to locked and sort_order to index', () => {
    const out = mapSnapshotDocumentBlocks([
      { template_block_id: 'a' },
      { template_block_id: 'b' },
    ]);
    expect(out[0].block_state).toBe('locked');
    expect(out[1].sort_order).toBe(1);
  });

  it('skips non-object items', () => {
    const out = mapSnapshotDocumentBlocks(['nope', 5, null, { template_block_id: 'a' }]);
    expect(out.map((b) => b.template_block_id)).toEqual(['a']);
  });
});

describe('effectiveDocumentBlockContent', () => {
  it('uses content when it has nodes', () => {
    const c = para('relleno');
    expect(effectiveDocumentBlockContent(docBlock({ content: c, default_content: para('guía') }))).toBe(c);
  });

  it('falls back to default_content when content is empty/null', () => {
    const guide = para('guía');
    expect(effectiveDocumentBlockContent(docBlock({ content: null, default_content: guide }))).toBe(guide);
    expect(effectiveDocumentBlockContent(docBlock({ content: [], default_content: guide }))).toBe(guide);
  });
});

describe('documentBlocksToComparable', () => {
  it('maps key/title/sortOrder and effective content', () => {
    const blocks = [
      docBlock({ template_block_id: 't1', title: 'Intro', content: para('hola'), sort_order: 0 }),
      docBlock({ template_block_id: 't2', title: null, content: null, default_content: para('guía'), sort_order: 1 }),
    ];
    const out = documentBlocksToComparable(blocks);
    expect(out[0]).toEqual({ key: 't1', title: 'Intro', content: para('hola'), sortOrder: 0 });
    expect(out[1]).toEqual({ key: 't2', title: null, content: para('guía'), sortOrder: 1 });
  });
});

describe('templateSnapshotToComparable', () => {
  const tplBlock = (over: Partial<TemplateVersionSnapshotBlock>): TemplateVersionSnapshotBlock => ({
    id: 'tb1',
    type: 'text',
    title: 'Bloque',
    default_content: null,
    sort_order: 0,
    ...over,
  });

  it('keys by id and uses default_content as content', () => {
    const dc = para('plantilla');
    const out = templateSnapshotToComparable([tplBlock({ id: 'tb9', title: 'T', default_content: dc, sort_order: 3 })]);
    expect(out[0]).toEqual({ key: 'tb9', title: 'T', content: dc, sortOrder: 3 });
  });

  it('falls back sortOrder to index when missing', () => {
    const out = templateSnapshotToComparable([
      tplBlock({ id: 'a', sort_order: undefined as unknown as number }),
      tplBlock({ id: 'b', sort_order: undefined as unknown as number }),
    ]);
    expect(out.map((b) => b.sortOrder)).toEqual([0, 1]);
  });
});
