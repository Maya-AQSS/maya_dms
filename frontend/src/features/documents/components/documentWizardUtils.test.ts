import { describe, expect, it } from 'vitest';
import type { DocumentDisplayBlock } from '../../../types/documents';
import {
  resolveActiveBlockKey,
  visibleDocumentBlocks,
} from './documentWizardUtils';

function block(
  overrides: Partial<DocumentDisplayBlock> & Pick<DocumentDisplayBlock, 'template_block_id'>,
): DocumentDisplayBlock {
  return {
    document_block_id: 'doc-block-1',
    type: 'content',
    title: 'Bloque',
    default_content: null,
    block_state: 'optional',
    mandatory: false,
    sort_order: 1,
    content: null,
    is_filled: false,
    ...overrides,
  };
}

describe('visibleDocumentBlocks', () => {
  it('excludes blocks marked as deleted', () => {
    const blocks = [
      block({ template_block_id: 'tpl-1' }),
      block({ template_block_id: 'tpl-2', is_deleted: true, document_block_id: null }),
    ];
    expect(visibleDocumentBlocks(blocks).map((b) => b.template_block_id)).toEqual(['tpl-1']);
  });
});

describe('resolveActiveBlockKey', () => {
  it('keeps the previous key when it is still visible', () => {
    const visible = [block({ template_block_id: 'tpl-1', document_block_id: 'doc-1' })];
    expect(resolveActiveBlockKey('doc-1', visible)).toBe('doc-1');
  });

  it('selects the first visible block when the previous one was deleted', () => {
    const visible = [
      block({ template_block_id: 'tpl-1', document_block_id: 'doc-1', sort_order: 1 }),
      block({ template_block_id: 'tpl-2', document_block_id: 'doc-2', sort_order: 2 }),
    ];
    expect(resolveActiveBlockKey('doc-deleted', visible)).toBe('doc-1');
  });

  it('returns null when there are no visible blocks', () => {
    expect(resolveActiveBlockKey('doc-1', [])).toBeNull();
  });
});
