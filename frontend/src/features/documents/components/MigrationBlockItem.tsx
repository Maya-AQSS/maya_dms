import { useTranslation } from 'react-i18next';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import type {
  DocumentMigrationBlock,
  MigrationChoice,
} from '../schemas/migrationPayload';

type Props = {
  block: DocumentMigrationBlock;
  choice: MigrationChoice | undefined;
  onChoose: (templateBlockId: string, choice: MigrationChoice | undefined) => void;
};

/**
 * Una fila del paso de migración: contenido nuevo (verde) vs contenido antiguo
 * del usuario (rojo), reutilizando el patrón visual de DocumentDiffModal. Permite
 * elegir Reemplazar / Anexar salvo en bloques bloqueados, nuevos o eliminados.
 */
export function MigrationBlockItem({ block, choice, onChoose }: Props) {
  const { t } = useTranslation('documents');

  const id = block.template_block_id;
  const actionable = !block.locked && !block.new_block && !block.removed_block && block.old_content != null;

  const badge = block.removed_block
    ? { label: t('migration.removedBadge'), cls: 'text-danger border-danger/30 bg-danger/5' }
    : block.new_block
      ? { label: t('migration.newBadge'), cls: 'text-odoo-purple border-odoo-purple/30 bg-odoo-purple/5' }
      : block.locked
        ? { label: t('migration.lockedBadge'), cls: 'text-text-muted border-ui-border bg-ui-body' }
        : block.changed_block_state
          ? { label: t('migration.stateChangedBadge'), cls: 'text-warning-dark border-warning/30 bg-warning/5' }
          : null;

  return (
    <div className="rounded-xl border border-ui-border dark:border-ui-dark-border overflow-hidden">
      <div className="flex items-center gap-2 px-4 py-2.5 border-b border-ui-border dark:border-ui-dark-border bg-ui-body dark:bg-ui-dark-bg">
        <span className="text-sm font-semibold text-text-secondary dark:text-text-dark-secondary truncate">
          {block.title ?? t('migration.untitled')}
        </span>
        {badge && (
          <span className={`ml-auto shrink-0 text-[10px] font-black uppercase tracking-widest border rounded-full px-2 py-0.5 ${badge.cls}`}>
            {badge.label}
          </span>
        )}
      </div>

      <div className="p-4 space-y-4">
        {!block.new_block && block.old_content != null && (
          <div>
            <p className="text-xs font-black uppercase tracking-widest text-danger mb-2">
              {t('migration.oldContent')}
            </p>
            <div className="bg-danger/5 border border-danger/20 dark:bg-danger/10 dark:border-danger/30 rounded-lg p-4 min-h-[48px]">
              <BlockContentHtml content={normalizeBlockContentForEditor(block.old_content)} />
            </div>
          </div>
        )}

        {!block.removed_block && (
          <div>
            <p className="text-xs font-black uppercase tracking-widest text-success-dark dark:text-success mb-2">
              {t('migration.newContent')}
            </p>
            <div className="bg-success/5 border border-success/20 dark:bg-success/10 dark:border-success/30 rounded-lg p-4 min-h-[48px]">
              <BlockContentHtml content={normalizeBlockContentForEditor(block.new_default_content)} />
            </div>
          </div>
        )}

        {block.removed_block && (
          <p className="text-xs text-text-muted dark:text-text-dark-muted">
            {t('migration.removedHelp')}
          </p>
        )}

        {block.locked && (
          <p className="text-xs text-text-muted dark:text-text-dark-muted">🔒 {t('migration.lockedHelp')}</p>
        )}

        {actionable && (
          <div className="flex items-center gap-2 pt-1">
            <span className="text-xs text-text-muted dark:text-text-dark-muted mr-1">{t('migration.addAction')}</span>
            <Button
              type="button"
              size="xs"
              variant={choice === 'replace' ? 'primary' : 'outline'}
              onClick={() => onChoose(id, choice === 'replace' ? undefined : 'replace')}
            >
              {t('migration.replace')}
            </Button>
            <Button
              type="button"
              size="xs"
              variant={choice === 'append' ? 'primary' : 'outline'}
              onClick={() => onChoose(id, choice === 'append' ? undefined : 'append')}
            >
              {t('migration.append')}
            </Button>
            {choice && (
              <span className="ml-1 text-[11px] font-semibold text-success-dark dark:text-success">
                ✓ {choice === 'replace' ? t('migration.replace') : t('migration.append')}
              </span>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
