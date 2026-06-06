import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { MigrationBlockItem } from './MigrationBlockItem';
import type {
  DocumentMigrationBlock,
  DocumentMigrationPayload,
  MigrationChoice,
} from '../schemas/migrationPayload';

type Props = {
  payload: DocumentMigrationPayload;
  choices: Record<string, MigrationChoice>;
  onChoose: (templateBlockId: string, choice: MigrationChoice | undefined) => void;
};

/** True para bloques cuyo contenido antiguo el usuario puede precargar. */
export function isActionableMigrationBlock(b: DocumentMigrationBlock): boolean {
  return !b.locked && !b.new_block && !b.removed_block && b.old_content != null;
}

/**
 * Paso del wizard que aparece solo al continuar un documento cuya plantilla
 * tiene una versión más nueva. Muestra, por bloque, el contenido nuevo (verde)
 * frente al contenido antiguo del usuario (rojo) y permite arrastrarlo
 * (Reemplazar / Anexar) a la versión nueva, salvo en bloques bloqueados.
 */
export function DocumentMigrationStep({ payload, choices, onChoose }: Props) {
  const { t } = useTranslation('documents');

  const { active, removed, other } = useMemo(() => {
    const ordered = [...payload.blocks].sort((a, b) => a.sort_order - b.sort_order);
    return {
      active: ordered.filter(isActionableMigrationBlock),
      removed: ordered.filter((b) => b.removed_block),
      other: ordered.filter((b) => !isActionableMigrationBlock(b) && !b.removed_block),
    };
  }, [payload.blocks]);

  return (
    <div className="flex-1 min-h-0 flex flex-col bg-ui-card dark:bg-ui-dark-card overflow-hidden">
      <div className="flex-1 overflow-y-auto px-8 py-6 space-y-6">
        <div className="rounded-lg border border-odoo-purple/20 bg-odoo-purple/5 px-4 py-3">
          <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            {t('migration.intro', {
              from: payload.source_version_number,
              to: payload.target_version_number,
            })}
          </p>
          <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">{t('migration.legend')}</p>
        </div>

        {active.map((block) => (
          <MigrationBlockItem
            key={block.template_block_id}
            block={block}
            choice={choices[block.template_block_id]}
            onChoose={onChoose}
          />
        ))}

        {other.map((block) => (
          <MigrationBlockItem
            key={block.template_block_id}
            block={block}
            choice={undefined}
            onChoose={onChoose}
          />
        ))}

        {removed.length > 0 && (
          <details className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3">
            <summary className="cursor-pointer text-xs font-black uppercase tracking-widest text-danger">
              {t('migration.removedSection', { count: removed.length })}
            </summary>
            <div className="mt-4 space-y-4">
              {removed.map((block) => (
                <MigrationBlockItem
                  key={block.template_block_id}
                  block={block}
                  choice={undefined}
                  onChoose={onChoose}
                />
              ))}
            </div>
          </details>
        )}
      </div>
    </div>
  );
}
