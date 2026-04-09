import type { TextareaHTMLAttributes } from 'react';
import { fieldControlClass, type FieldSize } from './fieldClasses';

type Props = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  fieldSize?: FieldSize;
};

export function TextArea({ fieldSize = 'md', className = '', ...rest }: Props) {
  return <textarea className={fieldControlClass(fieldSize, className)} {...rest} />;
}
