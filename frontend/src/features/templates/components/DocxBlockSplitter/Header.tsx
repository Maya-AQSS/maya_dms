export interface HeaderProps {
  filename: string | null;
  onCancel: () => void;
}

export function Header({ filename, onCancel }: HeaderProps) {
  return (
    <div className="flex items-center justify-between border-b border-ui-border px-5 py-3 dark:border-ui-dark-border">
      <div>
        <h2 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
          Importar bloques desde Word
        </h2>
        {filename && <p className="text-xs text-text-muted dark:text-text-dark-muted">{filename}</p>}
      </div>
      <button
        type="button"
        onClick={onCancel}
        className="text-text-muted hover:text-text-primary dark:text-text-dark-muted dark:hover:text-text-dark-primary"
        aria-label="Cerrar"
      >
        ✕
      </button>
    </div>
  );
}
