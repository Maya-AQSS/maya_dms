import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from './Button';

export type ConfirmDialogVariant = 'primary' | 'teal' | 'danger';

type Props = {
  open: boolean;
  title: string;
  description?: ReactNode;
  error?: string | null;
  confirmLabel: string;
  cancelLabel?: string;
  variant?: ConfirmDialogVariant;
  loading?: boolean;
  icon?: ReactNode;
  onConfirm: () => void | Promise<void>;
  onCancel: () => void;
};

export function ConfirmDialog({
  open,
  title,
  description,
  error = null,
  confirmLabel,
  cancelLabel = 'Cancelar',
  variant = 'primary',
  loading = false,
  icon = '⚠️',
  onConfirm,
  onCancel,
}: Props) {
  const titleId = useId();
  const descId = useId();
  const confirmRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const t = window.setTimeout(() => confirmRef.current?.focus(), 0);
    return () => window.clearTimeout(t);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (!loading) onCancel();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, loading, onCancel]);

  useEffect(() => {
    if (!open) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [open]);

  if (!open) return null;

  const headerBg = variant === 'danger' ? 'bg-danger/5' : variant === 'teal' ? 'bg-odoo-teal/5' : 'bg-odoo-purple/5';

  return (
    <div className="fixed inset-0 z-[400] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm animate-in fade-in" role="presentation">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descId : undefined}
        className="relative w-full max-w-md rounded-xl border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card shadow-2xl overflow-hidden flex flex-col animate-in zoom-in-95"
        onClick={(e) => e.stopPropagation()}
      >
        <div className={`px-6 py-5 border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 ${headerBg}`}>
          {icon && <span className="text-2xl">{icon}</span>}
          <h2 id={titleId} className="text-lg font-bold text-text-primary dark:text-text-dark-primary tracking-tight">
            {title}
          </h2>
        </div>

        {description ? (
          <div
            id={descId}
            className="px-6 py-6 text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed"
          >
            {description}
          </div>
        ) : null}

        {error ? (
          <div
            className="px-4 py-2.5 border-b border-ui-border dark:border-ui-dark-border bg-error-light/15 dark:bg-error-dark/20"
            role="alert"
          >
            <p className="text-xs font-medium text-danger-dark dark:text-danger">{error}</p>
          </div>
        ) : null}

        <div className="flex items-center justify-end gap-3 px-6 py-4 bg-ui-body/50 dark:bg-ui-dark-bg/50 border-t border-ui-border dark:border-ui-dark-border">
          <Button
            type="button"
            variant="secondary"
            size="md"
            className="flex-1"
            disabled={loading}
            onClick={onCancel}
          >
            {cancelLabel}
          </Button>
          <Button
            ref={confirmRef}
            type="button"
            variant={variant === 'danger' ? 'outline' : variant}
            size="md"
            loading={loading}
            className={`flex-1 ${variant === 'danger' ? 'text-danger border-danger/40 hover:border-danger hover:bg-danger/5' : ''}`}
            onClick={() => void onConfirm()}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
