import { useEffect, useState } from 'react';
import type { Template } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import { fetchProcesses } from '../../../api/processes';
import { visibilityLabel } from '../constants';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { PaperBlocksArticle, type PaperArticleBlock } from '../../documents/components/PaperBlocksArticle';

interface Props {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

/**
 * Previsualización de plantilla — usa el mismo `PaperPreviewLayout` y
 * `PaperBlocksArticle` que `DocumentPreviewPage` para garantizar paridad
 * visual exacta. Se monta como overlay (`asOverlay`) porque se abre desde el
 * wizard sin cambiar de ruta.
 */
export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  const [processLabel, setProcessLabel] = useState<string | null>(null);

  useEffect(() => {
    if (!template.process_id) {
      setProcessLabel(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const process = res.data.find((p) => p.id === template.process_id) ?? null;
        if (!process) {
          setProcessLabel(null);
          return;
        }
        setProcessLabel(`Proceso: ${process.code} — ${process.name}`);
      })
      .catch(() => {
        if (!cancelled) setProcessLabel(null);
      });
    return () => {
      cancelled = true;
    };
  }, [template.process_id]);

  const headerMetaInfo = (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
      {processLabel ? (
        <>
          {processLabel}
          {' · '}
        </>
      ) : null}
      {template.author_name ?? 'Autor desconocido'}
      {' · '}
      {visibilityLabel(template.visibility_level)}
      {' · '}
      {blocks.length} {blocks.length === 1 ? 'bloque' : 'bloques'}
      {template.delivery_deadline ? <>{' · '}Fecha límite: {formatDate(template.delivery_deadline)}</> : null}
    </p>
  );

  const headerActions = (
    <>
      <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-warning-light text-warning-dark dark:bg-warning-dark/30 dark:text-warning-light">
        Vista previa
      </span>
      {template.version != null && (
        <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
          v{template.version}
        </span>
      )}
    </>
  );

  const articleBlocks: PaperArticleBlock[] = blocks.map((b) => ({
    id: b.id,
    title: b.title,
    mandatory: b.mandatory,
    isLocked: b.block_state === 'locked',
    nodes: normalizeBlockContentForEditor(b.default_content),
  }));

  return (
    <PaperPreviewLayout
      title={template.name}
      onBack={onClose}
      backLabel="Cerrar previsualización"
      metaInfo={headerMetaInfo}
      actions={headerActions}
      asOverlay
    >
      <PaperBlocksArticle
        title={template.name}
        blocks={articleBlocks}
        emptyMessage="Esta plantilla no tiene bloques."
      />
    </PaperPreviewLayout>
  );
}
