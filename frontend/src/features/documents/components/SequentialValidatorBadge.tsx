type ReviewerSlot = {
  stage: number;
  status?: string | null;
  name?: string | null;
};

type Props = {
  reviewers: ReviewerSlot[];
  reviewMode: string | null | undefined;
};

/**
 * Small chip shown when review_mode='sequential', displaying the active stage validator.
 * Renders nothing for parallel mode or when no pending reviews remain.
 */
export function SequentialValidatorBadge({ reviewers, reviewMode }: Props) {
  if (reviewMode !== 'sequential') return null;

  const pending = reviewers.filter((r) => (r.status ?? 'pending') === 'pending');
  if (pending.length === 0) return null;

  const minStage = Math.min(...pending.map((r) => r.stage));
  const active = pending.filter((r) => r.stage === minStage);
  const names = active.map((r) => r.name ?? 'Validador').join(', ');

  return (
    <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-odoo-teal/40 bg-odoo-teal/5 dark:bg-odoo-teal/10 shrink-0">
      <svg className="w-3 h-3 text-odoo-teal dark:text-odoo-dark-teal shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      <span className="text-2xs font-black uppercase tracking-widest text-odoo-teal dark:text-odoo-dark-teal whitespace-nowrap">
        {names}
        <span className="opacity-50 ml-1">· etapa {minStage}</span>
      </span>
    </div>
  );
}
