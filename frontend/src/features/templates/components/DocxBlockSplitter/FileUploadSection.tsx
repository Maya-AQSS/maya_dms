import { useRef, useCallback } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import type { Status } from './types';

export interface FileUploadSectionProps {
  status: Status;
  error: string | null;
  onPickFile: (file: File) => void;
}

export function FileUploadSection({ status, error, onPickFile }: FileUploadSectionProps) {
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const handleClick = useCallback(() => {
    fileInputRef.current?.click();
  }, []);

  const handleFileChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      e.target.value = '';
      if (file) onPickFile(file);
    },
    [onPickFile],
  );

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-10">
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
        Selecciona un archivo <strong>.docx</strong> para trocearlo en bloques de plantilla.
      </p>
      <Button
        variant="primary"
        disabled={status === 'parsing'}
        onClick={handleClick}
      >
        {status === 'parsing' ? 'Procesando…' : 'Elegir archivo .docx'}
      </Button>
      {error && <p className="text-sm text-danger-dark">{error}</p>}
      <input
        ref={fileInputRef}
        type="file"
        accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        className="hidden"
        onChange={handleFileChange}
      />
    </div>
  );
}
