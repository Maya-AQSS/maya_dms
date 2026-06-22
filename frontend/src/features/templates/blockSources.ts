import type { ReactNode } from 'react';

/**
 * Registro extensible de "fuentes de bloque" para el dropdown "+ Añadir bloque"
 * del wizard. Añadir un tipo nuevo (galería, generador IA, …) es declarativo:
 * una entrada más en BLOCK_SOURCES, sin tocar el wizard.
 */
export interface BlockSourceContext {
  /** ID de la plantilla actual. */
  templateId: string;
  /** Crea un bloque vacío (opcionalmente de un `block_type` de maquetación) y entra en edición. */
  createBlock: (block: Partial<{ name: string; description: string; content: unknown; block_type: 'content' | 'cover' | 'blank' | 'index' }>) => void | Promise<void>;
  /** Abre el modal DocxBlockSplitter. */
  openDocxSplitter: () => void;
  /** Cierra el diálogo activo (id=null) o uno específico. */
  setActiveDialog: (id: string | null) => void;
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
    onSelect: ({ createBlock }) => createBlock({}),
  },
  {
    id: 'docx',
    label: 'Importar desde Word',
    description: 'Subir un .docx y dividir su contenido en bloques',
    icon: '↥',
    onSelect: ({ openDocxSplitter, setActiveDialog }) => {
      openDocxSplitter();
      setActiveDialog('docx-splitter');
    },
  },
  // El tipo de bloque (contenido / portada / hoja en blanco / índice) se
  // elige en el panel "Propiedades" del bloque, no aquí: todo bloque nuevo
  // nace como "contenido" y el usuario lo cambia si lo necesita.
  // Futuras entradas (galería de plantillas, bloques IA, …) entran aquí
  // sin tocar el wizard.
];
