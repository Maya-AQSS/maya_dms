export interface HeadingEntry {
  level: number;
  text: string;
}

/** Texto plano concatenado de un nodo tiptap/BlockNote (recursivo sobre `content`). */
function nodeText(node: unknown): string {
  if (!node || typeof node !== 'object') return '';
  const n = node as { type?: string; text?: string; content?: unknown[] };
  if (n.type === 'text' && typeof n.text === 'string') return n.text;
  if (Array.isArray(n.content)) return n.content.map(nodeText).join('');
  return '';
}

/**
 * Extrae los encabezados (H1–H3) del contenido de un bloque, en orden.
 *
 * Soporta las 3 formas en que se guarda el contenido en este proyecto:
 *  - TipTap doc: `{ type:'doc', content:[...] }`
 *  - Array pelado de nodos: `[ { type:'heading', attrs:{level}, ... } ]`
 *  - BlockNote: nivel en `props.level` en vez de `attrs.level`.
 */
export function extractHeadings(content: unknown): HeadingEntry[] {
  const root = Array.isArray(content)
    ? content
    : (content && typeof content === 'object' && Array.isArray((content as { content?: unknown[] }).content)
        ? (content as { content: unknown[] }).content
        : []);
  const out: HeadingEntry[] = [];
  const walk = (nodes: unknown[]) => {
    for (const node of nodes) {
      if (!node || typeof node !== 'object') continue;
      const n = node as {
        type?: string;
        attrs?: { level?: number };
        props?: { level?: number };
        content?: unknown[];
      };
      if (n.type === 'heading') {
        const rawLevel = Number(n.attrs?.level ?? n.props?.level) || 1;
        const level = Math.min(3, Math.max(1, rawLevel));
        const text = nodeText(n).trim();
        if (text !== '') out.push({ level, text });
      } else if (Array.isArray(n.content)) {
        walk(n.content);
      }
    }
  };
  walk(root);
  return out;
}
