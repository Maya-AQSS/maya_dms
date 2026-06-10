import {
  diffTiptapContentLines,
  diffTiptapRemovedContent,
  type TiptapDiffLine,
} from './tiptapLineDiff';

/**
 * Bloque normalizado de una versión publicada, listo para comparar entre dos
 * versiones (documento o plantilla). `content` es el contenido Tiptap efectivo
 * de esa versión para ese bloque.
 */
export type ComparableBlock = {
  /** Identidad estable del bloque entre versiones (template_block_id). */
  key: string;
  title: string | null;
  content: unknown;
  sortOrder: number;
};

export type VersionBlockChangeStatus = 'added' | 'removed' | 'modified';

export type VersionBlockChange = {
  key: string;
  title: string | null;
  /** Posición 1-based del bloque (en la versión B; en A si fue eliminado). */
  blockNumber: number;
  status: VersionBlockChangeStatus;
  lines: TiptapDiffLine[];
};

function bySortOrder(a: ComparableBlock, b: ComparableBlock): number {
  return a.sortOrder - b.sortOrder;
}

/** Mapa key → posición 1-based según el orden de `sortOrder`. */
function numberByKey(blocks: ComparableBlock[]): Map<string, number> {
  const map = new Map<string, number>();
  [...blocks].sort(bySortOrder).forEach((b, idx) => {
    if (!map.has(b.key)) map.set(b.key, idx + 1);
  });
  return map;
}

/**
 * Compara los bloques de dos versiones publicadas (A = anterior, B = posterior)
 * emparejándolos por `key`. Devuelve solo los bloques con cambios: añadidos
 * (solo en B), eliminados (solo en A) y modificados (contenido distinto).
 */
export function compareVersionBlocks(
  blocksA: ComparableBlock[],
  blocksB: ComparableBlock[],
  options: { emptyBlockLabel: string },
): VersionBlockChange[] {
  const mapA = new Map(blocksA.map((b) => [b.key, b]));
  const mapB = new Map(blocksB.map((b) => [b.key, b]));
  const numbersA = numberByKey(blocksA);
  const numbersB = numberByKey(blocksB);

  const changes: VersionBlockChange[] = [];

  // Recorremos B en orden: bloques modificados y añadidos.
  for (const blockB of [...blocksB].sort(bySortOrder)) {
    const blockA = mapA.get(blockB.key);
    if (!blockA) {
      const added = diffTiptapContentLines(null, blockB.content);
      changes.push({
        key: blockB.key,
        title: blockB.title,
        blockNumber: numbersB.get(blockB.key) ?? 0,
        status: 'added',
        lines:
          added.length > 0
            ? added
            : [{ type: 'added', text: options.emptyBlockLabel }],
      });
      continue;
    }
    const lines = diffTiptapContentLines(blockA.content, blockB.content);
    if (lines.length === 0) continue; // sin cambios → se omite
    changes.push({
      key: blockB.key,
      title: blockB.title ?? blockA.title,
      blockNumber: numbersB.get(blockB.key) ?? 0,
      status: 'modified',
      lines,
    });
  }

  // Bloques presentes solo en A → eliminados en B.
  for (const blockA of [...blocksA].sort(bySortOrder)) {
    if (mapB.has(blockA.key)) continue;
    changes.push({
      key: blockA.key,
      title: blockA.title,
      blockNumber: numbersA.get(blockA.key) ?? 0,
      status: 'removed',
      lines: diffTiptapRemovedContent(blockA.content, options.emptyBlockLabel),
    });
  }

  return changes;
}
