import { useCallback, useRef, useState } from 'react';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface UseAutoSaveResult {
  isSaving: boolean;
  saveStatus: SaveStatus;
  lastSaved: Date | null;
  triggerSave: () => void;
  forceSave: () => Promise<void>;
}

/**
 * Hook de autoguardado con debounce unificado.
 * Reutilizable en WizardStep2Blocks, TemplateEditor y DocumentWizard.
 *
 * @param saveFn  Función async que realiza el guardado.
 * @param delay   Milisegundos de debounce (por defecto 1500ms).
 */
export function useAutoSave(
  saveFn: () => Promise<void>,
  delay = 1500,
): UseAutoSaveResult {
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const savedClearRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Always call the latest version of saveFn
  const saveFnRef = useRef(saveFn);
  saveFnRef.current = saveFn;

  const executeSave = useCallback(async () => {
    setSaveStatus('saving');
    try {
      await saveFnRef.current();
      setLastSaved(new Date());
      setSaveStatus('saved');
      if (savedClearRef.current) clearTimeout(savedClearRef.current);
      savedClearRef.current = setTimeout(() => setSaveStatus('idle'), 3000);
    } catch (error) {
      setSaveStatus('error');
      throw error;
    }
  }, []);

  /** Reinicia el debounce cada vez que se llama. */
  const triggerSave = useCallback((): Promise<void> => {
    return new Promise((resolve, reject) => {
      if (timerRef.current) clearTimeout(timerRef.current);

      setSaveStatus('idle');

      timerRef.current = setTimeout(async () => {
        try {
          await executeSave();
          resolve();
        } catch (error) {
          reject(error);
        }
      }, delay);
    });
  }, [delay, executeSave]);

  /** Ejecuta el guardado inmediatamente, cancelando el debounce pendiente. */
  const forceSave = useCallback(async () => {
    if (timerRef.current) clearTimeout(timerRef.current);
    await executeSave();
  }, [executeSave]);

  return {
    isSaving: saveStatus === 'saving',
    saveStatus,
    lastSaved,
    triggerSave,
    forceSave,
  };
}
