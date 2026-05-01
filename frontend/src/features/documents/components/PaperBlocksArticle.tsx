import type { ReactNode } from 'react'
import { BlockContentHtml } from '../../templates/components/BlockContentHtml'

export interface PaperArticleBlock {
  /** Identificador estable para `key`. */
  id: string
  /** Título de la sección (negrita pequeña sobre el contenido). */
  title?: string | null
  /** Si true, muestra badge "Obligatorio". */
  mandatory?: boolean
  /** Si true, atenúa la sección y muestra badge "Bloqueado". */
  isLocked?: boolean
  /** Nodos ya normalizados para `BlockContentHtml`. */
  nodes: unknown[]
}

interface Props {
  /** Título grande del documento/plantilla, en el H1 con borde inferior. */
  title: ReactNode
  /** Bloques a renderizar en el cuerpo del artículo. */
  blocks: PaperArticleBlock[]
  /** Mensaje cuando no hay bloques. */
  emptyMessage?: ReactNode
  /** Mensaje cuando un bloque no tiene contenido. */
  blockEmptyMessage?: ReactNode
  /** Slot opcional sobre el H1 (por ejemplo loading/error states). */
  topSlot?: ReactNode
}

/**
 * Cuerpo del artículo "papel" reusable: un H1 con borde inferior + lista de
 * secciones con título/badges/contenido. Lo usan tanto la previsualización de
 * documentos como la de plantillas para garantizar paridad visual exacta.
 */
export function PaperBlocksArticle({
  title,
  blocks,
  emptyMessage = 'Este documento no tiene bloques.',
  blockEmptyMessage = 'Sin contenido.',
  topSlot,
}: Props) {
  return (
    <>
      {topSlot}
      <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
        {title}
      </h1>

      {blocks.length === 0 ? (
        <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
          {emptyMessage}
        </p>
      ) : (
        <div className="space-y-10">
          {blocks.map((block) => {
            const hasContent = block.nodes.length > 0
            return (
              <section
                key={block.id}
                style={block.isLocked ? { opacity: 0.45 } : undefined}
              >
                <div className="flex flex-wrap items-baseline gap-2 mb-2">
                  {block.title && (
                    <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                      {block.title}
                    </h4>
                  )}
                  {block.mandatory && (
                    <span className="text-xs font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-success-light text-success-dark dark:bg-success-dark/30 dark:text-success-light">
                      Obligatorio
                    </span>
                  )}
                  {block.isLocked && (
                    <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                      Bloqueado
                    </span>
                  )}
                </div>
                {hasContent ? (
                  <BlockContentHtml content={block.nodes} />
                ) : (
                  <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                    {blockEmptyMessage}
                  </p>
                )}
              </section>
            )
          })}
        </div>
      )}
    </>
  )
}
