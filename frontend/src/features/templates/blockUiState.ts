import type { BlockState, TemplateBlock } from '../../types/blocks';

export type BlockUiState = 'editable' | 'modifiable' | 'locked' | 'optional';

export const BLOCK_UI_STATE_CONFIG: Record<
  BlockUiState,
  { label: string; badgeCls: string; payload: { block_state: BlockState; mandatory: boolean } }
> = {
  editable: {
    label: 'Editable',
    badgeCls:
      'bg-odoo-teal/15 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
    payload: { block_state: 'editable', mandatory: true },
  },
  modifiable: {
    label: 'Modificable',
    badgeCls:
      'bg-odoo-purple/10 text-odoo-purple dark:bg-odoo-dark-purple/20 dark:text-odoo-dark-purple',
    payload: { block_state: 'modifiable', mandatory: true },
  },
  locked: {
    label: 'Bloqueado',
    badgeCls: 'bg-danger/10 text-danger-dark dark:bg-danger/15 dark:text-danger',
    payload: { block_state: 'locked', mandatory: true },
  },
  optional: {
    label: 'Opcional',
    badgeCls:
      'bg-ui-body text-text-muted dark:bg-ui-dark-bg dark:text-text-dark-muted border border-ui-border dark:border-ui-dark-border',
    payload: { block_state: 'editable', mandatory: false },
  },
};

export function blockToUiState(block: TemplateBlock): BlockUiState {
  if (!block.mandatory) return 'optional';
  if (block.block_state === 'locked') return 'locked';
  if (block.block_state === 'modifiable') return 'modifiable';
  return 'editable';
}
