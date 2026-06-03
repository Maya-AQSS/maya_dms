import { useEffect, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfirmDialog } from '@ceedcv-maya/shared-ui-react';

export const VERSION_CHANGELOG_MAX_LENGTH = 5000;

type Props = {
  open: boolean;
  title: string;
  intro?: ReactNode;
  initialValue?: string | null;
  confirmLabel: string;
  loading?: boolean;
  error?: string | null;
  onCancel: () => void;
  onConfirm: (changelog: string) => void | boolean | Promise<void | boolean>;
};

export function SubmissionChangelogReadonly({ text }: { text: string }) {
  const { t } = useTranslation('common');

  return (
    <section
      className="mb-6 rounded-xl border border-ui-border dark:border-ui-dark-border bg-ui-body/40 dark:bg-ui-dark-bg/40 px-4 py-3"
      aria-label={t('versionChangelog.readOnlyTitle', { defaultValue: 'Cambios enviados por el autor' })}
    >
      <h2 className="text-2xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary mb-2">
        {t('versionChangelog.readOnlyTitle', { defaultValue: 'Cambios enviados por el autor' })}
      </h2>
      <p className="text-sm text-text-primary dark:text-text-dark-primary leading-relaxed whitespace-pre-wrap">
        {text}
      </p>
    </section>
  );
}

export function VersionChangelogModal({
  open,
  title,
  intro,
  initialValue = '',
  confirmLabel,
  loading = false,
  error = null,
  onCancel,
  onConfirm,
}: Props) {
  const { t } = useTranslation('common');
  const [value, setValue] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setValue((initialValue ?? '').trim() ? (initialValue ?? '').trim() : '');
      setLocalError(null);
    }
  }, [open, initialValue]);

  const handleConfirm = () => {
    const trimmed = value.trim();
    if (trimmed === '') {
      setLocalError(t('versionChangelog.required', { defaultValue: 'El changelog es obligatorio.' }));
      return false;
    }
    if (trimmed.length > VERSION_CHANGELOG_MAX_LENGTH) {
      setLocalError(
        t('versionChangelog.maxLength', {
          max: VERSION_CHANGELOG_MAX_LENGTH,
          defaultValue: `El changelog no puede superar ${VERSION_CHANGELOG_MAX_LENGTH} caracteres.`,
        }),
      );
      return false;
    }
    setLocalError(null);
    return onConfirm(trimmed);
  };

  const displayError = localError ?? error;

  return (
    <ConfirmDialog
      open={open}
      title={title}
      icon="✉️"
      description={
        <div className="space-y-3">
          {intro}
          <p className="text-xs text-text-muted dark:text-text-dark-muted">
            {t('versionChangelog.hint', {
              defaultValue: 'Describe qué has modificado. Los validadores verán este texto.',
            })}
          </p>
          <label className="block">
            <span className="text-2xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
              {t('versionChangelog.label', { defaultValue: 'Cambios en esta versión' })}
            </span>
            <textarea
              value={value}
              onChange={(e) => {
                setValue(e.target.value);
                if (localError) setLocalError(null);
              }}
              placeholder={t('versionChangelog.placeholder', { defaultValue: 'Descripción del cambio…' })}
              maxLength={VERSION_CHANGELOG_MAX_LENGTH}
              className="mt-1.5 w-full min-h-28 rounded-md border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm text-text-primary dark:text-text-dark-primary"
            />
          </label>
        </div>
      }
      confirmLabel={confirmLabel}
      loading={loading}
      error={displayError}
      onCancel={onCancel}
      onConfirm={handleConfirm}
    />
  );
}
