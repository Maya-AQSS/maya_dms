import type { InputHTMLAttributes } from 'react';
import { fieldControlClass, type FieldSize } from './fieldClasses';

type Props = InputHTMLAttributes<HTMLInputElement> & {
  fieldSize?: FieldSize;
  error?: boolean;
};

export function TextInput({ fieldSize = 'md', error, className = '', ...rest }: Props) {
  const errorCls = error ? 'border-danger dark:border-danger' : '';
  return (
    <input
      className={fieldControlClass(fieldSize, `${errorCls} ${className}`)}
      {...rest}
    />
  );
}
