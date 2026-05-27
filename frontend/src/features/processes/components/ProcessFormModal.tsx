import { useEffect, useRef, type MutableRefObject } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { getProcessIcon, PROCESS_ICON_SLUGS } from '../../../components/layout/processIcons';
import type { Process } from '../../../types/processes';
import type { ProcessPayload } from '../../../api/processes';

const HEX_COLOR_RE = /^#[0-9A-Fa-f]{6}$/;

const processSchema = z.object({
  code: z.string().trim().min(1, 'El código es obligatorio.').max(50),
  name: z.string().trim().min(1, 'El nombre es obligatorio.').max(255),
  alias: z.string().trim().min(1, 'El alias es obligatorio.').max(100),
  description: z.string().trim().nullable().optional(),
  process_parent_id: z.string().nullable().optional(),
  color: z
    .string()
    .regex(HEX_COLOR_RE, 'Color inválido (formato #RRGGBB).')
    .nullable()
    .optional(),
  icon: z.string().nullable().optional(),
});

type ProcessFormValues = z.infer<typeof processSchema>;

interface ProcessFormModalProps {
  open: boolean;
  onClose: () => void;
  onSave: (payload: ProcessPayload) => Promise<void>;
  initial?: Process | null;
  processes: Process[];
}

function defaultValues(initial?: Process | null): ProcessFormValues {
  return {
    code: initial?.code ?? '',
    name: initial?.name ?? '',
    alias: initial?.alias ?? '',
    description: initial?.description ?? null,
    process_parent_id: initial?.process_parent_id ?? null,
    color: initial?.color ?? null,
    icon: initial?.icon ?? null,
  };
}

export function ProcessFormModal({
  open,
  onClose,
  onSave,
  initial,
  processes,
}: ProcessFormModalProps) {
  const firstInputRef = useRef<HTMLInputElement | null>(null) as MutableRefObject<HTMLInputElement | null>;

  const form = useForm<ProcessFormValues>({
    resolver: zodResolver(processSchema),
    defaultValues: defaultValues(initial),
  });
  const { register, handleSubmit, reset, formState: { errors, isSubmitting }, setError, setValue, control } = form;
  const watchColor = useWatch({ control, name: 'color' });
  const watchIcon = useWatch({ control, name: 'icon' });

  useEffect(() => {
    reset(defaultValues(initial));
  }, [initial, open, reset]);

  useEffect(() => {
    if (!open) return;
    const timer = setTimeout(() => firstInputRef.current?.focus(), 50);
    return () => clearTimeout(timer);
  }, [open]);

  if (!open) return null;

  const onSubmit = async (values: ProcessFormValues) => {
    try {
      await onSave({
        ...values,
        description: values.description?.trim() || null,
        process_parent_id: values.process_parent_id || null,
        color: values.color || null,
        icon: values.icon || null,
      });
      onClose();
    } catch (err) {
      setError('root', {
        message: err instanceof Error ? err.message : 'Error al guardar el proceso.',
      });
    }
  };

  const parentCandidates = processes.filter(
    (p) => p.process_parent_id === null && p.id !== initial?.id,
  );

  const isEditing = !!initial;

  const inputClass =
    'w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors';
  const labelClass =
    'block text-sm font-medium text-text-primary dark:text-text-dark-primary mb-1';

  const { ref: codeRef, ...codeRest } = register('code');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />

      <div className="relative bg-ui-card dark:bg-ui-dark-card w-full max-w-lg rounded-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
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

        <form onSubmit={(e) => void handleSubmit(onSubmit)(e)}>
          <div className="px-5 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
            {errors.root && (
              <div className="rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
                {errors.root.message}
              </div>
            )}

            <div>
              <label className={labelClass}>
                Código <span className="text-danger">*</span>
              </label>
              <input
                {...codeRest}
                ref={(el) => {
                  codeRef(el);
                  firstInputRef.current = el;
                }}
                type="text"
                placeholder="Ej. PE01"
                className={inputClass}
              />
              {errors.code && <p className="mt-1 text-xs text-danger">{errors.code.message}</p>}
            </div>

            <div>
              <label className={labelClass}>
                Nombre <span className="text-danger">*</span>
              </label>
              <input
                {...register('name')}
                type="text"
                placeholder="Nombre completo del proceso"
                className={inputClass}
              />
              {errors.name && <p className="mt-1 text-xs text-danger">{errors.name.message}</p>}
            </div>

            <div>
              <label className={labelClass}>
                Alias <span className="text-danger">*</span>
              </label>
              <input
                {...register('alias')}
                type="text"
                placeholder="Etiqueta corta"
                className={inputClass}
              />
              {errors.alias && <p className="mt-1 text-xs text-danger">{errors.alias.message}</p>}
            </div>

            <div>
              <label className={labelClass}>Descripción</label>
              <textarea
                {...register('description')}
                rows={3}
                placeholder="Descripción opcional"
                className={`${inputClass} resize-none`}
              />
            </div>

            <div>
              <label className={labelClass}>Proceso padre</label>
              <select {...register('process_parent_id')} className={inputClass}>
                <option value="">Sin padre (proceso raíz)</option>
                {parentCandidates.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code} — {p.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className={labelClass}>Color</label>
              <div className="flex items-center gap-3">
                {watchColor ? (
                  <>
                    <input
                      type="color"
                      value={watchColor}
                      onChange={(e) => setValue('color', e.target.value, { shouldValidate: true })}
                      className="h-9 w-14 cursor-pointer rounded border border-ui-border dark:border-ui-dark-border bg-transparent p-0.5"
                    />
                    <span className="font-mono text-xs text-text-secondary dark:text-text-dark-secondary">
                      {watchColor}
                    </span>
                    <button
                      type="button"
                      onClick={() => setValue('color', null, { shouldValidate: true })}
                      className="text-xs text-text-muted dark:text-text-dark-muted underline hover:text-danger transition-colors"
                    >
                      Quitar color
                    </button>
                  </>
                ) : (
                  <button
                    type="button"
                    onClick={() => setValue('color', '#6366f1', { shouldValidate: true })}
                    className="text-xs text-text-muted dark:text-text-dark-muted underline hover:text-text-primary dark:hover:text-text-dark-primary transition-colors"
                  >
                    + Asignar color
                  </button>
                )}
              </div>
              {errors.color && <p className="mt-1 text-xs text-danger">{errors.color.message}</p>}
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label className={labelClass}>Icono</label>
                {watchIcon && (
                  <button
                    type="button"
                    onClick={() => setValue('icon', null)}
                    className="text-xs text-text-muted dark:text-text-dark-muted underline hover:text-danger transition-colors"
                  >
                    Quitar icono
                  </button>
                )}
              </div>
              <div className="grid grid-cols-10 gap-1 p-2 rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-body dark:bg-ui-dark-bg max-h-40 overflow-y-auto">
                {PROCESS_ICON_SLUGS.map((slug) => {
                  const isSelected = watchIcon === slug;
                  const bgStyle = watchColor
                    ? { backgroundColor: watchColor + '33' }
                    : { backgroundColor: isSelected ? 'rgba(99,102,241,0.15)' : 'rgba(0,0,0,0.06)' };
                  const iconStyle = watchColor ? { color: watchColor } : undefined;
                  return (
                    <button
                      key={slug}
                      type="button"
                      title={slug}
                      onClick={() => setValue('icon', isSelected ? null : slug)}
                      className={[
                        'w-7 h-7 flex items-center justify-center rounded-full transition-all',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-1'
                          : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border',
                      ].join(' ')}
                      style={bgStyle}
                    >
                      <span
                        className={iconStyle ? '' : 'text-text-secondary dark:text-text-dark-secondary'}
                        style={iconStyle}
                      >
                        {getProcessIcon(slug)}
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-2 px-5 py-3 border-t border-ui-border dark:border-ui-dark-border">
            <Button type="button" variant="outline" size="sm" onClick={onClose} disabled={isSubmitting}>
              Cancelar
            </Button>
            <Button type="submit" variant="primary" size="sm" disabled={isSubmitting}>
              {isSubmitting ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear proceso'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
