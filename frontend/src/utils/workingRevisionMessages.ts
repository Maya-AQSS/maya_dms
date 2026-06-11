export type WorkingRevisionBlockInfo = {
  editorName?: string | null;
  startedAt?: string | null;
};

type TranslateFn = (key: string, options?: Record<string, unknown>) => string;

function appendElapsedDraftMessage(message: string, startedAt: string, t: TranslateFn): string {
  const started = new Date(startedAt);
  if (Number.isNaN(started.getTime())) return message;

  const diffMs = Date.now() - started.getTime();
  if (diffMs < 60_000) return message;

  const minutes = Math.floor(diffMs / 60_000);
  if (minutes < 60) {
    return `${message} ${t('preview.draftAlreadyExistsDuration_minutes', { count: minutes })}`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${message} ${t('preview.draftAlreadyExistsDuration_hours', { count: hours })}`;
  }

  const days = Math.floor(hours / 24);
  return `${message} ${t('preview.draftAlreadyExistsDuration_days', { count: days })}`;
}

export function buildNewVersionBlockedDescription(
  info: WorkingRevisionBlockInfo,
  t: TranslateFn,
): string {
  const author = info.editorName?.trim() || t('preview.draftAlreadyExistsUnknownAuthor');

  let message = t('preview.draftAlreadyExistsDescription', { author });

  if (info.startedAt) {
    message = appendElapsedDraftMessage(message, info.startedAt, t);
  }

  return message;
}
