/**
 * Validation utilities for template blocks.
 */

export function validateBlockName(name: string): string {
  if (!name.trim()) return 'El nombre del bloque es obligatorio';
  if (name.trim().toLowerCase() === 'bloque sin nombre') return '"Bloque sin nombre" no es un nombre válido';
  return '';
}

/**
 * Checks if parsed content is a blank BlockNote document (whitespace-only).
 * Returns true if content should be normalized to null.
 */
export function isBlankBlockNoteContent(parsedContent: unknown): boolean {
  if (!Array.isArray(parsedContent) || parsedContent.length === 0) return false;

  type BlockNoteNode = { type?: string; content?: Array<{ text?: unknown }> };
  return (parsedContent as BlockNoteNode[]).every((b) =>
    b.type !== 'image' &&
    b.type !== 'table' && (
      !Array.isArray(b.content) ||
      b.content.length === 0 ||
      b.content.every((c) => typeof c.text !== 'string' || !c.text.trim())
    ),
  );
}

export type TiptapNode = {
  type?: string;
  text?: string;
  content?: TiptapNode[];
};

/** Tipos de nodo sin texto que cuentan como contenido real.
 *  Incluye iframe/alert (fix histórico: el saneado los borraba del Contenido). */
const MEANINGFUL_NODE_TYPES: ReadonlySet<string> = new Set([
  'image',
  'table',
  'bulletList',
  'orderedList',
  'iframe',
  'alert',
]);

/**
 * Comprueba recursivamente si un nodo Tiptap aporta contenido real (DMS-F02).
 * Función PURA — sustituye al validador inline de WizardStep2Blocks que
 * mutaba estado React durante la recursión (setMeaningFullContent por nodo).
 * Misma tabla de verdad que el validador histórico:
 * - `text` → significativo solo si `text.trim()` no está vacío.
 * - image/table/bulletList/orderedList/iframe/alert → siempre significativos.
 * - resto → significativo si algún hijo lo es (párrafos vacíos → false).
 */
export function hasMeaningfulTiptapNode(node: TiptapNode): boolean {
  if (node.type === 'text') {
    return typeof node.text === 'string' && node.text.trim().length > 0;
  }
  if (node.type && MEANINGFUL_NODE_TYPES.has(node.type)) {
    return true;
  }
  return Array.isArray(node.content) ? node.content.some(hasMeaningfulTiptapNode) : false;
}

/**
 * Versión tolerante a las formas de contenido de bloque (array pelado de
 * nodos o doc Tiptap `{type:'doc'}`); null/string → sin contenido.
 */
export function tiptapContentHasMeaning(parsed: unknown): boolean {
  if (Array.isArray(parsed)) {
    return parsed.some((node) => hasMeaningfulTiptapNode(node as TiptapNode));
  }
  if (parsed !== null && typeof parsed === 'object') {
    return hasMeaningfulTiptapNode(parsed as TiptapNode);
  }
  return false;
}

/**
 * Parses JSON string safely, returning null on parse error.
 */
export function safeJsonParse(json: string | null): unknown {
  if (!json) return null;
  try {
    return JSON.parse(json);
  } catch {
    return null;
  }
}
