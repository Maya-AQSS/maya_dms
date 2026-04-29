import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { PageTitle } from '@maya/shared-ui-react';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { visibilityLabel } from '../constants';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';

// ── Types ─────────────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
};

function blockContentNodes(block: TemplateBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.default_content);
  if (fromContent.length > 0) return fromContent;
  return [];
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

// ── Modal ─────────────────────────────────────────────────────────────────────

export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return createPortal(
    <div className="fixed inset-0 overflow-y-auto bg-app-gradient" style={{ zIndex: 9999 }}>
      <div className="px-4 pt-4">
        <PageTitle
          title={template.name}
          subtitle="Previsualización"
          onBack={onClose}
          backLabel="Volver al resumen"
          meta={
            <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
              {template.author_name ?? 'Autor desconocido'}
              {' · '}
              {visibilityLabel(template.visibility_level)}
              {' · '}
              Fecha límite de validación: {formatDate(template.delivery_deadline)}
              {' · '}
              Última edición: {formatDate(template.updated_at)}
            </p>
          }
          className="!mb-0"
        />
      </div>

      {/* Paper */}
      <article
        className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content my-6"
        style={{ maxWidth: '760px', padding: '56px 72px' }}
      >
        <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
          {template.name}
        </h1>

        {blocks.length === 0 ? (
          <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
            Esta plantilla no tiene bloques.
          </p>
        ) : (
          <div className="space-y-10">
            {blocks.map((block) => {
              const isLocked = block.block_state === 'locked';
              const nodes = blockContentNodes(block);
              const hasContent = nodes.length > 0;

              return (
                <section
                  key={block.id}
                  style={isLocked ? { opacity: 0.45, pointerEvents: 'none' } : undefined}
                >
                  <div className="flex flex-wrap items-baseline gap-2 mb-2">
                    {block.title && (
                      <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                        {block.title}
                      </h4>
                    )}
                    {block.mandatory && (
                      <span className="text-xs font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                        Obligatorio
                      </span>
                    )}
                    {isLocked && (
                      <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                        Bloqueado
                      </span>
                    )}
                  </div>
                  {hasContent ? (
                    <BlockContentHtml content={nodes} />
                  ) : (
                    <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                      Sin contenido.
                    </p>
                  )}
                </section>
              );
            })}
          </div>
        )}
      </article>
    </div>,
    document.body,
  );
}
