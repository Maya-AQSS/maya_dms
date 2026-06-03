/**
 * Pure selection logic — no React hooks, stateless helpers
 * for multi-select behavior (single, toggle, range).
 */

export function toggleSelection(
  current: Set<number>,
  index: number,
  mode: 'single' | 'toggle' | 'range',
  lastClicked: number | null,
): Set<number> {
  const next = new Set(current);
  if (mode === 'single') {
    next.clear();
    next.add(index);
  } else if (mode === 'toggle') {
    next.has(index) ? next.delete(index) : next.add(index);
  } else {
    const from = lastClicked ?? index;
    const [lo, hi] = from <= index ? [from, index] : [index, from];
    for (let i = lo; i <= hi; i++) next.add(i);
  }
  return next;
}
