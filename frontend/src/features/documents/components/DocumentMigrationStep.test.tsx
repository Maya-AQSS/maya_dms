import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  DocumentMigrationStep,
  isActionableMigrationBlock,
} from './DocumentMigrationStep';
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
    new_default_content: [{ type: 'paragraph', content: [{ type: 'text', text: 'nuevo' }] }],
    old_content: [{ type: 'paragraph', content: [{ type: 'text', text: 'antiguo' }] }],
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

describe('isActionableMigrationBlock', () => {
  it('is true for an editable block with old content', () => {
    expect(isActionableMigrationBlock(block({ template_block_id: 'a' }))).toBe(true);
  });

  it('is false for locked, new, removed, or empty-old blocks', () => {
    expect(isActionableMigrationBlock(block({ locked: true }))).toBe(false);
    expect(isActionableMigrationBlock(block({ new_block: true, old_content: null }))).toBe(false);
    expect(isActionableMigrationBlock(block({ removed_block: true }))).toBe(false);
    expect(isActionableMigrationBlock(block({ old_content: null }))).toBe(false);
  });
});

describe('DocumentMigrationStep', () => {
  const mixed = payload([
    block({ template_block_id: 'editable-1', sort_order: 0 }),
    block({ template_block_id: 'locked-1', sort_order: 1, locked: true, block_state: 'locked' }),
    block({ template_block_id: 'new-1', sort_order: 2, new_block: true, old_content: null }),
    block({ template_block_id: 'removed-1', sort_order: 3, removed_block: true, new_default_content: null }),
  ]);

  it('renders Replace/Append actions only for actionable blocks', () => {
    render(<DocumentMigrationStep payload={mixed} choices={{}} onChoose={vi.fn()} />);
    // Exactly one actionable block → one Replace and one Append button.
    expect(screen.getAllByText('migration.replace')).toHaveLength(1);
    expect(screen.getAllByText('migration.append')).toHaveLength(1);
  });

  it('reports the chosen action immutably via onChoose', () => {
    const onChoose = vi.fn();
    render(<DocumentMigrationStep payload={mixed} choices={{}} onChoose={onChoose} />);
    fireEvent.click(screen.getByText('migration.replace'));
    expect(onChoose).toHaveBeenCalledWith('editable-1', 'replace');
  });

  it('toggles a selected action off when clicked again', () => {
    const onChoose = vi.fn();
    render(
      <DocumentMigrationStep
        payload={mixed}
        choices={{ 'editable-1': 'replace' }}
        onChoose={onChoose}
      />,
    );
    fireEvent.click(screen.getByText('migration.replace'));
    expect(onChoose).toHaveBeenCalledWith('editable-1', undefined);
  });

  it('shows the removed-blocks section when there are removed blocks', () => {
    render(<DocumentMigrationStep payload={mixed} choices={{}} onChoose={vi.fn()} />);
    expect(screen.getByText(/migration\.removedSection/)).toBeTruthy();
  });

  it('does NOT offer Keep/Remove for removed blocks in clone mode', () => {
    render(<DocumentMigrationStep payload={mixed} choices={{}} onChoose={vi.fn()} />);
    expect(screen.queryByText('migration.keep')).toBeNull();
    expect(screen.queryByText('migration.delete')).toBeNull();
  });

  it('offers Keep/Remove for removed blocks in upgrade mode and reports the choice', () => {
    const onChooseRemoved = vi.fn();
    render(
      <DocumentMigrationStep
        payload={mixed}
        choices={{}}
        onChoose={vi.fn()}
        removedChoices={{}}
        onChooseRemoved={onChooseRemoved}
        allowRemovedDecision
      />,
    );
    expect(screen.getByText('migration.keep')).toBeTruthy();
    fireEvent.click(screen.getByText('migration.delete'));
    expect(onChooseRemoved).toHaveBeenCalledWith('removed-1', 'delete');
  });
});
