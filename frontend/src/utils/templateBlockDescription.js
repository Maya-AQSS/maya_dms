/**
 * Descripción de bloque de plantilla: texto plano.
 * Convierte legado (objeto/array BlockNote o JSON string) a string para formularios y lectura.
 */
export function templateBlockDescriptionToPlainText(input) {
    if (input == null)
        return '';
    if (typeof input === 'string') {
        const t = input.trim();
        if (!t)
            return '';
        if (t.startsWith('{') || t.startsWith('[')) {
            try {
                const parsed = JSON.parse(t);
                const extracted = extractFromLegacy(parsed);
                if (extracted)
                    return extracted;
            }
            catch {
                /* texto que parece JSON pero no lo es */
            }
        }
        return t;
    }
    if (typeof input === 'object') {
        return extractFromLegacy(input) ?? '';
    }
    return String(input);
}
function extractFromLegacy(node) {
    if (!node || typeof node !== 'object')
        return '';
    const n = node;
    const type = n.type;
    if (type === 'doc' && Array.isArray(n.content)) {
        return collapseBlocks(n.content);
    }
    if (Array.isArray(node) && node.length > 0) {
        return collapseBlocks(node);
    }
    if (type === 'paragraph' || type === 'heading' || type === 'blockquote') {
        return extractInline(n.content);
    }
    if (type === 'bulletListItem' || type === 'numberedListItem') {
        const inline = extractInline(n.content);
        const children = Array.isArray(n.children) ? collapseBlocks(n.children) : '';
        return [inline, children].filter(Boolean).join('\n\n');
    }
    const parts = [];
    if (Array.isArray(n.content))
        parts.push(collapseBlocks(n.content));
    if (Array.isArray(n.children) && n.children.length > 0) {
        parts.push(collapseBlocks(n.children));
    }
    return parts.filter(Boolean).join('\n\n');
}
function collapseBlocks(blocks) {
    const out = [];
    for (const b of blocks) {
        const t = extractFromLegacy(b);
        if (t)
            out.push(t);
    }
    return out.join('\n\n');
}
function extractInline(content) {
    if (!Array.isArray(content))
        return '';
    let s = '';
    for (const item of content) {
        if (!item || typeof item !== 'object')
            continue;
        const it = item;
        if (it.type === 'text' && typeof it.text === 'string')
            s += it.text;
        else
            s += extractFromLegacy(item);
    }
    return s.trim();
}
