import type { LabelHTMLAttributes, ReactNode } from 'react';

type Props = LabelHTMLAttributes<HTMLLabelElement> & {
  children: ReactNode;
};

export function FieldLabel({ children, className = '', ...rest }: Props) {
  const base = 'block text-xs text-text-muted dark:text-text-dark-muted mb-1';
  return (
    <label className={className ? `${base} ${className}` : base} {...rest}>
      {children}
    </label>
  );
}
