import { useEffect, useRef, useState } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { BLOCK_SOURCES, type BlockSourceContext } from '../blockSources';

interface Props {
  ctx: BlockSourceContext;
  disabled?: boolean;
}

/**
 * Dropdown "+ Añadir bloque ▾" que itera el registro extensible BLOCK_SOURCES.
 * Añadir un tipo de bloque nuevo = una entrada en BLOCK_SOURCES (sin tocar
 * este componente ni el wizard).
 */
export function AddBlockMenu({ ctx, disabled }: Props) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    window.addEventListener('mousedown', onDown);
    window.addEventListener('keydown', onKey);
    return () => {
      window.removeEventListener('mousedown', onDown);
      window.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const sources = BLOCK_SOURCES.filter((s) => !s.isAvailable || s.isAvailable(ctx));

  return (
    <div ref={rootRef} className="relative">
      <Button
        variant="outline"
        className="w-full border-dashed"
        disabled={disabled}
        onClick={() => setOpen((v) => !v)}
      >
        + Añadir bloque ▾
      </Button>
      {open && (
        <div className="absolute bottom-full left-0 right-0 z-50 mb-1 overflow-hidden rounded-md border border-ui-border bg-white shadow-lg dark:border-ui-dark-border dark:bg-ui-dark-card">
          {sources.map((src) => (
            <button
              key={src.id}
              type="button"
              className="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-ui-bg dark:hover:bg-ui-dark-bg"
              onClick={() => {
                setOpen(false);
                void src.onSelect(ctx);
              }}
            >
              {src.icon && (
                <span aria-hidden className="mt-0.5 text-text-muted dark:text-text-dark-muted">
                  {src.icon}
                </span>
              )}
              <span className="min-w-0">
                <span className="block font-medium leading-tight text-text-primary dark:text-text-dark-primary">
                  {src.label}
                </span>
                {src.description && (
                  <span className="mt-0.5 block text-xs text-text-muted dark:text-text-dark-muted">
                    {src.description}
                  </span>
                )}
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
