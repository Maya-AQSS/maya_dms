import type { ReactNode } from 'react';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';

/**
 * Descripción de bloque: el backend puede enviar string, JSON string u objeto
 * BlockNote (`{ type: 'doc', content }`). Renderizar un objeto dentro de `<p>`
 * rompe React (pantalla en blanco), así que normalizamos antes de pintar.
 */
export function DocumentBlockDescriptionView({ description }: { description: unknown }) {
  if (description === null || description === undefined || description === '') {
    return null;
  }

  const wrapProse = (inner: ReactNode) => (
    <div className="prose prose-sm dark:prose-invert max-w-none">{inner}</div>
  );

  if (typeof description === 'string') {
    try {
      const parsed: unknown = JSON.parse(description);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const doc = parsed as { type?: string; content?: unknown };
        if (doc.type === 'doc' && Array.isArray(doc.content)) {
          return wrapProse(<BlockContentHtml content={doc.content as unknown[]} />);
        }
      }
      if (Array.isArray(parsed)) {
        return wrapProse(<BlockContentHtml content={parsed as unknown[]} />);
      }
    } catch {
      return (
        <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
          {description}
        </p>
      );
    }
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
        {description}
      </p>
    );
  }

  if (Array.isArray(description)) {
    return wrapProse(<BlockContentHtml content={description as unknown[]} />);
  }

  if (typeof description === 'object') {
    const doc = description as { type?: string; content?: unknown };
    if (doc.type === 'doc' && Array.isArray(doc.content)) {
      return wrapProse(<BlockContentHtml content={doc.content as unknown[]} />);
    }
    return wrapProse(<BlockContentHtml content={[description] as unknown[]} />);
  }

  return (
    <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
      {String(description)}
    </p>
  );
}

export function DocSummaryRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col py-1.5 border-b border-ui-border dark:border-ui-dark-border/30 last:border-0">
      <dt className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
        {label}
      </dt>
      <dd className="mt-0.5 text-xs font-medium text-text-primary dark:text-text-dark-primary">
        {value || <span className="text-text-muted italic">—</span>}
      </dd>
    </div>
  );
}
