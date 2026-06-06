import { isSemanticallyEmptyTiptapContent } from '@ceedcv-maya/shared-editor-react';
import { normalizeBlockContentForEditor } from './normalizeBlockContent';

/**
 * Concatena dos contenidos TipTap (acepta array de bloques o envoltura `doc`)
 * en un único array de bloques para persistencia. Recorta los nodos finales
 * semánticamente vacíos del primero para evitar párrafos en blanco en la unión.
 *
 * Usado por el paso de migración del wizard cuando el usuario elige «Anexar»:
 * el contenido antiguo se añade debajo del contenido por defecto del bloque nuevo.
 */
export function concatTiptapContent(prev: unknown, next: unknown): unknown[] {
  const head = normalizeBlockContentForEditor(prev);
  const tail = normalizeBlockContentForEditor(next);

  if (head.length === 0) return tail;
  if (tail.length === 0) return head;

  const trimmedHead = [...head];
  while (
    trimmedHead.length > 0 &&
    isSemanticallyEmptyTiptapContent([trimmedHead[trimmedHead.length - 1]])
  ) {
    trimmedHead.pop();
  }

  return [...trimmedHead, ...tail];
}
