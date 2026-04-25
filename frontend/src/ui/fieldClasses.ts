/**
 * Clases compartidas para controles de formulario (Maya / Odoo).
 */
const FIELD_SHELL =
  'w-full rounded border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary disabled:opacity-50 disabled:cursor-not-allowed';

/** Padding y tipografía por contexto. */
export const FIELD_SIZE_CLASS = {
  /** Formularios en paneles (alta, filtros amplios). */
  comfortable: 'px-3 py-2 text-sm',
  /** Campos en tarjetas (nombre, descripción). */
  md: 'px-3 py-1.5 text-sm',
  /** Selects y campos en rejilla compacta dentro de tarjeta. */
  sm: 'px-2 py-1.5 text-sm',
  /** UUID / jerarquía. */
  mono: 'px-2 py-1.5 text-xs font-mono',
} as const;

export type FieldSize = keyof typeof FIELD_SIZE_CLASS;

export function fieldControlClass(size: FieldSize, extraClassName?: string): string {
  const extra = extraClassName?.trim();
  return extra ? `${FIELD_SHELL} ${FIELD_SIZE_CLASS[size]} ${extra}` : `${FIELD_SHELL} ${FIELD_SIZE_CLASS[size]}`;
}
