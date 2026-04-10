import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';

export type ButtonVariant =
  | 'primary'
  | 'secondary'
  | 'danger'
  | 'teal'
  /** Borde neutro (editar, cancelar). */
  | 'outline'
  /** Borde teal (clonar). */
  | 'outlineTeal'
  /** Borde advertencia (eliminar en fila). */
  | 'outlineWarning'
  /** Solo texto enlazado (p. ej. ✕, “Cerrar” en línea). */
  | 'ghost'
  /** Sin estilos de superficie; usar `className` (backdrop, nav, icono). */
  | 'unstyled';

export type ButtonSize = 'xs' | 'sm' | 'md';

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  /** Opcional si el botón solo usa `aria-label` (p. ej. backdrop). */
  children?: ReactNode;
};

const focusRing =
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 focus-visible:ring-offset-1 focus-visible:ring-offset-ui-card dark:focus-visible:ring-offset-ui-dark-card';

const sizeClass: Record<ButtonSize, string> = {
  xs: 'px-2 py-1 text-xs font-medium rounded',
  sm: 'px-3 py-1.5 text-xs font-medium rounded',
  md: 'px-4 py-2 text-sm font-medium rounded',
};

const variantClass: Record<ButtonVariant, string> = {
  primary: 'bg-odoo-purple text-text-inverse hover:bg-odoo-purple-d active:bg-odoo-purple-d',
  secondary:
    'border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary hover:bg-white dark:hover:bg-ui-dark-bg',
  danger: 'bg-danger text-text-inverse hover:brightness-95 active:brightness-90',
  teal: 'bg-odoo-teal text-text-inverse hover:bg-odoo-teal-d active:bg-odoo-teal-d',
  outline:
    'border border-ui-border dark:border-ui-dark-border text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg',
  outlineTeal: 'border border-odoo-teal/40 text-odoo-teal hover:bg-odoo-teal/10',
  outlineWarning: 'border border-warning/40 text-warning-dark hover:bg-warning-light/30 dark:hover:bg-warning-dark/15',
  ghost:
    'border-0 bg-transparent px-1 py-0.5 text-text-link dark:text-text-dark-link hover:underline disabled:opacity-40 rounded',
  unstyled: '',
};

function spinnerClass(variant: ButtonVariant): string {
  if (
    variant === 'secondary' ||
    variant === 'outline' ||
    variant === 'outlineTeal' ||
    variant === 'outlineWarning' ||
    variant === 'ghost' ||
    variant === 'unstyled'
  ) {
    return 'border-text-primary/35 border-t-text-primary dark:border-text-dark-primary/35 dark:border-t-text-dark-primary';
  }
  return 'border-text-inverse/30 border-t-text-inverse';
}

/**
 * Botón reutilizable alineado con Maya / Odoo (variantes sólidas, contorno y ghost).
 */
export const Button = forwardRef<HTMLButtonElement, Props>(function Button(
  {
    type = 'button',
    variant = 'primary',
    size = 'sm',
    loading = false,
    disabled,
    className = '',
    children,
    ...rest
  },
  ref,
) {
  const sizeStyles =
    variant === 'ghost' ? 'text-xs font-medium rounded' : variant === 'unstyled' ? '' : sizeClass[size];
  const layoutClass =
    variant === 'unstyled'
      ? 'inline-flex'
      : 'inline-flex items-center justify-center gap-2';
  const base = `${layoutClass} transition-colors disabled:opacity-50 disabled:pointer-events-none ${focusRing} ${sizeStyles} ${variantClass[variant]}`;
  const merged = className ? `${base} ${className}` : base;

  return (
    <button ref={ref} type={type} disabled={disabled || loading} className={merged} {...rest}>
      {loading ? (
        <span
          className={`size-3 shrink-0 animate-spin rounded-full border-2 ${spinnerClass(variant)}`}
          aria-hidden
        />
      ) : null}
      {children ?? null}
    </button>
  );
});
