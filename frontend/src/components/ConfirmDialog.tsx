import { useEffect, useId, useRef, type ReactNode } from 'react';

export type ConfirmDialogVariant = 'primary' | 'teal' | 'danger';

type Props = {
  open: boolean;
  title: string;
  description?: ReactNode;
  confirmLabel: string;
  cancelLabel?: string;
  /** Estilo del botón de confirmación */
  variant?: ConfirmDialogVariant;
  /** Deshabilita botones (p. ej. petición en curso) */
  loading?: boolean;
  onConfirm: () => void | Promise<void>;
  onCancel: () => void;
};

/** Botones primarios alineados con formularios Odoo / Maya (compactos, sin ring exagerado). */
function confirmButtonClass(variant: ConfirmDialogVariant): string {
  switch (variant) {
    case 'danger':
      return 'bg-danger text-text-inverse hover:brightness-95 active:brightness-90';
    case 'teal':
      return 'bg-odoo-teal text-text-inverse hover:bg-odoo-teal-d active:bg-odoo-teal-d';
    default:
      return 'bg-odoo-purple text-text-inverse hover:bg-odoo-purple-d active:bg-odoo-purple-d';
  }
}

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

  const btnBase =
    'rounded px-3 py-1.5 text-xs font-medium transition-colors disabled:opacity-50 disabled:pointer-events-none focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 focus-visible:ring-offset-1 focus-visible:ring-offset-ui-card dark:focus-visible:ring-offset-ui-dark-card';

  return (
    <div
      className="fixed inset-0 z-[400] flex items-center justify-center p-4"
      role="presentation"
    >
      <button
        type="button"
        className="absolute inset-0 bg-ui-sidebar/50 dark:bg-black/50"
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
        {/* Cabecera tipo ventana Odoo (barra de título corporativa) */}
        <div className="flex items-center min-h-10 px-3 py-2 bg-odoo-purple dark:bg-odoo-dark-purple-d border-b border-odoo-purple-d dark:border-odoo-dark-purple-l">
          <h2
            id={titleId}
            className="text-sm font-semibold text-text-inverse tracking-tight"
          >
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

        {/* Pie: fondo gris suave como formularios Odoo */}
        <div className="flex flex-row flex-wrap justify-end gap-2 px-3 py-2.5 bg-ui-body dark:bg-ui-dark-bg/90 border-t border-ui-border-l dark:border-ui-dark-border-l">
          <button
            type="button"
            disabled={loading}
            onClick={onCancel}
            className={`${btnBase} border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary hover:bg-white dark:hover:bg-ui-dark-card`}
          >
            {cancelLabel}
          </button>
          <button
            ref={confirmRef}
            type="button"
            disabled={loading}
            onClick={() => void onConfirm()}
            className={`${btnBase} inline-flex items-center justify-center gap-2 min-w-[5.5rem] ${confirmButtonClass(variant)}`}
          >
            {loading ? (
              <span
                className="size-3 shrink-0 animate-spin rounded-full border-2 border-text-inverse/30 border-t-text-inverse"
                aria-hidden
              />
            ) : null}
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
