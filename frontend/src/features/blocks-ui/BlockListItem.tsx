import type { ReactNode } from 'react';

export type BlockListItemVariant =
  | 'default'
  | 'selected'
  | 'multi-queued'
  | 'multi-current'
  | 'multi-saved';

type Props = {
  title: string;
  variant?: BlockListItemVariant;
  locked?: boolean;
  /** Hay mensajes de otros usuarios sin leer en este bloque. */
  hasUnreadComments?: boolean;
  stateLabel?: string | null;
  dragHandle?: ReactNode;
  isEmpty?: boolean;
  /** Ayuda visual: el usuario lo ha marcado como finalizado (no bloquea nada). */
  isCompleted?: boolean;
  onClick: () => void;
};

function LockIcon() {
  return (
    <svg
      width="12"
      height="12"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
      <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
  );
}

const VARIANT_CLS: Record<BlockListItemVariant, string> = {
  selected: 'bg-odoo-purple/5 border-odoo-purple shadow-sm',
  'multi-current': 'bg-warning-light/40 border-warning ring-2 ring-warning/30',
  'multi-saved': 'bg-success/5 border-success/30',
  'multi-queued':
    'bg-odoo-purple/5 border-odoo-purple/40 shadow-sm',
  default:
    'bg-white dark:bg-ui-dark-card border-ui-border dark:border-ui-dark-border hover:border-odoo-purple/40 hover:bg-ui-body/50',
};

export function BlockListItem({
  title,
  variant = 'default',
  locked = false,
  hasUnreadComments = false,
  stateLabel,
  dragHandle,
  isEmpty = false,
  isCompleted = false,
  onClick,
}: Props) {
  const titleColor =
    variant === 'selected'
      ? 'text-odoo-purple'
      : 'text-text-primary dark:text-text-dark-primary';

  return (
    <div
      className={[
        'flex items-center gap-2 rounded-lg px-3 py-2.5 border transition-all cursor-pointer group',
        hasUnreadComments && variant === 'default'
          ? 'bg-warning-light/20 dark:bg-warning/10 border-warning/40 dark:border-warning/30 hover:border-warning/60'
          : VARIANT_CLS[variant],
        isCompleted ? 'ring-2 ring-success/60 ring-offset-1 dark:ring-offset-ui-dark-card' : '',
      ].join(' ')}
      onClick={onClick}
    >
      {dragHandle}
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          onClick();
        }}
        className="flex-1 min-w-0 flex flex-col gap-0.5 text-left focus:outline-none"
      >
        <span className="flex items-center gap-1.5">
          {locked && (
            <span className="shrink-0 text-danger-dark">
              <LockIcon />
            </span>
          )}
          <span className={`flex-1 truncate text-xs font-bold ${titleColor}`}>
            {title || '(Sin título)'}
          </span>
          {hasUnreadComments && (
            <span
              className="w-2 h-2 rounded-full bg-warning shadow-[0_0_8px_rgba(255,193,7,0.5)]"
              title="Mensajes sin leer"
            />
          )}
          {isEmpty && (
            <span
              className="w-2 h-2 rounded-full bg-danger shadow-[0_0_6px_rgba(220,38,38,0.5)]"
              title="Obligatorio — este bloque debe rellenarse"
            />
          )}
          {isCompleted && (
            <svg
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="3"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
              className="shrink-0 text-success-dark"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
          )}
        </span>
        {stateLabel && (
          <span className="text-xs text-text-muted dark:text-text-dark-muted truncate">
            {stateLabel}
          </span>
        )}
      </button>
    </div>
  );
}
