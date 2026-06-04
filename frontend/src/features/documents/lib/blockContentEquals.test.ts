import { describe, expect, it } from 'vitest';
import { planDocumentBlockSave } from './blockContentEquals';

const guide = [
  { type: 'paragraph', content: [{ type: 'text', text: 'Texto guía' }] },
];
const userEdit = [
  { type: 'paragraph', content: [{ type: 'text', text: 'Texto propio' }] },
];

describe('planDocumentBlockSave', () => {
  it('skips when local matches last saved in session', () => {
    expect(
      planDocumentBlockSave(userEdit, userEdit, userEdit, guide, 'editable'),
    ).toEqual({ action: 'skip' });
  });

  it('skips when local matches default and DB is already aligned', () => {
    expect(
      planDocumentBlockSave(guide, guide, null, guide, 'editable'),
    ).toEqual({ action: 'skip' });
  });

  it('persists null when editable user reverts to guide but DB still has edits', () => {
    expect(
      planDocumentBlockSave(guide, userEdit, userEdit, guide, 'editable'),
    ).toEqual({ action: 'persist', payload: null });
  });

  it('persists default JSON when modifiable reverts but DB has edits', () => {
    expect(
      planDocumentBlockSave(guide, userEdit, userEdit, guide, 'modifiable'),
    ).toEqual({ action: 'persist', payload: guide });
  });

  it('persists local content on real edits', () => {
    expect(
      planDocumentBlockSave(userEdit, guide, null, guide, 'editable'),
    ).toEqual({ action: 'persist', payload: userEdit });
  });
});
