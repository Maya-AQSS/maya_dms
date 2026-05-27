interface ColorBadgeProps {
  color: string | null;
  /** sm: used in table cells (h-4/w-4, xs text). md: used in detail views (h-5/w-5, sm text). */
  size?: 'sm' | 'md';
}

export function ColorBadge({ color, size = 'md' }: ColorBadgeProps) {
  if (!color) {
    return (
      <span className="text-text-muted dark:text-text-dark-muted">
        {size === 'sm' ? '—' : 'Sin color'}
      </span>
    );
  }
  return (
    <div className="flex items-center gap-2">
      <span
        className={`inline-block rounded-full border border-ui-border ${size === 'sm' ? 'h-4 w-4' : 'h-5 w-5 shadow-sm'}`}
        style={{ backgroundColor: color }}
        title={color}
      />
      <span
        className={`font-mono ${size === 'sm' ? 'text-xs' : 'text-sm'} text-text-secondary dark:text-text-dark-secondary`}
      >
        {color}
      </span>
    </div>
  );
}
