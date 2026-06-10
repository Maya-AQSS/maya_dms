import { useTranslation } from 'react-i18next';
import type { TiptapDiffLine } from '../lib/tiptapLineDiff';

/** Render compartido de líneas de diff (rojo = eliminado, verde = añadido). */
export function DiffLines({ lines }: { lines: TiptapDiffLine[] }) {
  const { t } = useTranslation('documents');
  if (lines.length === 0) {
    return (
      <p className="px-2 py-1 text-text-muted italic text-2xs">
        {t('diff.noChangesInSubmission')}
      </p>
    );
  }
  return (
    <>
      {lines.map((line, li) => (
        <div
          key={li}
          className={`px-2 py-0.5 whitespace-pre-wrap break-all leading-relaxed ${
            line.type === 'removed'
              ? 'bg-danger/10 text-danger-dark dark:bg-danger/15 dark:text-danger'
              : 'bg-success/10 text-success-dark dark:bg-success/15 dark:text-success'
          }`}
        >
          <span className="mr-2 select-none font-bold opacity-70">
            {line.type === 'removed' ? '−' : '+'}
          </span>
          {line.text}
        </div>
      ))}
    </>
  );
}
