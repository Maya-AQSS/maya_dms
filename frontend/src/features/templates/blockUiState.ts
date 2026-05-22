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
    payload: { block_state: 'modifiable', mandatory: false },
  },
  locked: {
    label: 'Bloqueado',
    badgeCls: 'bg-danger/10 text-danger-dark dark:bg-danger-dark/30 dark:text-danger-light',
    payload: { block_state: 'locked', mandatory: true },
  },
  optional: {
    label: 'Opcional',
    badgeCls:
      'bg-warning/10 text-warning-dark dark:bg-warning-dark/30 dark:text-warning-light',
    payload: { block_state: 'optional', mandatory: false },
  },
};

export function blockToUiState(block: Pick<TemplateBlock, 'block_state' | 'mandatory'>): BlockUiState {
  // block_state is the authoritative signal — mandatory only drives UI labels (badge), not state.
  if (block.block_state === 'locked') return 'locked';
  if (block.block_state === 'optional') return 'optional';
  if (block.block_state === 'modifiable') return 'modifiable';
  return 'editable';
}
