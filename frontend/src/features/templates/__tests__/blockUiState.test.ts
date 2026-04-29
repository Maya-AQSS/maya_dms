import { describe, it, expect } from'vitest';
import { blockToUiState, BLOCK_UI_STATE_CONFIG } from'../blockUiState';
import type { TemplateBlock } from'../../../types/blocks';

describe('blockUiState logic', () => {
 describe('blockToUiState', () => {
 it('returns"optional" when block_state is optional', () => {
 const block: Partial<TemplateBlock> = { block_state:'optional' };
 expect(blockToUiState(block as TemplateBlock)).toBe('optional');
 });

 it('returns"locked" when state is locked', () => {
 const block: Partial<TemplateBlock> = { block_state:'locked' };
 expect(blockToUiState(block as TemplateBlock)).toBe('locked');
 });

 it('returns"modifiable" when state is modifiable', () => {
 const block: Partial<TemplateBlock> = { block_state:'modifiable' };
 expect(blockToUiState(block as TemplateBlock)).toBe('modifiable');
 });

 it('returns"editable" when state is editable', () => {
 const block: Partial<TemplateBlock> = { block_state:'editable' };
 expect(blockToUiState(block as TemplateBlock)).toBe('editable');
 });
 });

 describe('BLOCK_UI_STATE_CONFIG', () => {
 it('has the correct labels and expected color tags in badgeCls', () => {
 expect(BLOCK_UI_STATE_CONFIG.locked.label).toBe('Bloqueado');
 expect(BLOCK_UI_STATE_CONFIG.locked.badgeCls).toContain('danger');
 
 expect(BLOCK_UI_STATE_CONFIG.editable.label).toBe('Editable');
 expect(BLOCK_UI_STATE_CONFIG.editable.badgeCls).toContain('success');

 expect(BLOCK_UI_STATE_CONFIG.modifiable.label).toBe('Modificable');
 expect(BLOCK_UI_STATE_CONFIG.modifiable.badgeCls).toContain('info');

 expect(BLOCK_UI_STATE_CONFIG.optional.label).toBe('Opcional');
 expect(BLOCK_UI_STATE_CONFIG.optional.badgeCls).toContain('purple');
 });
 });
});
