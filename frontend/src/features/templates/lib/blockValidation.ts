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
