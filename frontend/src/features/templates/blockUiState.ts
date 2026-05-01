import type { BlockState, TemplateBlock } from '../../types/blocks';

export type BlockUiState = 'editable' | 'modifiable' | 'locked' | 'optional';

export const BLOCK_UI_STATE_CONFIG: Record<
  BlockUiState,
  { label: string; badgeCls: string; payload: { block_state: BlockState; mandatory: boolean } }
> = {
  editable: {
    label: 'Editable',
    badgeCls:
      'bg-success/15 text-success-dark dark:bg-success-dark/30 dark:text-success-light',
    payload: { block_state: 'editable', mandatory: true },
  },
  modifiable: {
    label: 'Modificable',
    badgeCls:
      'bg-info/10 text-info-dark dark:bg-info-dark/30 dark:text-info-light',
    payload: { block_state: 'modifiable', mandatory: true },
  },
  locked: {
    label: 'Bloqueado',
    badgeCls: 'bg-danger/10 text-danger-dark dark:bg-danger-dark/30 dark:text-danger-light',
    payload: { block_state: 'locked', mandatory: true },
  },
  optional: {
    label: 'Opcional',
    badgeCls:
      'bg-odoo-purple/10 text-odoo-purple-d dark:bg-odoo-dark-purple-d/30 dark:text-odoo-dark-purple-l',
    payload: { block_state: 'editable', mandatory: false },
  },
};

export function blockToUiState(block: Pick<TemplateBlock, 'block_state' | 'mandatory'>): BlockUiState {
  // 'locked' tiene prioridad sobre 'optional': un bloque locked es no editable
  // independientemente de mandatory. El backend (DocumentService::updateBlock)
  // rechaza con 403 cualquier edición de un bloque cuyo block_state === 'locked'.
  if (block.block_state === 'locked') {
    return 'locked';
  }
  if (block.block_state === 'optional' || block.mandatory === false) {
    return 'optional';
  }
  if (block.block_state === 'modifiable') {
    return 'modifiable';
  }
  return 'editable';
}
