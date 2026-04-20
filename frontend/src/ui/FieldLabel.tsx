import type { LabelHTMLAttributes, ReactNode } from 'react';

type Props = LabelHTMLAttributes<HTMLLabelElement> & {
  children: ReactNode;
  required?: boolean;
};

export function FieldLabel({ children, required, className = '', ...rest }: Props) {
  const base = 'block text-[10px] font-bold uppercase tracking-wider text-text-muted dark:text-text-dark-muted mb-1';
  return (
    <label className={className ? `${base} ${className}` : base} {...rest}>
      {children}
      {required && <span className="text-danger ml-0.5" title="Obligatorio">*</span>}
    </label>
  );
}
