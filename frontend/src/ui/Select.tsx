import type { SelectHTMLAttributes } from 'react';
import { fieldControlClass, type FieldSize } from './fieldClasses';

type Props = SelectHTMLAttributes<HTMLSelectElement> & {
  fieldSize?: FieldSize;
};

export function Select({ fieldSize = 'sm', className = '', children, ...rest }: Props) {
  return (
    <select className={fieldControlClass(fieldSize, className)} {...rest}>
      {children}
    </select>
  );
}
