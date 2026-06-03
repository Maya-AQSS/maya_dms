import type { BlockChunk } from '@ceedcv-maya/shared-editor-react';
import { EditorContentHtml } from '@ceedcv-maya/shared-editor-react';
import type { TargetBlock } from './types';

export interface TargetBlockPanelProps {
  targets: TargetBlock[];
  chunks: BlockChunk[];
  assignments: Map<number, string>;
  onRenameBlock: (id: string, name: string) => void;
  onRemoveBlock: (id: string) => void;
  chunksByTarget: (targetId: string) => BlockChunk[];
}

export function TargetBlockPanel({
  targets,
  chunks,
  assignments,
  onRenameBlock,
  onRemoveBlock,
  chunksByTarget,
}: TargetBlockPanelProps) {
  const assignedCount = assignments.size;

  return (
    <div className="flex w-2/5 flex-col">
      <div className="border-b border-ui-border px-4 py-2 text-xs text-text-muted dark:border-ui-dark-border dark:text-text-dark-muted">
        {targets.length} bloques destino · {assignedCount}/{chunks.length} elementos asignados
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto p-3">
        {targets.length === 0 ? (
          <p className="p-4 text-center text-sm text-text-muted dark:text-text-dark-muted">
            Selecciona elementos a la izquierda y pulsa <strong>+ Nuevo bloque</strong>.
          </p>
        ) : (
          targets.map((t) => {
            const tc = chunksByTarget(t.id);
            return (
              <div
                key={t.id}
                className="mb-3 rounded border border-ui-border p-2 dark:border-ui-dark-border"
              >
                <div className="mb-2 flex items-center gap-2">
                  <input
                    value={t.name}
                    onChange={(e) => onRenameBlock(t.id, e.target.value)}
                    className="min-w-0 flex-1 rounded border border-ui-border px-2 py-1 text-sm dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary"
                  />
                  <span className="shrink-0 text-xs text-text-muted dark:text-text-dark-muted">
                    {tc.length} el.
                  </span>
                  <button
                    type="button"
                    onClick={() => onRemoveBlock(t.id)}
                    className="shrink-0 text-danger-dark hover:opacity-70"
                    aria-label="Eliminar bloque"
                  >
                    🗑
                  </button>
                </div>
                {tc.length === 0 ? (
                  <p className="text-xs text-warning-dark">Sin elementos — asígnale alguno.</p>
                ) : (
                  <div className="max-h-40 overflow-y-auto rounded bg-ui-bg p-2 text-xs dark:bg-ui-dark-bg">
                    <EditorContentHtml html={tc.map((c) => c.html).join('\n')} />
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
