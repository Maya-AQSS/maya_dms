import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { DiffLines } from '../features/documents/components/DiffLines';
import {
  loadVersionComparable,
  type VersionEntityType,
} from '../features/documents/lib/loadVersionComparable';
import {
  compareVersionBlocks,
  type VersionBlockChange,
} from '../features/documents/lib/versionBlockCompare';

export type CompareVersionOption = {
  id: string;
  versionNumber: number;
};

type Props = {
  entityType: VersionEntityType;
  entityId: string;
  /** Versiones publicadas, ordenadas descendente por número de versión. */
  versions: CompareVersionOption[];
};

const STATUS_STYLES: Record<VersionBlockChange['status'], string> = {
  added: 'bg-success/15 text-success-dark dark:text-success border border-success/30',
  removed: 'bg-danger/15 text-danger-dark dark:text-danger border border-danger/30',
  modified: 'bg-primary/10 text-primary-dark dark:text-primary-light border border-primary/25',
};

function useComparableVersion(
  entityType: VersionEntityType,
  entityId: string,
  versionId: string | null,
) {
  return useQuery({
    queryKey: ['version-compare', entityType, entityId, versionId],
    queryFn: () => loadVersionComparable(entityType, entityId, versionId as string),
    enabled: !!versionId,
    staleTime: 60_000,
  });
}

export function VersionComparePanel({ entityType, entityId, versions }: Props) {
  const { t } = useTranslation('common');

  const [aId, setAId] = useState<string | null>(() => versions[1]?.id ?? null);
  const [bId, setBId] = useState<string | null>(() => versions[0]?.id ?? null);

  const aQuery = useComparableVersion(entityType, entityId, aId);
  const bQuery = useComparableVersion(entityType, entityId, bId);

  const distinct = !!aId && !!bId && aId !== bId;
  const loading = distinct && (aQuery.isLoading || bQuery.isLoading);
  const failed = distinct && (aQuery.isError || bQuery.isError);
  const ready = distinct && aQuery.data && bQuery.data;

  const result = useMemo(() => {
    if (!aQuery.data || !bQuery.data) return null;
    const older =
      aQuery.data.versionNumber <= bQuery.data.versionNumber ? aQuery.data : bQuery.data;
    const newer = older === aQuery.data ? bQuery.data : aQuery.data;
    return {
      from: older.versionNumber,
      to: newer.versionNumber,
      changes: compareVersionBlocks(older.blocks, newer.blocks, {
        emptyBlockLabel: t('versionCompare.emptyBlock'),
      }),
    };
  }, [aQuery.data, bQuery.data, t]);

  if (versions.length < 2) {
    return (
      <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-8 leading-relaxed">
        {t('versionCompare.needTwo')}
      </p>
    );
  }

  const statusLabel = (status: VersionBlockChange['status']): string =>
    t(`versionCompare.status.${status}`);

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <label className="block">
          <span className="block text-2xs font-black uppercase tracking-widest text-text-muted dark:text-text-dark-muted mb-1">
            {t('versionCompare.firstLabel')}
          </span>
          <select
            value={aId ?? ''}
            onChange={(e) => setAId(e.target.value || null)}
            className="w-full rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-2.5 py-1.5 text-sm text-text-primary dark:text-text-dark-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
          >
            {versions.map((v) => (
              <option key={v.id} value={v.id}>
                v{v.versionNumber}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="block text-2xs font-black uppercase tracking-widest text-text-muted dark:text-text-dark-muted mb-1">
            {t('versionCompare.secondLabel')}
          </span>
          <select
            value={bId ?? ''}
            onChange={(e) => setBId(e.target.value || null)}
            className="w-full rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-2.5 py-1.5 text-sm text-text-primary dark:text-text-dark-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
          >
            {versions.map((v) => (
              <option key={v.id} value={v.id}>
                v{v.versionNumber}
              </option>
            ))}
          </select>
        </label>
      </div>

      {!distinct && (
        <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-6 px-2 leading-relaxed">
          {t('versionCompare.samePick')}
        </p>
      )}
      {loading && (
        <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-6">
          {t('versionCompare.loading')}
        </p>
      )}
      {failed && (
        <p className="text-sm text-warning-dark dark:text-warning-light text-center py-6 px-2">
          {t('versionCompare.loadFailed')}
        </p>
      )}

      {ready && result && (
        <div className="space-y-3">
          <div className="flex items-center justify-between gap-2">
            <p className="text-2xs font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary">
              {t('versionCompare.resultHeading', { from: result.from, to: result.to })}
            </p>
            <span className="text-2xs font-semibold text-text-muted dark:text-text-dark-muted">
              {t('versionCompare.changeCount', { count: result.changes.length })}
            </span>
          </div>

          {result.changes.length === 0 ? (
            <p className="py-8 text-center text-xs text-text-muted dark:text-text-dark-muted italic">
              {t('versionCompare.noChanges')}
            </p>
          ) : (
            <ul className="space-y-2.5" role="list">
              {result.changes.map((change) => (
                <li
                  key={change.key}
                  className="rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card/80 overflow-hidden"
                >
                  <div className="flex items-center justify-between gap-2 px-3 py-2 border-b border-ui-border/60 dark:border-ui-dark-border/60">
                    <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">
                      {t('versionCompare.blockLabel', {
                        n: change.blockNumber,
                        title: change.title ?? t('versionCompare.untitled'),
                      })}
                    </p>
                    <span
                      className={`shrink-0 text-2xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full ${STATUS_STYLES[change.status]}`}
                    >
                      {statusLabel(change.status)}
                    </span>
                  </div>
                  <div className="text-2xs font-mono">
                    <DiffLines lines={change.lines} />
                  </div>
                </li>
              ))}
            </ul>
          )}

          <div className="flex items-center gap-3 pt-1 text-2xs text-text-muted dark:text-text-dark-muted">
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded-sm bg-danger/25 border border-danger/40" />
              {t('versionCompare.status.removed')}
            </span>
            <span className="flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 rounded-sm bg-success/25 border border-success/40" />
              {t('versionCompare.status.added')}
            </span>
          </div>
        </div>
      )}
    </div>
  );
}
