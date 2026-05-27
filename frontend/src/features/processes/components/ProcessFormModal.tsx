import { useEffect, useRef, useState } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import type { Process } from '../../../types/processes';
import type { ProcessPayload } from '../hooks/useProcessesCrud';

interface ProcessFormModalProps {
  open: boolean;
  onClose: () => void;
  onSave: (payload: ProcessPayload) => Promise<void>;
  initial?: Process | null;
  /** Lista de procesos disponibles para el select de padre. */
  processes: Process[];
}

const EMPTY_FORM: ProcessPayload = {
  code: '',
  name: '',
  alias: '',
  description: null,
  process_parent_id: null,
};

function formFromProcess(process: Process): ProcessPayload {
  return {
    code: process.code,
    name: process.name,
    alias: process.alias,
    description: process.description ?? null,
    process_parent_id: process.process_parent_id ?? null,
  };
}

export function ProcessFormModal({
  open,
  onClose,
  onSave,
  initial,
  processes,
}: ProcessFormModalProps) {
  const [form, setForm] = useState<ProcessPayload>(
    initial ? formFromProcess(initial) : EMPTY_FORM,
  );
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const firstInputRef = useRef<HTMLInputElement>(null);

  // Sync form when initial changes (open different item)
  useEffect(() => {
    setForm(initial ? formFromProcess(initial) : EMPTY_FORM);
    setFormError(null);
  }, [initial, open]);

  // Focus first input on open
  useEffect(() => {
    if (open) {
      setTimeout(() => firstInputRef.current?.focus(), 50);
    }
  }, [open]);

  if (!open) return null;

  const set = (patch: Partial<ProcessPayload>) => setForm((f) => ({ ...f, ...patch }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (saving) return;

    try {
      setSaving(true);
      setFormError(null);
      await onSave({
        ...form,
        description: form.description?.trim() || null,
        process_parent_id: form.process_parent_id || null,
      });
      onClose();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Error al guardar el proceso.');
    } finally {
      setSaving(false);
    }
  };

  // Only top-level processes can be parents (exclude the process being edited)
  const parentCandidates = processes.filter(
    (p) => p.process_parent_id === null && p.id !== initial?.id,
  );

  const isEditing = !!initial;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />

      <div className="relative bg-ui-card dark:bg-ui-dark-card w-full max-w-lg rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border">
          <h2 className="text-sm font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary">
            {isEditing ? 'Editar proceso' : 'Nuevo proceso'}
          </h2>
          <button
            type="button"
            className="text-text-muted hover:text-text-primary dark:text-text-dark-muted dark:hover:text-text-dark-primary transition-colors p-1 rounded"
            onClick={onClose}
            aria-label="Cerrar"
          >
            ✕
          </button>
        </div>

        {/* Form */}
        <form onSubmit={(e) => void handleSubmit(e)}>
          <div className="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
            {formError && (
              <div className="rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
                {formError}
              </div>
            )}

            {/* Código */}
            <div>
              <label
                htmlFor="process-code"
                className="block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1"
              >
                Código <span className="text-danger">*</span>
              </label>
              <input
                ref={firstInputRef}
                id="process-code"
                type="text"
                value={form.code}
                onChange={(e) => set({ code: e.target.value })}
                required
                maxLength={100}
                placeholder="Ej. PE01"
                className="w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors"
              />
            </div>

            {/* Nombre */}
            <div>
              <label
                htmlFor="process-name"
                className="block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1"
              >
                Nombre <span className="text-danger">*</span>
              </label>
              <input
                id="process-name"
                type="text"
                value={form.name}
                onChange={(e) => set({ name: e.target.value })}
                required
                placeholder="Nombre completo del proceso"
                className="w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors"
              />
            </div>

            {/* Alias */}
            <div>
              <label
                htmlFor="process-alias"
                className="block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1"
              >
                Alias <span className="text-danger">*</span>
              </label>
              <input
                id="process-alias"
                type="text"
                value={form.alias}
                onChange={(e) => set({ alias: e.target.value })}
                required
                maxLength={255}
                placeholder="Etiqueta corta (máx. 255 caracteres)"
                className="w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors"
              />
            </div>

            {/* Descripción */}
            <div>
              <label
                htmlFor="process-description"
                className="block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1"
              >
                Descripción
              </label>
              <textarea
                id="process-description"
                value={form.description ?? ''}
                onChange={(e) => set({ description: e.target.value || null })}
                rows={3}
                placeholder="Descripción opcional"
                className="w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors resize-none"
              />
            </div>

            {/* Proceso padre */}
            <div>
              <label
                htmlFor="process-parent"
                className="block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1"
              >
                Proceso padre
              </label>
              <select
                id="process-parent"
                value={form.process_parent_id ?? ''}
                onChange={(e) => set({ process_parent_id: e.target.value || null })}
                className="w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors"
              >
                <option value="">Sin padre (proceso raíz)</option>
                {parentCandidates.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code} — {p.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 px-5 py-3 border-t border-ui-border dark:border-ui-dark-border">
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={saving}>
              Cancelar
            </Button>
            <Button type="submit" variant="primary" size="sm" disabled={saving}>
              {saving ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear proceso'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
