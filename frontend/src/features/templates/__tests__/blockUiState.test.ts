import { describe, it, expect } from 'vitest';
import { blockToUiState, BLOCK_UI_STATE_CONFIG } from '../blockUiState';
import type { TemplateBlock } from '../../../types/blocks';

describe('blockUiState logic', () => {
  describe('blockToUiState', () => {
    it('returns "optional" when block is not mandatory', () => {
      const block: Partial<TemplateBlock> = { mandatory: false };
      expect(blockToUiState(block as TemplateBlock)).toBe('optional');
    });

    it('returns "locked" when block is mandatory and state is locked', () => {
      const block: Partial<TemplateBlock> = { mandatory: true, block_state: 'locked' };
      expect(blockToUiState(block as TemplateBlock)).toBe('locked');
    });

    it('returns "modifiable" when block is mandatory and state is modifiable', () => {
      const block: Partial<TemplateBlock> = { mandatory: true, block_state: 'modifiable' };
      expect(blockToUiState(block as TemplateBlock)).toBe('modifiable');
    });

    it('returns "editable" when block is mandatory and state is something else (e.g. editable)', () => {
      const block: Partial<TemplateBlock> = { mandatory: true, block_state: 'editable' };
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
