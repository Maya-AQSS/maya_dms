import { BLOCK_UI_STATE_CONFIG, type BlockUiState } from '../blockUiState';

export function BlockUiStateToggle({
  value,
  onChange,
  disabled,
}: {
  value: BlockUiState;
  onChange: (s: BlockUiState) => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {(['editable', 'modifiable', 'locked', 'optional'] as BlockUiState[]).map((s) => (
        <button
          key={s}
          type="button"
          disabled={disabled}
          onClick={() => onChange(s)}
          className={[
            'px-2.5 py-1 rounded text-xs font-bold uppercase tracking-widest transition-all border',
            value === s
              ? 'border-odoo-purple bg-odoo-purple text-text-inverse'
              : 'border-ui-border dark:border-ui-dark-border text-text-secondary hover:border-odoo-purple/50',
            'disabled:opacity-50 disabled:pointer-events-none',
          ].join(' ')}
        >
          {BLOCK_UI_STATE_CONFIG[s].label}
        </button>
      ))}
    </div>
  );
}
