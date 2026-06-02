import type { ReactNode } from 'react';

/**
 * Registro extensible de "fuentes de bloque" para el dropdown "+ Añadir bloque"
 * del wizard. Añadir un tipo nuevo (galería, generador IA, …) es declarativo:
 * una entrada más en BLOCK_SOURCES, sin tocar el wizard.
 */
export interface BlockSourceContext {
  /** Crea un bloque simple vacío y entra en edición (comportamiento actual). */
  addSimpleBlock: () => void | Promise<void>;
  /** Abre el modal DocxBlockSplitter. */
  openDocxSplitter: () => void;
  /** Predicado de permisos para `isAvailable`. */
  hasPermission: (perm: string) => boolean;
}

export interface BlockSource {
  /** Id estable, usado como key del item. */
  id: string;
  /** Texto principal del item. */
  label: string;
  /** Texto de ayuda bajo el label. */
  description?: string;
  /** Glifo a la izquierda del label. */
  icon?: ReactNode;
  /** Oculta la entrada cuando devuelve false. */
  isAvailable?: (ctx: BlockSourceContext) => boolean;
  /** Handler al seleccionar. */
  onSelect: (ctx: BlockSourceContext) => void | Promise<void>;
}

export const BLOCK_SOURCES: BlockSource[] = [
  {
    id: 'simple',
    label: 'Bloque simple',
    description: 'Crear un bloque vacío editable',
    icon: '+',
    onSelect: ({ addSimpleBlock }) => addSimpleBlock(),
  },
  {
    id: 'docx',
    label: 'Importar desde Word',
    description: 'Subir un .docx y dividir su contenido en bloques',
    icon: '↥',
    onSelect: ({ openDocxSplitter }) => openDocxSplitter(),
  },
  // Futuras entradas (galería de plantillas, bloques IA, …) entran aquí
  // sin tocar el wizard.
];
