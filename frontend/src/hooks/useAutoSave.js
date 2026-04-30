import { useCallback, useRef, useState } from 'react';
/**
 * Hook de autoguardado con debounce unificado.
 * Reutilizable en WizardStep2Blocks, TemplateEditor y DocumentWizard.
 *
 * @param saveFn  Función async que realiza el guardado.
 * @param delay   Milisegundos de debounce (por defecto 1500ms).
 */
export function useAutoSave(saveFn, delay = 1500) {
    const [saveStatus, setSaveStatus] = useState('idle');
    const [lastSaved, setLastSaved] = useState(null);
    const timerRef = useRef(null);
    const savedClearRef = useRef(null);
    // Always call the latest version of saveFn
    const saveFnRef = useRef(saveFn);
    saveFnRef.current = saveFn;
    const executeSave = useCallback(async () => {
        setSaveStatus('saving');
        try {
            await saveFnRef.current();
            setLastSaved(new Date());
            setSaveStatus('saved');
            if (savedClearRef.current)
                clearTimeout(savedClearRef.current);
            savedClearRef.current = setTimeout(() => setSaveStatus('idle'), 3000);
        }
        catch {
            setSaveStatus('error');
        }
    }, []);
    /** Reinicia el debounce cada vez que se llama. */
    const triggerSave = useCallback(() => {
        if (timerRef.current)
            clearTimeout(timerRef.current);
        setSaveStatus('idle');
        timerRef.current = setTimeout(() => {
            void executeSave();
        }, delay);
    }, [delay, executeSave]);
    /** Ejecuta el guardado inmediatamente, cancelando el debounce pendiente. */
    const forceSave = useCallback(async () => {
        if (timerRef.current)
            clearTimeout(timerRef.current);
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
