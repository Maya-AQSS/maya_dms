import { useEffect } from 'react';
import { Button } from '@maya/shared-ui-react';

type Props = {
  open: boolean;
  entityType: 'template' | 'document';
  entityId: string;
  onClose: () => void;
};

// TODO: conectar con endpoint de versiones cuando esté disponible
export function VersionHistoryPanel({ open, entityType: _entityType, entityId: _entityId, onClose }: Props) {
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[300] flex justify-end" role="presentation">
      <div
        className="absolute inset-0 bg-black/30 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label="Historial de versiones"
        className="relative w-full max-w-sm h-full bg-ui-card dark:bg-ui-dark-card border-l border-ui-border dark:border-ui-dark-border shadow-2xl flex flex-col animate-in slide-in-from-right-4"
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Historial de versiones
          </h2>
          <Button type="button" variant="ghost" size="xs" onClick={onClose}>
            ✕
          </Button>
        </div>
        <div className="flex-1 flex items-center justify-center px-6">
          <p className="text-sm text-text-muted dark:text-text-dark-muted text-center leading-relaxed">
            El historial de versiones estará disponible próximamente.
          </p>
        </div>
      </aside>
    </div>
  );
}
