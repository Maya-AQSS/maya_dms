import { describe, expect, it } from 'vitest';
import { planDocumentBlockSave, isUnresolvedEditableBlock } from './blockContentEquals';
import type { DocumentDisplayBlock } from '../../../types/documents';

const block = (over: Partial<DocumentDisplayBlock>): DocumentDisplayBlock => ({
  document_block_id: 'd1',
  template_block_id: 't1',
  type: '',
  title: null,
  default_content: null,
  block_state: 'editable',
  mandatory: true,
  sort_order: 0,
  content: null,
  is_filled: false,
  ...over,
});

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

describe('isUnresolvedEditableBlock — bloques estructurales', () => {
  it('portada editable SIN relleno está sin resolver (punto rojo)', () => {
    expect(isUnresolvedEditableBlock(block({ block_type: 'cover', content: null }))).toBe(true);
  });

  it('portada con datos de relleno se considera resuelta (sin punto)', () => {
    expect(
      isUnresolvedEditableBlock(block({ block_type: 'cover', content: { kind: 'cover-fill', values: { k: 'x' } } })),
    ).toBe(false);
  });

  it('índice y hoja en blanco nunca cuentan como sin resolver', () => {
    expect(isUnresolvedEditableBlock(block({ block_type: 'index', content: null }))).toBe(false);
    expect(isUnresolvedEditableBlock(block({ block_type: 'blank', content: null }))).toBe(false);
  });

  it('bloque de contenido editable vacío sigue sin resolver', () => {
    expect(isUnresolvedEditableBlock(block({ block_type: 'content', content: null }))).toBe(true);
  });
});
