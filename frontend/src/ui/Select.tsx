import type { SelectHTMLAttributes } from 'react';
import { fieldControlClass, type FieldSize } from './fieldClasses';

type Props = SelectHTMLAttributes<HTMLSelectElement> & {
  fieldSize?: FieldSize;
  error?: boolean;
};

export function Select({ fieldSize = 'sm', error, className = '', children, ...rest }: Props) {
  const errorCls = error ? 'border-danger dark:border-danger' : '';
  return (
    <select
      className={fieldControlClass(fieldSize, `${errorCls} ${className}`)}
      {...rest}
    >
      {children}
    </select>
  );
}
