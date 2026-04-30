/**
 * Normaliza el JSON de un bloque para BlockNote / vista previa.
 * Acepta: array de bloques (formato editor) o envoltura legacy `{ type: 'doc', content: [...] }`.
 */
export function normalizeBlockContentForEditor(raw) {
    if (Array.isArray(raw) && raw.length > 0) {
        return raw;
    }
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
        const o = raw;
        if (o.type === 'doc' && Array.isArray(o.content)) {
            return o.content;
        }
    }
    return [];
}
