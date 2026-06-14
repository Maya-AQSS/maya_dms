import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, ConfirmDialog, PageTitle, useConfirm } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { DMS_PERMISSIONS } from '../../../permissions';
import {
  fetchProcess,
  createProcess,
  deleteProcess,
  fetchProcessDeletionPreview,
  updateProcess,
} from '../../../api/processes';
import { ColorBadge } from '../components/ColorBadge';
import { getProcessIcon, PROCESS_ICON_SLUGS } from '../../../components/layout/processIcons';
import { formatError } from '../utils/formatError';
import type { ProcessPayload } from '../../../api/processes';

const HEX_COLOR_RE = /^#[0-9A-Fa-f]{6}$/;

const processSchema = z.object({
  code:              z.string().trim().min(1, 'El código es obligatorio.').max(50),
  name:              z.string().trim().min(1, 'El nombre es obligatorio.').max(255),
  alias:             z.string().trim().min(1, 'El alias es obligatorio.').max(100),
  description:       z.string().trim().nullable().optional(),
  process_parent_id: z.string().nullable().optional(),
  color:             z.string().regex(HEX_COLOR_RE, 'Formato #RRGGBB').nullable().optional(),
  icon:              z.string().nullable().optional(),
});

type ProcessFormValues = z.infer<typeof processSchema>;

const inputClass =
  'w-full border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors';
const labelClass =
  'block text-sm font-medium text-text-secondary dark:text-text-dark-secondary';
const displayClass =
  'mt-1 min-h-[2.75rem] rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary flex items-center';

export function ProcessShowPage() {
  const { processId } = useParams<{ processId: string }>();
  const isCreate = !processId;
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const location = useLocation();
  const { goBack } = useBackNavigation({ fallback: '/admin/processes' });
  const queryClient = useQueryClient();
  const { hasPermission } = useUserProfile();
  const {
    confirmState: deleteConfirmState,
    confirm: confirmDelete,
    closeConfirm: closeDeleteConfirm,
  } = useConfirm();
  const {
    confirmState: impactConfirmState,
    confirm: confirmImpact,
    closeConfirm: closeImpactConfirm,
  } = useConfirm();

  const [editing, setEditing] = useState(isCreate);
  const [actionError, setActionError] = useState<string | null>(null);

  const mayUpdate = hasPermission(DMS_PERMISSIONS.processUpdate);
  const mayDelete = hasPermission(DMS_PERMISSIONS.processDelete);

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['process', processId],
    queryFn: () => fetchProcess(processId!).then((r) => r.data),
    enabled: !isCreate,
  });

  const processesQuery = useProcessesQuery();
  const allProcesses = processesQuery.data?.data ?? [];
  const parentProcess = data?.process_parent_id
    ? allProcesses.find((p) => p.id === data.process_parent_id) ?? null
    : null;
  const parentCandidates = allProcesses.filter(
    (p) => p.process_parent_id === null && p.id !== data?.id,
  );

  const form = useForm<ProcessFormValues>({
    resolver: zodResolver(processSchema),
    defaultValues: {
      code: '', name: '', alias: '', description: null,
      process_parent_id: null, color: null, icon: null,
    },
  });
  const { register, handleSubmit, reset, setValue, control,
    formState: { errors, isSubmitting } } = form;
  const watchCode  = useWatch({ control, name: 'code' });
  const watchName  = useWatch({ control, name: 'name' });
  const watchColor = useWatch({ control, name: 'color' });
  const watchIcon  = useWatch({ control, name: 'icon' });

  useEffect(() => {
    if (data) {
      reset({
        code:              data.code,
        name:              data.name,
        alias:             data.alias,
        description:       data.description ?? null,
        process_parent_id: data.process_parent_id ?? null,
        color:             data.color ?? null,
        icon:              data.icon ?? null,
      });
    }
  }, [data, reset]);

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ['processes'] });
    if (processId) void queryClient.invalidateQueries({ queryKey: ['process', processId] });
  };

  const onSubmit = async (values: ProcessFormValues) => {
    const payload: ProcessPayload = {
      ...values,
      description:       values.description?.trim() || null,
      process_parent_id: values.process_parent_id || null,
      color:             values.color || null,
      icon:              values.icon || null,
    };
    try {
      setActionError(null);
      if (isCreate) {
        const res = await createProcess(payload);
        invalidate();
        navigate(`/admin/processes/${res.data.id}`, { replace: true, state: location.state });
      } else {
        await updateProcess(data!.id, payload);
        invalidate();
        setEditing(false);
      }
    } catch (e) {
      setActionError(formatError(e));
    }
  };

  const handleDelete = async () => {
    if (!data) return;
    try {
      setActionError(null);
      await deleteProcess(data.id);
      void queryClient.invalidateQueries({ queryKey: ['processes'] });
      goBack({ replace: true });
    } catch (e) {
      setActionError(formatError(e));
    }
  };

  const plural = (n: number, singular: string, pluralForm: string) =>
    `${n} ${n === 1 ? singular : pluralForm}`;

  // Paso 1: confirmación genérica. Tras aceptar, se consulta el impacto y se
  // decide si bloquear (subprocesos), pedir 2.ª confirmación (con dependientes)
  // o eliminar directamente (sin dependientes).
  const openDeleteFlow = () => {
    if (!data) return;
    confirmDelete({
      title: 'Eliminar proceso',
      description: `¿Está seguro de que quiere eliminar "${data.name}" (${data.code})?`,
      confirmLabel: 'Sí, continuar',
      variant: 'danger',
      onConfirm: () => evaluateDeletionImpact(),
    });
  };

  const evaluateDeletionImpact = async () => {
    if (!data) return;
    try {
      setActionError(null);
      const { data: preview } = await fetchProcessDeletionPreview(data.id);

      if (preview.subprocess_count > 0) {
        confirmImpact({
          title: 'No se puede eliminar',
          description: `Este proceso tiene ${plural(
            preview.subprocess_count,
            'subproceso',
            'subprocesos',
          )}. Elimina o reubica primero sus subprocesos.`,
          confirmLabel: 'Entendido',
          variant: 'primary',
          onConfirm: () => {},
        });
        return;
      }

      if (preview.templates_count > 0 || preview.documents_count > 0) {
        confirmImpact({
          title: 'Confirmar eliminación',
          description: `Al borrar este proceso borrarás ${plural(
            preview.templates_count,
            'plantilla',
            'plantillas',
          )} y ${plural(
            preview.documents_count,
            'documento',
            'documentos',
          )}. ¿Estás seguro?`,
          confirmLabel: 'Eliminar definitivamente',
          variant: 'danger',
          onConfirm: () => handleDelete(),
        });
        return;
      }

      await handleDelete();
    } catch (e) {
      setActionError(formatError(e));
    }
  };

  const handleCancelEdit = () => {
    if (isCreate) {
      goBack();
    } else {
      if (data) reset({
        code: data.code, name: data.name, alias: data.alias,
        description: data.description ?? null,
        process_parent_id: data.process_parent_id ?? null,
        color: data.color ?? null, icon: data.icon ?? null,
      });
      setEditing(false);
    }
  };

  // ─── Loading / not found ───────────────────────────────────────────────────
  if (!isCreate && isError && !isLoading) {
    return (
      <div className="px-4 py-6 sm:px-6 lg:px-8">
        <PageTitle title={t('processes:detail.title')} onBack={() => goBack()} backLabel={t('navigation.backToProcesses')} />
        <div className="mt-4 rounded-lg border border-dashed border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          {formatError(error)}
        </div>
      </div>
    );
  }

  // ─── Hero reactive display (live preview while editing) ───────────────────
  const heroIcon  = editing ? watchIcon  : data?.icon;
  const heroColor = editing ? watchColor : data?.color;
  const heroName  = editing ? (watchName || (isCreate ? t('processes:form.newTitle') : data?.name)) : data?.name;
  const heroCode  = editing ? (watchCode || data?.code) : data?.code;

  const iconBgStyle    = heroColor ? { backgroundColor: heroColor + '33' } : { backgroundColor: 'rgba(0,0,0,0.06)' };
  const iconColorStyle = heroColor ? { color: heroColor } : undefined;

  return (
    <div className="px-4 py-6 sm:px-6 lg:px-8">
      <PageTitle
        title={isCreate ? t('processes:form.newTitle') : (data?.name ?? t('processes:detail.title'))}
        subtitle={isCreate ? undefined : (data ? `${data.code} · ${t('processes:detail.subtitleSuffix')}` : undefined)}
        onBack={() => goBack()}
        backLabel={t('navigation.backToProcesses')}
        actions={
          <div className="flex gap-2">
            {editing ? (
              <>
                <Button type="button" variant="outline" size="sm" onClick={handleCancelEdit} disabled={isSubmitting}>
                  {t('actions.cancel')}
                </Button>
                <Button type="submit" form="process-form" variant="primary" size="sm" disabled={isSubmitting}>
                  {isSubmitting ? t('saving') : isCreate ? t('processes:form.createProcess') : t('processes:form.saveChanges')}
                </Button>
              </>
            ) : (
              <>
                {mayUpdate && (
                  <Button type="button" variant="outline" size="sm" onClick={() => setEditing(true)}>
                    {t('actions.edit')}
                  </Button>
                )}
                {mayDelete && data && (
                  <Button
                    type="button"
                    variant="danger"
                    size="sm"
                    onClick={openDeleteFlow}
                  >
                    {t('actions.delete')}
                  </Button>
                )}
              </>
            )}
          </div>
        }
      />

      {isError && !isCreate && (
        <Alert tone="danger" className="mt-4">{formatError(error)}</Alert>
      )}
      {actionError && (
        <Alert tone="danger" className="mt-4">{actionError}</Alert>
      )}

      {!isCreate && isLoading && (
        <div className="mt-4 rounded-lg border border-ui-border bg-ui-card p-6 text-center text-sm text-text-muted dark:border-ui-dark-border dark:bg-ui-dark-card dark:text-text-dark-muted">
          {t('processes:detail.loading')}
        </div>
      )}

      {(isCreate || data) && (
        <>
          {/* Hero */}
          <div className="mt-4 flex items-center gap-4 rounded-lg border border-ui-border bg-ui-card px-5 py-4 shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-card">
            <span
              className="shrink-0 w-14 h-14 flex items-center justify-center rounded-full [&>span>svg]:w-7 [&>span>svg]:h-7"
              style={iconBgStyle}
            >
              <span className="text-text-secondary dark:text-text-dark-secondary" style={iconColorStyle}>
                {getProcessIcon(heroIcon)}
              </span>
            </span>
            <div className="min-w-0">
              {heroCode && (
                <p className="font-mono text-xs uppercase tracking-widest text-text-muted dark:text-text-dark-muted">
                  {heroCode}
                </p>
              )}
              <h2 className="text-lg font-semibold text-text-primary dark:text-text-dark-primary truncate">
                {heroName ?? <span className="italic text-text-muted">{t('processes:form.namePlaceholder')}</span>}
              </h2>
            </div>
            {heroColor && !editing && (
              <div className="ml-auto shrink-0">
                <ColorBadge color={heroColor} />
              </div>
            )}
          </div>

          {/* Campos */}
          <form
            id="process-form"
            onSubmit={(e) => void handleSubmit(onSubmit)(e)}
            className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2"
          >
            {/* Código */}
            <div>
              <label className={labelClass}>{t('processes:form.code')} {editing && <span className="text-danger">*</span>}</label>
              {editing ? (
                <>
                  <input {...register('code')} placeholder={t('processes:form.codePlaceholder')} className={`mt-1 ${inputClass}`} />
                  {errors.code && <p className="mt-1 text-xs text-danger">{errors.code.message}</p>}
                </>
              ) : (
                <div className={displayClass}>
                  <span className="font-mono">{data!.code}</span>
                </div>
              )}
            </div>

            {/* Nombre */}
            <div>
              <label className={labelClass}>{t('processes:form.name')} {editing && <span className="text-danger">*</span>}</label>
              {editing ? (
                <>
                  <input {...register('name')} placeholder={t('processes:form.namePlaceholderShort')} className={`mt-1 ${inputClass}`} />
                  {errors.name && <p className="mt-1 text-xs text-danger">{errors.name.message}</p>}
                </>
              ) : (
                <div className={displayClass}>{data!.name}</div>
              )}
            </div>

            {/* Alias */}
            <div>
              <label className={labelClass}>{t('processes:form.alias')} {editing && <span className="text-danger">*</span>}</label>
              {editing ? (
                <>
                  <input {...register('alias')} placeholder={t('processes:form.aliasPlaceholder')} className={`mt-1 ${inputClass}`} />
                  {errors.alias && <p className="mt-1 text-xs text-danger">{errors.alias.message}</p>}
                </>
              ) : (
                <div className={displayClass}>{data!.alias}</div>
              )}
            </div>

            {/* Proceso padre */}
            <div>
              <label className={labelClass}>{t('processes:form.parent')}</label>
              {editing ? (
                <select {...register('process_parent_id')} className={`mt-1 ${inputClass}`}>
                  <option value="">{t('processes:form.noParent')}</option>
                  {parentCandidates.map((p) => (
                    <option key={p.id} value={p.id}>{p.code} — {p.name}</option>
                  ))}
                </select>
              ) : (
                <div className={displayClass}>
                  {parentProcess ? (
                    <a
                      href={`/admin/processes/${parentProcess.id}`}
                      onClick={(e) => { e.preventDefault(); navigate(`/admin/processes/${parentProcess.id}`); }}
                      className="flex items-center gap-2 text-odoo-purple dark:text-odoo-dark-purple hover:underline"
                    >
                      <span className="font-mono text-xs">{parentProcess.code}</span>
                      <span>{parentProcess.name}</span>
                    </a>
                  ) : (
                    <span className="italic text-text-muted dark:text-text-dark-muted">{t('processes:form.rootNoParent')}</span>
                  )}
                </div>
              )}
            </div>

            {/* Color */}
            <div>
              <label className={labelClass}>{t('processes:form.color')}</label>
              {editing ? (
                <div className="mt-1 flex items-center gap-3 min-h-[2.375rem]">
                  {watchColor ? (
                    <>
                      <input
                        type="color"
                        value={watchColor}
                        onChange={(e) => setValue('color', e.target.value, { shouldValidate: true })}
                        className="h-9 w-14 cursor-pointer rounded border border-ui-border dark:border-ui-dark-border bg-transparent p-0.5"
                      />
                      <span className="font-mono text-xs text-text-secondary dark:text-text-dark-secondary">{watchColor}</span>
                      <button
                        type="button"
                        onClick={() => setValue('color', null)}
                        className="text-xs text-text-muted underline hover:text-danger transition-colors"
                      >
                        {t('processes:form.remove')}
                      </button>
                    </>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setValue('color', '#6366f1')}
                      className="text-xs text-text-muted underline hover:text-text-primary dark:hover:text-text-dark-primary transition-colors"
                    >
                      {t('processes:form.assignColor')}
                    </button>
                  )}
                  {errors.color && <p className="text-xs text-danger">{errors.color.message}</p>}
                </div>
              ) : (
                <div className={displayClass}>
                  {data!.color ? (
                    <div className="flex items-center gap-2.5">
                      <ColorBadge color={data!.color} />
                      <span className="font-mono text-xs text-text-muted dark:text-text-dark-muted">{data!.color}</span>
                    </div>
                  ) : (
                    <span className="italic text-text-muted dark:text-text-dark-muted">{t('processes:form.noColor')}</span>
                  )}
                </div>
              )}
            </div>

            {/* Icono */}
            <div className={editing ? 'md:col-span-2' : undefined}>
              <div className="flex items-center justify-between mb-1">
                <label className={labelClass}>{t('processes:form.icon')}</label>
                {editing && watchIcon && (
                  <button
                    type="button"
                    onClick={() => setValue('icon', null)}
                    className="text-xs text-text-muted underline hover:text-danger transition-colors"
                  >
                    {t('processes:form.removeIcon')}
                  </button>
                )}
              </div>
              {editing ? (
                <div className="grid grid-cols-12 gap-1 p-2 rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-body dark:bg-ui-dark-bg max-h-52 overflow-y-auto">
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
                          'w-8 h-8 flex items-center justify-center rounded-full transition-all',
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
              ) : (
                <div className={displayClass}>
                  {data!.icon ? (
                    <div className="flex items-center gap-2.5">
                      <span
                        className="w-7 h-7 shrink-0 flex items-center justify-center rounded-full"
                        style={iconBgStyle}
                      >
                        <span style={iconColorStyle} className="text-text-secondary dark:text-text-dark-secondary">
                          {getProcessIcon(data!.icon)}
                        </span>
                      </span>
                      <span className="font-mono text-xs text-text-muted dark:text-text-dark-muted">{data!.icon}</span>
                    </div>
                  ) : (
                    <span className="italic text-text-muted dark:text-text-dark-muted">{t('processes:form.noIcon')}</span>
                  )}
                </div>
              )}
            </div>

            {/* Descripción */}
            <div className="md:col-span-2">
              <label className={labelClass}>{t('fields.description')}</label>
              {editing ? (
                <textarea
                  {...register('description')}
                  rows={4}
                  placeholder={t('processes:form.descriptionPlaceholder')}
                  className={`mt-1 resize-none ${inputClass}`}
                />
              ) : (
                data!.description ? (
                  <div className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm text-text-primary whitespace-pre-wrap break-words shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-primary">
                    {data!.description}
                  </div>
                ) : (
                  <div className="mt-1 rounded-lg border border-ui-border bg-ui-body px-3 py-2.5 text-sm italic text-text-muted shadow-inner dark:border-ui-dark-border dark:bg-ui-dark-bg dark:text-text-dark-muted">
                    {t('fields.noDescription')}
                  </div>
                )
              )}
            </div>
          </form>
        </>
      )}

      <ConfirmDialog {...deleteConfirmState} onCancel={closeDeleteConfirm} />
      <ConfirmDialog {...impactConfirmState} onCancel={closeImpactConfirm} />
    </div>
  );
}
