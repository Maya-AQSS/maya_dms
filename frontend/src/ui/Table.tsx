import type { ComponentPropsWithoutRef, JSX, ReactNode } from 'react';

type BaseProps<T extends keyof JSX.IntrinsicElements> = ComponentPropsWithoutRef<T> & {
  children: ReactNode;
  className?: string;
};

export function Table({ children, className = '', ...props }: BaseProps<'table'>) {
  const merged = className ? `min-w-full ${className}` : 'min-w-full';
  return (
    <table className={merged} {...props}>
      {children}
    </table>
  );
}

export function TableHead({ children, className = '', ...props }: BaseProps<'thead'>) {
  const merged = className
    ? `bg-ui-body dark:bg-ui-dark-bg ${className}`
    : 'bg-ui-body dark:bg-ui-dark-bg';
  return (
    <thead className={merged} {...props}>
      {children}
    </thead>
  );
}

export function TableBody({ children, className = '', ...props }: BaseProps<'tbody'>) {
  const merged = className
    ? `divide-y divide-ui-border-l dark:divide-ui-dark-border-l bg-ui-card dark:bg-ui-dark-card ${className}`
    : 'divide-y divide-ui-border-l dark:divide-ui-dark-border-l bg-ui-card dark:bg-ui-dark-card';
  return (
    <tbody className={merged} {...props}>
      {children}
    </tbody>
  );
}

export function TableRow({ children, className = '', ...props }: BaseProps<'tr'>) {
  return (
    <tr className={className} {...props}>
      {children}
    </tr>
  );
}

export function TableHeader({ children, className = '', ...props }: BaseProps<'th'>) {
  const merged = className
    ? `px-4 py-2.5 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-primary/90 font-semibold ${className}`
    : 'px-4 py-2.5 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-primary/90 font-semibold';
  return (
    <th className={merged} {...props}>
      {children}
    </th>
  );
}

export function TableCell({ children, className = '', ...props }: BaseProps<'td'>) {
  return (
    <td className={className} {...props}>
      {children}
    </td>
  );
}
