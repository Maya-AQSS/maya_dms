import { useCallback, useEffect, useRef, useState } from 'react';
import { downloadDocumentPdf, type DocumentExportState } from '../../../api/documents';

export interface UseDocumentPdfExportResult {
  /** Estado del flujo. */
  state: DocumentExportState | 'idle' | 'downloading';
  /** Mensaje de error legible (cuando state === 'failed'). */
  error: string | null;
  /** Genera y descarga el PDF. No-op si ya está en curso. */
  start: () => Promise<void>;
  /** Resetea a `idle`. Útil para reintentar. */
  reset: () => void;
}

/**
 * Hook de descarga del PDF/UA de un documento (o de una versión histórica).
 *
 * Flujo SÍNCRONO, igual que el PDF de muestra de themes: `start()` → GET
 * `/pdf` (el backend genera el PDF con WeasyPrint en la propia respuesta) →
 * blob → descarga automática. Sin cola ni polling.
 */
export function useDocumentPdfExport(
  documentId: string | undefined,
  documentName: string | undefined,
  versionId?: string,
): UseDocumentPdfExportResult {
  const [state, setState] = useState<DocumentExportState | 'idle' | 'downloading'>('idle');
  const [error, setError] = useState<string | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const start = useCallback(async () => {
    if (!documentId || state === 'downloading') return;
    setError(null);
    setState('downloading');
    try {
      await downloadDocumentPdf(documentId, documentName ?? 'documento', versionId);
      if (mountedRef.current) setState('ready');
    } catch (e) {
      if (!mountedRef.current) return;
      setError(e instanceof Error ? e.message : 'No se pudo descargar el PDF.');
      setState('failed');
    }
  }, [documentId, documentName, versionId, state]);

  const reset = useCallback(() => {
    setError(null);
    setState('idle');
  }, []);

  return { state, error, start, reset };
}
