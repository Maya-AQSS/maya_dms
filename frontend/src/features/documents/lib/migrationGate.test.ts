import { describe, expect, it } from 'vitest';
import { pendingMigrationBlockLabels } from './migrationGate';
import type {
  DocumentMigrationBlock,
  DocumentMigrationPayload,
} from '../schemas/migrationPayload';

function block(overrides: Partial<DocumentMigrationBlock>): DocumentMigrationBlock {
  return {
    template_block_id: 'b',
    title: 'Bloque',
    type: 'text',
    sort_order: 0,
    block_state: 'editable',
    old_block_state: 'editable',
    new_block: false,
    removed_block: false,
    changed_block_state: false,
    locked: false,
    new_default_content: [{ type: 'paragraph' }],
    old_content: [{ type: 'paragraph' }],
    old_default_content: null,
    ...overrides,
  };
}

function payload(blocks: DocumentMigrationBlock[]): DocumentMigrationPayload {
  return {
    source_document_id: 'doc',
    source_template_version_id: 'v1',
    source_version_number: 1,
    target_template_version_id: 'v2',
    target_version_number: 2,
    blocks,
  };
}

describe('pendingMigrationBlockLabels', () => {
  it('returns [] for a null payload', () => {
    expect(pendingMigrationBlockLabels(null, {}, {}, false)).toEqual([]);
  });

  it('flags actionable blocks without a replace/append choice', () => {
    const p = payload([block({ template_block_id: 'a', title: 'A' })]);
    expect(pendingMigrationBlockLabels(p, {}, {}, false)).toEqual(['A']);
    expect(pendingMigrationBlockLabels(p, { a: 'replace' }, {}, false)).toEqual([]);
  });

  it('ignores locked and new blocks', () => {
    const p = payload([
      block({ template_block_id: 'locked', locked: true }),
      block({ template_block_id: 'new', new_block: true, old_content: null }),
    ]);
    expect(pendingMigrationBlockLabels(p, {}, {}, true)).toEqual([]);
  });

  it('flags removed blocks only in upgrade mode and until a choice is made', () => {
    const p = payload([
      block({ template_block_id: 'rm', title: 'R', removed_block: true, new_default_content: null }),
    ]);
    // clone mode (allowRemovedDecision=false): removed blocks are informational.
    expect(pendingMigrationBlockLabels(p, {}, {}, false)).toEqual([]);
    // upgrade mode: required until chosen.
    expect(pendingMigrationBlockLabels(p, {}, {}, true)).toEqual(['R']);
    expect(pendingMigrationBlockLabels(p, {}, { rm: 'delete' }, true)).toEqual([]);
  });

  it('accumulates both actionable and removed pendings in upgrade', () => {
    const p = payload([
      block({ template_block_id: 'a', title: 'A' }),
      block({ template_block_id: 'rm', title: 'R', removed_block: true, new_default_content: null }),
    ]);
    expect(pendingMigrationBlockLabels(p, {}, {}, true)).toEqual(['A', 'R']);
  });
});
