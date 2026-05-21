import { useCallback, useEffect, useRef, useState } from 'react';
import {
  downloadDocumentPdf,
  exportDocumentPdf,
  getDocumentExportStatus,
  type DocumentExportState,
} from '../../../api/documents';

const POLL_INTERVAL_MS = 1500;
const POLL_MAX_ATTEMPTS = 60; // 90 s total, suficiente para WeasyPrint de un doc grande

export interface UseDocumentPdfExportResult {
  /** Estado del flujo end-to-end. */
  state: DocumentExportState | 'idle' | 'downloading';
  /** Mensaje de error legible (cuando state === 'failed'). */
  error: string | null;
  /** Lanza la generación + polling + descarga. No-op si ya está en curso. */
  start: () => Promise<void>;
  /** Resetea a `idle`. Útil para reintentar. */
  reset: () => void;
}

/**
 * Hook que orquesta el flujo de descarga del PDF/UA de un documento.
 *
 * Flujo: `start()` → POST /export-pdf → poll cada 1.5 s sobre /export-status
 * → cuando `ready`, GET /pdf como blob → descarga automática.
 *
 * El backend ya hace el job idempotente: si el PDF se generó hace poco
 * y sigue válido en disco, `start()` devuelve `state=ready` sin
 * re-encolar.
 */
export function useDocumentPdfExport(
  documentId: string | undefined,
  documentName: string | undefined,
): UseDocumentPdfExportResult {
  const [state, setState] = useState<DocumentExportState | 'idle' | 'downloading'>('idle');
  const [error, setError] = useState<string | null>(null);
  const pollAttemptsRef = useRef(0);
  const timerRef = useRef<number | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
      if (timerRef.current) {
        window.clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };
  }, []);

  const finishWithError = useCallback((message: string) => {
    if (!mountedRef.current) return;
    setError(message);
    setState('failed');
  }, []);

  const triggerDownload = useCallback(async () => {
    if (!documentId) return;
    setState('downloading');
    try {
      await downloadDocumentPdf(documentId, documentName ?? 'documento');
      if (mountedRef.current) {
        setState('ready');
      }
    } catch (e) {
      finishWithError(e instanceof Error ? e.message : 'No se pudo descargar el PDF.');
    }
  }, [documentId, documentName, finishWithError]);

  const pollOnce = useCallback(async () => {
    if (!documentId || !mountedRef.current) return;
    try {
      const payload = await getDocumentExportStatus(documentId);
      if (!mountedRef.current) return;
      setState(payload.state);
      if (payload.state === 'ready') {
        await triggerDownload();
        return;
      }
      if (payload.state === 'failed') {
        finishWithError(payload.error ?? 'La generación del PDF falló.');
        return;
      }
      pollAttemptsRef.current += 1;
      if (pollAttemptsRef.current >= POLL_MAX_ATTEMPTS) {
        finishWithError('La generación del PDF está tardando demasiado. Inténtalo de nuevo.');
        return;
      }
      timerRef.current = window.setTimeout(() => void pollOnce(), POLL_INTERVAL_MS);
    } catch (e) {
      finishWithError(e instanceof Error ? e.message : 'No se pudo consultar el estado del PDF.');
    }
  }, [documentId, triggerDownload, finishWithError]);

  const start = useCallback(async () => {
    if (!documentId) return;
    if (state === 'queued' || state === 'processing' || state === 'downloading') return;
    setError(null);
    pollAttemptsRef.current = 0;
    try {
      const initial = await exportDocumentPdf(documentId);
      if (!mountedRef.current) return;
      setState(initial.state);
      if (initial.state === 'ready') {
        await triggerDownload();
        return;
      }
      if (initial.state === 'failed') {
        finishWithError(initial.error ?? 'La generación del PDF falló.');
        return;
      }
      // queued | processing → arrancar polling.
      timerRef.current = window.setTimeout(() => void pollOnce(), POLL_INTERVAL_MS);
    } catch (e) {
      finishWithError(e instanceof Error ? e.message : 'No se pudo iniciar la exportación.');
    }
  }, [documentId, state, pollOnce, triggerDownload, finishWithError]);

  const reset = useCallback(() => {
    if (timerRef.current) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    pollAttemptsRef.current = 0;
    setError(null);
    setState('idle');
  }, []);

  return { state, error, start, reset };
}
