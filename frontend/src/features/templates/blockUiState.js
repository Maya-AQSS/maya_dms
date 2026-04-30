export const BLOCK_UI_STATE_CONFIG = {
    editable: {
        label: 'Editable',
        badgeCls: 'bg-success/15 text-success-dark dark:bg-success-dark/30 dark:text-success-light',
        payload: { block_state: 'editable', mandatory: true },
    },
    modifiable: {
        label: 'Modificable',
        badgeCls: 'bg-info/10 text-info-dark dark:bg-info-dark/30 dark:text-info-light',
        payload: { block_state: 'modifiable', mandatory: true },
    },
    locked: {
        label: 'Bloqueado',
        badgeCls: 'bg-danger/10 text-danger-dark dark:bg-danger-dark/30 dark:text-danger-light',
        payload: { block_state: 'locked', mandatory: true },
    },
    optional: {
        label: 'Opcional',
        badgeCls: 'bg-odoo-purple/10 text-odoo-purple-d dark:bg-odoo-dark-purple-d/30 dark:text-odoo-dark-purple-l',
        payload: { block_state: 'editable', mandatory: false },
    },
};
export function blockToUiState(block) {
    if (block.mandatory === false) {
        return 'optional';
    }
    if (block.block_state === 'locked') {
        return 'locked';
    }
    if (block.block_state === 'modifiable') {
        return 'modifiable';
    }
    return 'editable';
}
