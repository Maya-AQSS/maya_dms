import type { InputHTMLAttributes } from 'react';
import { fieldControlClass, type FieldSize } from './fieldClasses';

type Props = InputHTMLAttributes<HTMLInputElement> & {
  fieldSize?: FieldSize;
};

export function TextInput({ fieldSize = 'md', className = '', ...rest }: Props) {
  return <input className={fieldControlClass(fieldSize, className)} {...rest} />;
}
