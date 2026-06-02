import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import type { TargetBlock } from './types';

/**
 * Pure chunk grouping logic — no React dependencies.
 * Converts chunks and assignments into block-ordered payloads.
 */

export function groupChunksByTarget(
  chunks: BlockChunk[],
  assignments: Map<number, string>,
  targetId: string,
): BlockChunk[] {
  return chunks
    .filter((c) => assignments.get(c.index) === targetId)
    .sort((a, b) => a.index - b.index);
}

export function autoSplitByHeadingLevel(
  chunks: BlockChunk[],
  level: number,
  blockCounter: number,
): {
  targets: TargetBlock[];
  assignments: Map<number, string>;
  nextCounter: number;
} {
  const newTargets: TargetBlock[] = [];
  const newAssignments = new Map<number, string>();
  let currentId: string | null = null;
  let counter = blockCounter;
  let n = 0;

  for (const c of chunks) {
    if (c.type === 'heading' && (c.level ?? 99) <= level) {
      currentId = `tb-${++counter}`;
      newTargets.push({ id: currentId, name: c.text.slice(0, 60) || `Bloque ${++n}` });
    }
    if (currentId) newAssignments.set(c.index, currentId);
  }

  return {
    targets: newTargets,
    assignments: newAssignments,
    nextCounter: counter,
  };
}
