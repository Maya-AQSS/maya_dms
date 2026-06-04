import { normalizeBlockContentForEditor } from './normalizeBlockContent';

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null;
}

function normalizeLineText(text: string): string {
  return text.trim().replace(/\s+/g, ' ');
}

/** Etiqueta para diff; usa ruta completa (no solo el nombre) para distinguir medias clonadas. */
export function tiptapImageDiffLabel(attrs: unknown): string {
  const a = asRecord(attrs);
  const src = String(a?.src ?? '').trim();
  if (!src) return '[Imagen vacía]';
  try {
    const path = new URL(src, 'https://local.invalid').pathname.replace(/^\/+/, '');
    if (path) return `[Imagen: ${path}]`;
  } catch {
    /* blob: o rutas relativas */
  }
  if (src.length <= 96) return `[Imagen: ${src}]`;
  return `[Imagen: …${src.slice(-48)}]`;
}

function inlineDiffParts(nodes: unknown[]): string[] {
  const parts: string[] = [];
  for (const raw of nodes) {
    const n = asRecord(raw);
    if (!n) continue;
    const type = String(n.type ?? '');
    if (type === 'text') {
      const t = String(n.text ?? '').replace(/\u00a0/g, ' ');
      if (t) parts.push(t);
    } else if (type === 'hardBreak') {
      parts.push(' ');
    } else if (type === 'link') {
      parts.push(...inlineDiffParts(Array.isArray(n.content) ? n.content : []));
    } else if (type === 'image') {
      parts.push(tiptapImageDiffLabel(n.attrs));
    }
  }
  return parts;
}

/** Legacy BlockNote block → línea de diff (compatibilidad con snapshots antiguos). */
function legacyBlockNoteLine(block: Record<string, unknown>): string | null {
  const content = Array.isArray(block.content) ? block.content : [];
  const inline = content
    .map((c: unknown) => {
      const item = asRecord(c);
      if (!item) return '';
      if (item.type === 'text') return String(item.text ?? '');
      if (item.type === 'link') {
        return inlineDiffParts(Array.isArray(item.content) ? item.content : []).join('');
      }
      return '';
    })
    .join('');
  const type = String(block.type ?? '');
  const props = asRecord(block.props) ?? {};
  let prefix = '';
  if (type === 'heading') prefix = '#'.repeat(Number(props.level ?? 1)) + ' ';
  else if (type === 'bulletListItem') prefix = '• ';
  else if (type === 'numberedListItem') prefix = '1. ';
  const line = normalizeLineText(prefix + inline);
  return line || null;
}

function blockToDiffLines(node: unknown, listPrefix = ''): string[] {
  const n = asRecord(node);
  if (!n) return [];

  // BlockNote legacy blocks carry `props`; TipTap nodes do not.
  if (n.props != null) {
    const legacy = legacyBlockNoteLine(n);
    return legacy ? [listPrefix + legacy] : [];
  }

  const type = String(n.type ?? '');

  if (type === 'image') {
    return [listPrefix + tiptapImageDiffLabel(n.attrs)];
  }

  if (type === 'paragraph' || type === 'heading') {
    const parts = inlineDiffParts(Array.isArray(n.content) ? n.content : []);
    const headingPrefix =
      type === 'heading'
        ? '#'.repeat(Math.max(1, Math.min(6, Number(asRecord(n.attrs)?.level ?? 1)))) + ' '
        : '';
    const text = normalizeLineText(headingPrefix + parts.join(''));
    return text ? [listPrefix + text] : [];
  }

  if (type === 'bulletList' || type === 'orderedList' || type === 'taskList') {
    const items = Array.isArray(n.content) ? n.content : [];
    const lines: string[] = [];
    items.forEach((item, index) => {
      const itemPrefix =
        type === 'orderedList'
          ? `${index + 1}. `
          : type === 'taskList'
            ? '☐ '
            : '• ';
      lines.push(...blockToDiffLines(item, listPrefix + itemPrefix));
    });
    return lines;
  }

  if (type === 'listItem' || type === 'taskItem' || type === 'blockquote') {
    const inner = Array.isArray(n.content) ? n.content : [];
    const lines: string[] = [];
    for (const child of inner) {
      lines.push(...blockToDiffLines(child, listPrefix));
    }
    return lines;
  }

  if (type === 'horizontalRule') {
    return [listPrefix + '---'];
  }

  if (type === 'codeBlock') {
    const text = normalizeLineText(inlineDiffParts(Array.isArray(n.content) ? n.content : []).join(''));
    return text ? [listPrefix + text] : [];
  }

  if (type === 'table') {
    const rows = Array.isArray(n.content) ? n.content : [];
    const lines: string[] = [];
    rows.forEach((row, rowIndex) => {
      const rowNode = asRecord(row);
      if (!rowNode || String(rowNode.type ?? '') !== 'tableRow') return;
      const cells = Array.isArray(rowNode.content) ? rowNode.content : [];
      const cellTexts = cells
        .map((cell) => blockToDiffLines(cell, '').join(' | '))
        .map((t) => t.trim())
        .filter(Boolean);
      if (cellTexts.length > 0) {
        lines.push(
          listPrefix + `[Tabla fila ${rowIndex + 1}] ${cellTexts.join(' | ')}`,
        );
      }
    });
    return lines.length > 0 ? lines : [listPrefix + '[Tabla]'];
  }

  if (type === 'tableRow' || type === 'tableHeader' || type === 'tableCell') {
    const inner = Array.isArray(n.content) ? n.content : [];
    const lines: string[] = [];
    for (const child of inner) {
      lines.push(...blockToDiffLines(child, listPrefix));
    }
    return lines;
  }

  if (type === 'alertBlock') {
    const attrs = asRecord(n.attrs);
    const variant = String(attrs?.variant ?? attrs?.type ?? '').trim();
    const inner = (Array.isArray(n.content) ? n.content : [])
      .flatMap((child) => blockToDiffLines(child, ''))
      .join(' ');
    const label = variant ? `[Aviso: ${variant}]` : '[Aviso]';
    const text = normalizeLineText(`${label} ${inner}`);
    return text ? [listPrefix + text] : [listPrefix + label];
  }

  if (type === 'iframeBlock') {
    const attrs = asRecord(n.attrs);
    const src = String(attrs?.src ?? attrs?.url ?? '').trim();
    if (!src) return [listPrefix + '[Iframe]'];
    const label = tiptapImageDiffLabel({ src });
    return [listPrefix + label.replace('[Imagen:', '[Iframe:')];
  }

  // Cualquier otro nodo TipTap: aplanar hijos antes de descartar
  const inner = Array.isArray(n.content) ? n.content : [];
  if (inner.length > 0) {
    const lines: string[] = [];
    for (const child of inner) {
      lines.push(...blockToDiffLines(child, listPrefix));
    }
    if (lines.length > 0) return lines;
  }

  return [];
}

/**
 * Líneas de texto para el diff lateral (preview / validación).
 * Cubre párrafos, encabezados, listas, tablas, imágenes, código, avisos e iframes.
 * No sustituye `tiptapContentEquals` (comparación canónica del JSON).
 */
export function extractTiptapDiffLines(content: unknown): string[] {
  const nodes = normalizeBlockContentForEditor(content);
  const lines: string[] = [];
  for (const node of nodes) {
    lines.push(...blockToDiffLines(node));
  }
  return lines;
}
