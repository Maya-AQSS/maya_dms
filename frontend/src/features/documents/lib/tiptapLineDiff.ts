import { extractTiptapDiffLines } from './tiptapDiffLines';

export type TiptapDiffLine = { type: 'removed' | 'added' | 'unchanged'; text: string };

/** LCS line diff (compartido por panel de cambios e historiales). */
export function computeLineDiff(
  original: string[],
  modified: string[],
): TiptapDiffLine[] {
  const m = original.length;
  const n = modified.length;
  const dp = Array.from({ length: m + 1 }, () => new Array<number>(n + 1).fill(0));
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      dp[i][j] =
        original[i - 1] === modified[j - 1]
          ? dp[i - 1][j - 1] + 1
          : Math.max(dp[i - 1][j], dp[i][j - 1]);
    }
  }
  const result: TiptapDiffLine[] = [];
  let i = m;
  let j = n;
  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && original[i - 1] === modified[j - 1]) {
      result.unshift({ type: 'unchanged', text: original[i - 1] });
      i--;
      j--;
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      result.unshift({ type: 'added', text: modified[j - 1] });
      j--;
    } else {
      result.unshift({ type: 'removed', text: original[i - 1] });
      i--;
    }
  }
  return result;
}

/**
 * Diff entre dos instantáneas de contenido TipTap (historial de ciclos, etc.).
 * Sin snapshot previo → todas las líneas actuales como añadidas.
 */
export function diffTiptapContentLines(
  previousContent: unknown | null | undefined,
  currentContent: unknown,
): TiptapDiffLine[] {
  const modified = extractTiptapDiffLines(currentContent);
  if (previousContent == null) {
    return modified.map((text) => ({ type: 'added' as const, text }));
  }
  const original = extractTiptapDiffLines(previousContent);
  return computeLineDiff(original, modified).filter((l) => l.type !== 'unchanged');
}

/** Bloque eliminado: líneas del default como eliminadas. */
export function diffTiptapRemovedContent(
  defaultContent: unknown,
  emptyBlockLabel: string,
): TiptapDiffLine[] {
  const lines = extractTiptapDiffLines(defaultContent);
  return lines.length > 0
    ? lines.map((text) => ({ type: 'removed' as const, text }))
    : [{ type: 'removed' as const, text: emptyBlockLabel }];
}

/** Contenido de documento vs default de plantilla (panel «Ver cambios»). */
export function diffTiptapAgainstTemplateDefault(
  defaultContent: unknown,
  content: unknown,
  options?: { fallbackLine?: string; hasNonTextDiff?: boolean },
): TiptapDiffLine[] {
  const lineDiff = diffTiptapContentLines(defaultContent, content);
  if (
    lineDiff.length === 0 &&
    options?.hasNonTextDiff &&
    options.fallbackLine
  ) {
    return [{ type: 'added' as const, text: options.fallbackLine }];
  }
  return lineDiff;
}
