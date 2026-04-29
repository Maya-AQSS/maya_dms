/**
 * Descripción de bloque de plantilla: texto plano.
 * Convierte legado (objeto/array BlockNote o JSON string) a string para formularios y lectura.
 */
export function templateBlockDescriptionToPlainText(input: unknown): string {
 if (input == null) return'';
 if (typeof input ==='string') {
 const t = input.trim();
 if (!t) return'';
 if (t.startsWith('{') || t.startsWith('[')) {
 try {
 const parsed: unknown = JSON.parse(t);
 const extracted = extractFromLegacy(parsed);
 if (extracted) return extracted;
 } catch {
 /* texto que parece JSON pero no lo es */
 }
 }
 return t;
 }
 if (typeof input ==='object') {
 return extractFromLegacy(input) ??'';
 }
 return String(input);
}

function extractFromLegacy(node: unknown): string {
 if (!node || typeof node !=='object') return'';
 const n = node as Record<string, unknown>;
 const type = n.type as string | undefined;

 if (type ==='doc' && Array.isArray(n.content)) {
 return collapseBlocks(n.content);
 }

 if (Array.isArray(node) && node.length > 0) {
 return collapseBlocks(node as unknown[]);
 }

 if (type ==='paragraph' || type ==='heading' || type ==='blockquote') {
 return extractInline(n.content);
 }

 if (type ==='bulletListItem' || type ==='numberedListItem') {
 const inline = extractInline(n.content);
 const children = Array.isArray(n.children) ? collapseBlocks(n.children as unknown[]) :'';
 return [inline, children].filter(Boolean).join('\n\n');
 }

 const parts: string[] = [];
 if (Array.isArray(n.content)) parts.push(collapseBlocks(n.content as unknown[]));
 if (Array.isArray(n.children) && n.children.length > 0) {
 parts.push(collapseBlocks(n.children as unknown[]));
 }
 return parts.filter(Boolean).join('\n\n');
}

function collapseBlocks(blocks: unknown[]): string {
 const out: string[] = [];
 for (const b of blocks) {
 const t = extractFromLegacy(b);
 if (t) out.push(t);
 }
 return out.join('\n\n');
}

function extractInline(content: unknown): string {
 if (!Array.isArray(content)) return'';
 let s ='';
 for (const item of content) {
 if (!item || typeof item !=='object') continue;
 const it = item as Record<string, unknown>;
 if (it.type ==='text' && typeof it.text ==='string') s += it.text;
 else s += extractFromLegacy(item);
 }
 return s.trim();
}
