import { lazy, Suspense, useCallback, useMemo, type MutableRefObject, type ReactNode } from 'react';
import { Spinner } from '@ceedcv-maya/shared-ui-react';
import type { DocumentDisplayBlock } from '../../../types/documents';
import { PaperBlocksArticle, type PaperArticleBlock } from './PaperBlocksArticle';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { blockEditorContent } from './documentWizardUtils';
import { blockToUiState } from '../../templates/blockUiState';
import type { SaveStatus } from '@ceedcv-maya/shared-hooks-react';
import type { TiptapDoc } from '@ceedcv-maya/shared-editor-react';

const BlockNoteEditorPanel = lazy(() =>
  import('../../templates/components/BlockNoteEditorPanel').then((m) => ({
    default: m.BlockNoteEditorPanel,
  })),
);

interface Props {
  blocks: DocumentDisplayBlock[];
  activeBlockKey: string | null;
  documentTitle: ReactNode;
  isDark: boolean;
  /** Solo en `draft`/`rejected` un bloque puede convertirse en editor. */
  canEdit: boolean;
  saveStatus: SaveStatus;
  blockSaveError: string | null;
  /** Llamado al hacer click en un bloque distinto del activo. Debe hacer flush del autosave actual. */
  onSelectBlock: (key: string) => void | Promise<void>;
  /** Llamado por el editor del bloque activo cuando cambia su contenido. */
  onContentChange: (content: unknown) => void;
  /** Forzar autoguardado al perder foco del editor (blur, HTML/MD, destroy). */
  onFlush?: (payload?: string | TiptapDoc) => void | Promise<void>;
  /** Flush+sync imperativo del editor activo (antes de cambiar de bloque). */
  editorFlushRef?: MutableRefObject<(() => void | Promise<void>) | null>;
  uploadFile: (file: File) => Promise<string>;
  /** Pendiente de switch: bloquea interacciones mientras se hace flush. */
  switching: boolean;
  /** Si el bloque con esa key está marcado como finalizado por el usuario. */
  isBlockCompleted: (key: string) => boolean;
  /** Toggle finalizado del bloque. */
  onToggleCompleted: (key: string) => void;
  /** Abrir la descripción del bloque en el sidebar (solo se llama si block.description existe). */
  onOpenDescription: (block: DocumentDisplayBlock) => void;
  /** template_block_id del bloque cuya descripción está actualmente abierta (para resaltar el botón). */
  openDescriptionBlockKey: string | null;
  /** Recuento de comentarios del bloque (para el badge). */
  getCommentCount: (block: DocumentDisplayBlock) => number;
}

function keyOf(block: DocumentDisplayBlock): string {
  return block.document_block_id ?? block.template_block_id;
}

/**
 * Tipos de bloque que NO se editan con el editor de texto inline. La portada
 * (cover) se rellena en la vista por-bloques (canvas A4 + placeholders); índice
 * y blank no llevan contenido editable. Alimentarlos al editor TipTap corrompería
 * su `content` (p. ej. el JSON `cover-fill`), así que la vista continua los
 * muestra como aviso de solo lectura que dirige a la vista por-bloques.
 */
const NON_INLINE_BLOCK_TYPES = new Set(['cover', 'index', 'blank']);

function isInlineEditable(block: DocumentDisplayBlock): boolean {
  return !NON_INLINE_BLOCK_TYPES.has(block.block_type ?? 'content');
}

function nonInlineLabel(blockType: string | undefined): string {
  if (blockType === 'cover') return 'Esta portada se rellena en la vista por bloques.';
  if (blockType === 'index') return 'El índice se genera automáticamente; configúralo en la vista por bloques.';
  return 'Este bloque no tiene contenido editable.';
}

function SaveStatusBadge({ status }: { status: SaveStatus }) {
  if (status === 'saving') {
    return (
      <span className="text-xs text-text-muted italic animate-pulse">Guardando…</span>
    );
  }
  if (status === 'saved') {
    return <span className="text-xs text-success-dark font-bold">✓ Guardado</span>;
  }
  if (status === 'error') {
    return <span className="text-xs text-danger-dark font-bold">Error al guardar</span>;
  }
  return null;
}

export function ContinuousDocumentEditor({
  blocks,
  activeBlockKey,
  documentTitle,
  isDark,
  canEdit,
  saveStatus,
  blockSaveError,
  onSelectBlock,
  onContentChange,
  onFlush,
  editorFlushRef,
  uploadFile,
  switching,
  isBlockCompleted,
  onToggleCompleted,
  onOpenDescription,
  openDescriptionBlockKey,
  getCommentCount,
}: Props) {
  const articleBlocks: PaperArticleBlock[] = useMemo(
    () =>
      blocks.map((b) => ({
        id: keyOf(b),
        title: b.title,
        mandatory: b.mandatory,
        isLocked: blockToUiState(b) === 'locked',
        nodes: blockEditorContent(b),
      })),
    [blocks],
  );

  const blockByKey = useMemo(() => {
    const map = new Map<string, DocumentDisplayBlock>();
    for (const b of blocks) map.set(keyOf(b), b);
    return map;
  }, [blocks]);

  const renderBlockBody = useCallback(
    (block: PaperArticleBlock): ReactNode | undefined => {
      if (block.id !== activeBlockKey) return undefined;
      const original = blockByKey.get(block.id);
      if (!original) return undefined;
      const ui = blockToUiState(original);
      if (ui === 'locked' || !canEdit) return undefined;
      // La portada/índice/blank no se editan con el editor de texto inline.
      if (!isInlineEditable(original)) return undefined;
      return (
        <Suspense
          fallback={<div className="p-2 flex justify-center"><Spinner size="sm" /></div>}
          key={`${block.id}-editor`}
        >
          <BlockNoteEditorPanel
            initialContent={blockEditorContent(original)}
            editable
            isDark={isDark}
            onChange={onContentChange}
            onFlush={onFlush}
            editorFlushRef={editorFlushRef}
            uploadFile={uploadFile}
          />
        </Suspense>
      );
    },
    [activeBlockKey, blockByKey, canEdit, isDark, onContentChange, onFlush, editorFlushRef, uploadFile],
  );

  const renderBlockSection = useCallback(
    (block: PaperArticleBlock, body: ReactNode): ReactNode | undefined => {
      const original = blockByKey.get(block.id);
      if (!original) return undefined;
      const ui = blockToUiState(original);
      const isLocked = ui === 'locked';
      const isActive = block.id === activeBlockKey;
      const interactive = canEdit && !isLocked;
      const sortLabel = `#${original.sort_order ?? '?'}`;
      const completed = isBlockCompleted(block.id);
      const hasDescription = !!original.description;
      const descriptionOpen = openDescriptionBlockKey === block.id;
      const commentCount = getCommentCount(original);
      const handleSectionClick = () => {
        if (!interactive || isActive || switching) return;
        void onSelectBlock(block.id);
      };
      const btnBase =
        'shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider transition-all';
      const btnIdle =
        'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/40 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5';
      const btnActive = 'border-odoo-purple text-odoo-purple bg-odoo-purple/10';
      const btnSuccess =
        'border-success/60 text-success-dark bg-success/10 dark:bg-success-dark/20 dark:text-success-light';

      return (
        <section
          aria-current={isActive ? 'true' : undefined}
          onClick={handleSectionClick}
          className={[
            'relative group rounded-lg transition-all duration-200',
            interactive ? 'cursor-pointer' : '',
            // Anillos: prioridad → activo > completado > hover
            isActive
              ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
              : completed
                ? 'ring-2 ring-success/60 ring-offset-4 dark:ring-offset-ui-dark-card'
                : interactive
                  ? 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card'
                  : '',
            switching && !isActive ? 'pointer-events-none opacity-70' : '',
          ].join(' ')}
          style={isLocked ? { opacity: 0.55 } : undefined}
        >
          <div
            className={[
              'absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200',
              isActive
                ? 'opacity-100 text-odoo-purple'
                : completed
                  ? 'opacity-100 text-success-dark'
                  : 'opacity-0 group-hover:opacity-40 text-text-muted',
            ].join(' ')}
          >
            {sortLabel}
          </div>

          <div className="flex flex-wrap items-center gap-2 mb-2">
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
            {isLocked && (
              <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                Bloqueado
              </span>
            )}

            <div className="ml-auto flex items-center gap-1.5">
              {commentCount > 0 && (
                <span
                  className="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-warning-dark dark:text-warning-light"
                  title={`${commentCount} comentario(s) en este bloque`}
                >
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                  {commentCount}
                </span>
              )}
              {hasDescription && (
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    onOpenDescription(original);
                  }}
                  className={[btnBase, descriptionOpen ? btnActive : btnIdle].join(' ')}
                  title="Ver descripción / instrucciones del bloque"
                >
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>Descripción</span>
                </button>
              )}
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  onToggleCompleted(block.id);
                }}
                className={[btnBase, completed ? btnSuccess : btnIdle].join(' ')}
                title={completed ? 'Marcar como pendiente' : 'Marcar bloque como finalizado'}
                aria-pressed={completed}
              >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
                <span>{completed ? 'Finalizado' : 'Finalizar'}</span>
              </button>
              {isActive && <SaveStatusBadge status={saveStatus} />}
            </div>
          </div>

          {/* Cuando el bloque es activo y editable, `body` es el editor; en otro caso es BlockContentHtml read-only. */}
          {isActive && body
            ? body
            : !isInlineEditable(original)
              ? (
                  <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                    {nonInlineLabel(original.block_type)}
                  </p>
                )
              : (() => {
                  const nodes = blockEditorContent(original);
                  return nodes.length > 0 ? (
                    <BlockContentHtml content={nodes} />
                  ) : (
                    <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                      Sin contenido.
                    </p>
                  );
                })()}
        </section>
      );
    },
    [
      activeBlockKey,
      blockByKey,
      canEdit,
      getCommentCount,
      isBlockCompleted,
      onOpenDescription,
      onSelectBlock,
      onToggleCompleted,
      openDescriptionBlockKey,
      saveStatus,
      switching,
    ],
  );

  return (
    <>
      {blockSaveError && (
        <div className="mb-4 rounded-lg border border-danger/30 bg-danger/5 px-4 py-2 text-xs text-danger-dark dark:text-danger">
          {blockSaveError}
        </div>
      )}
      <PaperBlocksArticle
        title={documentTitle}
        blocks={articleBlocks}
        renderBlockBody={renderBlockBody}
        renderBlockSection={renderBlockSection}
      />
    </>
  );
}
