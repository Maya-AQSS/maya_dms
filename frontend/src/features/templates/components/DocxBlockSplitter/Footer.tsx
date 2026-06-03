import { Button } from '@ceedcv-maya/shared-ui-react';
import type { Status } from './types';

export interface FooterProps {
  status: Status;
  progress: { current: number; total: number } | null;
  targetCount: number;
  canConfirm: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}

export function Footer({
  status,
  progress,
  targetCount,
  canConfirm,
  onCancel,
  onConfirm,
}: FooterProps) {
  return (
    <div className="flex items-center justify-between border-t border-ui-border px-5 py-3 dark:border-ui-dark-border">
      <div className="text-xs text-text-muted dark:text-text-dark-muted">
        {status === 'creating' && progress
          ? `Creando bloque ${progress.current}/${progress.total}…`
          : status === 'ready' && !canConfirm && targetCount > 0
            ? 'Cada bloque debe tener al menos un elemento.'
            : ''}
      </div>
      <div className="flex gap-2">
        <Button variant="outline" onClick={onCancel} disabled={status === 'creating'}>
          Cancelar
        </Button>
        <Button variant="primary" onClick={onConfirm} disabled={!canConfirm}>
          {status === 'creating' ? 'Creando…' : `Crear ${targetCount} bloque${targetCount === 1 ? '' : 's'}`}
        </Button>
      </div>
    </div>
  );
}
