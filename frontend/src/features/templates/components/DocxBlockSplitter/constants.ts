import type { BlockChunkType } from '@ceedcv-maya/shared-editor-react';

export const TYPE_BADGE: Record<BlockChunkType, string> = {
  heading: 'H',
  paragraph: '¶',
  list: '•',
  table: '▦',
  figure: '🖼',
  blockquote: '❝',
  codeBlock: '</>',
  horizontalRule: '―',
  other: '?',
};

export const TYPE_LABEL: Record<BlockChunkType, string> = {
  heading: 'Encabezado',
  paragraph: 'Párrafo',
  list: 'Lista',
  table: 'Tabla',
  figure: 'Figura',
  blockquote: 'Cita',
  codeBlock: 'Código',
  horizontalRule: 'Separador',
  other: 'Elemento',
};
