import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from './Button';

export type ConfirmDialogVariant = 'primary' | 'teal' | 'danger';

type Props = {
  open: boolean;
  title: string;
  description?: ReactNode;
  confirmLabel: string;
  cancelLabel?: string;
  variant?: ConfirmDialogVariant;
  loading?: boolean;
  onConfirm: () => void | Promise<void>;
  onCancel: () => void;
};

/**
 * Diálogo modal de confirmación al estilo Odoo / Maya DMS:
 * cabecera morada corporativa, cuerpo claro, pie con botones compactos.
 */
export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  cancelLabel = 'Cancelar',
  variant = 'primary',
  loading = false,
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

  return (
    <div className="fixed inset-0 z-[400] flex items-center justify-center p-4" role="presentation">
      <Button
        type="button"
        variant="unstyled"
        className="absolute inset-0 z-0 min-h-0 w-full cursor-pointer bg-ui-sidebar/50 dark:bg-black/50 focus-visible:ring-inset focus-visible:ring-white/40"
        aria-label="Cerrar diálogo"
        disabled={loading}
        onClick={() => {
          if (!loading) onCancel();
        }}
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descId : undefined}
        className="relative w-full max-w-[28rem] rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card shadow-dropdown overflow-hidden flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center min-h-10 px-3 py-2 bg-odoo-purple dark:bg-odoo-dark-purple-d border-b border-odoo-purple-d dark:border-odoo-dark-purple-l">
          <h2 id={titleId} className="text-sm font-semibold text-text-inverse tracking-tight">
            {title}
          </h2>
        </div>

        {description ? (
          <div
            id={descId}
            className="px-4 py-3 text-sm text-text-secondary dark:text-text-dark-secondary leading-snug border-b border-ui-border-l dark:border-ui-dark-border-l bg-ui-card dark:bg-ui-dark-card"
          >
            {description}
          </div>
        ) : null}

        <div className="flex flex-row flex-wrap justify-end gap-2 px-3 py-2.5 bg-ui-body dark:bg-ui-dark-bg/90 border-t border-ui-border-l dark:border-ui-dark-border-l">
          <Button type="button" variant="secondary" size="sm" disabled={loading} onClick={onCancel}>
            {cancelLabel}
          </Button>
          <Button
            ref={confirmRef}
            type="button"
            variant={variant}
            size="sm"
            loading={loading}
            className="min-w-[5.5rem]"
            onClick={() => void onConfirm()}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
