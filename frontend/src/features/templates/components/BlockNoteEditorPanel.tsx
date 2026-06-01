import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@ceedcv-maya/shared-ui-react';
import { BlockNoteEditor, BlockNoteSchema, defaultBlockSpecs } from '@blocknote/core';
import { FormattingToolbar } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/ariakit';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';
import { createIframeBlock } from './IframeBlock'
import '@blocknote/ariakit/style.css';
import '../styles/blocknote-panel.css';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';

interface Props {
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange?: (content: unknown) => void;
  onFullscreenChange?: (isFullscreen: boolean) => void;
  uploadFile?: (file: File) => Promise<string>;
}

// BlockNote 0.49 changed blockGroup/blockContainer renderHTML to return {dom,contentDOM}
// instead of a ProseMirror DOMSpec array. ProseMirror's renderSpec crashes on those unless
// custom nodeViews are registered. Patch the TipTap extensionManager instance to inject them.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function patchBlockNoteStructuralNodeViews(tiptapEditor: any): void {
  const extMgr = tiptapEditor?.extensionManager;
  if (!extMgr) return;
  const proto = Object.getPrototypeOf(extMgr);
  const desc = Object.getOwnPropertyDescriptor(proto, 'nodeViews');
  if (!desc?.get) return;
  const origGet = desc.get;
  Object.defineProperty(extMgr, 'nodeViews', {
    get() {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const views: Record<string, any> = origGet.call(this);
      if (!views.blockGroup) {
        views.blockGroup = () => {
          const n = document.createElement('div');
          n.className = 'bn-block-group';
          n.setAttribute('data-node-type', 'blockGroup');
          return { dom: n, contentDOM: n };
        };
      }
      if (!views.blockContainer) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        views.blockContainer = (initialNode: any) => {
          const outer = document.createElement('div');
          outer.className = 'bn-block-outer';
          outer.setAttribute('data-node-type', 'blockOuter');
          const inner = document.createElement('div');
          inner.className = 'bn-block';
          inner.setAttribute('data-node-type', 'blockContainer');
          outer.appendChild(inner);
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const applyAttrs = (node: any) => {
            if (node.attrs?.id) inner.setAttribute('data-id', String(node.attrs.id));
            if (node.attrs?.blockColor) inner.setAttribute('data-block-color', String(node.attrs.blockColor));
            if (node.attrs?.blockStyle) inner.setAttribute('data-block-style', String(node.attrs.blockStyle));
          };
          applyAttrs(initialNode);
          return {
            dom: outer,
            contentDOM: inner,
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            update(newNode: any) {
              if (newNode.type !== initialNode.type) return false;
              applyAttrs(newNode);
              return true;
            },
          };
        };
      }
      return views;
    },
    configurable: true,
  });
}

function FullscreenIcon({ expanded }: { expanded: boolean }) {
  return expanded ? (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M8 3v3a2 2 0 0 1-2 2H3" />
      <path d="M21 8h-3a2 2 0 0 1-2-2V3" />
      <path d="M3 16h3a2 2 0 0 1 2 2v3" />
      <path d="M16 21v-3a2 2 0 0 1 2-2h3" />
    </svg>
  ) : (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M8 3H5a2 2 0 0 0-2 2v3" />
      <path d="M21 8V5a2 2 0 0 0-2-2h-3" />
      <path d="M3 16v3a2 2 0 0 0 2 2h3" />
      <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
    </svg>
  );
}

function cleanHtmlForPaste(html: string): string {
  // Replace newlines and multiple spaces between tags with a single space.
  // This prevents ProseMirror from collapsing newlines between inline tags into "nothing".
  return html
    .replace(/\r?\n/g, ' ')
    .replace(/>\s+</g, '> <')
    .replace(/\s+/g, ' ');
}

export function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange, onFullscreenChange, uploadFile }: Props) {
  const { t } = useTranslation('documents');
  const { addToast } = useToast();
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const onFullscreenChangeRef = useRef(onFullscreenChange);
  onFullscreenChangeRef.current = onFullscreenChange;

  const applyFullscreen = (v: boolean) => {
    setIsFullscreen(v);
    onFullscreenChangeRef.current?.(v);
  };

  useEffect(() => {
    if (!isFullscreen) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') applyFullscreen(false);
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [isFullscreen]); // eslint-disable-line react-hooks/exhaustive-deps

  const normalized = normalizeBlockContentForEditor(initialContent);
  const safeContent =
    normalized.length > 0
      ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (normalized as any)
      : undefined;

  //Added iframe block for youtube videos    
  const schema = BlockNoteSchema.create().extend({
    blockSpecs: {
      ...defaultBlockSpecs,
      iframe: createIframeBlock(), // Aquí registramos el bloque iframe
    },
  })

  // Use a ref for stable editor identity across React StrictMode's double-mount cycle.
  // useCreateBlockNote registers ProseMirror clipboard handlers on mount; without this
  // guard StrictMode's mount→cleanup→remount leaves two handlers → paste fires twice.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editorRef = useRef<any>(null);
  const uploadFileRef = useRef(uploadFile);
  if (!editorRef.current) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    editorRef.current = (BlockNoteEditor as any).create({
      schema, // PASAMOS EL NUEVO ESQUEMA
      initialContent: safeContent ? repairBlockNoteBlocks(safeContent) : undefined,
      uploadFile: uploadFileRef.current,
    });
    patchBlockNoteStructuralNodeViews((editorRef.current as any)._tiptapEditor);
  }
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editor = editorRef.current as any;

  useEffect(() => {
    let dom: HTMLElement | undefined;
    try {
      dom = (editor as any)._tiptapEditor?.view?.dom as HTMLElement | undefined;
    } catch {
      return;
    }
    if (!dom) return;

    const handlePaste = (e: ClipboardEvent) => {
    const selection = editor.getTextCursorPosition();
    const currentBlock = editor.document.find((b: any) => b.id === selection.block.id);
    const activeElement = document.activeElement;
    const isInputFocused = activeElement && (activeElement.tagName === "INPUT")
      
    // Si estamos en un bloque de código, no interceptamos
    if (currentBlock.type === 'codeBlock' || isInputFocused) {
      return; 
    }

    const plain = e.clipboardData?.getData('text/plain') ?? '';
    const html = e.clipboardData?.getData('text/html') ?? '';

    if (!plain.trim() && !html.trim()) return;

    let parsedBlocks: any[] | undefined;
    let handled = false;

    if (html && html.includes('<')) {
      const cleaned = cleanHtmlForPaste(html);
      try {
        parsedBlocks = (editor as any).tryParseHTMLToBlocks(cleaned);
        if (parsedBlocks?.length) handled = true;
      } catch {
        // Fallback to plain text below
      }
    }

    if (!handled && plain) {
      try {
        parsedBlocks = (editor as any).tryParseMarkdownToBlocks(plain);
        if (parsedBlocks?.length) handled = true;
      } catch {
        addToast({ message: t('blocks.pasteError', 'No se pudo pegar el contenido'), tone: 'danger' });
      }
    }

    if (handled && parsedBlocks) {
      // Inserción de bloques como antes
      const currentBlocks = editor.document;
      const blockIdx = currentBlocks.findIndex((b: any) => b.id === selection.block.id);
      if (blockIdx !== -1) {
        editor.insertBlocks(parsedBlocks, currentBlocks[blockIdx].id, 'after');
        const isEmpty = !currentBlocks[blockIdx].content?.length || 
          (currentBlocks[blockIdx].content.length === 1 && !currentBlocks[blockIdx].content[0].text);
        if (isEmpty) editor.removeBlocks([currentBlocks[blockIdx].id]);
      } else {
        editor.insertBlocks(parsedBlocks, currentBlocks[currentBlocks.length - 1].id, 'after');
      }

      onChange?.(editor.document);
      e.preventDefault();
      e.stopPropagation();
    }
  };

    // Use capture phase to ensure we intercept before the default handler.
    dom.addEventListener('paste', handlePaste, true);
    return () => dom.removeEventListener('paste', handlePaste, true);
  }, [editor, onChange, addToast]);

  // Click anywhere in the empty editor area focuses the editor at the end.
  const handleUndo = () => {
    try { (editor as any).undo(); } catch { /* noop */ }
  };
  const handleRedo = () => {
    try { (editor as any).redo(); } catch { /* noop */ }
  };

  const handleAreaClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!editable) return;
    const target = e.target as HTMLElement;
    if (target.closest('.bn-toolbar') || target.closest('[role="toolbar"]')) return;
    if (target.closest('.bn-fullscreen-btn') !== null) return;
    if (target.closest('.ProseMirror') !== null) return;
    editor.focus();
  };

  const SLASH_ACTIONS = [
    {
      name: "Tabla",
      icon: "⊞",
      color: "#F59E0B",
      desc: "Cuadrícula de datos",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks(
          [
            {
              name: "Crear tabla",
              type: "table",
              content: {
                type: "tableContent",
                rows: [
                  { cells: [[], []] },
                  { cells: [[], []] },
                ],
              },
            },
          ],
          targetId,
          "after"
        );
      },
    },

    {
      name: "Imagen",
      icon: "🖼",
      desc: "Insertar imagen",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks([{ type: "image" }], targetId, "after");
      },
    },

    /*{
      name: "Vídeo",
      icon: "▶",
      color: "#EC4899",
      desc: "Insertar vídeo local",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks([{ type: "video" as any }], targetId, "after");
      },
    },*/

    {
      name: "Código",
      icon: "</>",
      desc: "Bloque de código",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.updateBlock(targetId, { type: "codeBlock" });
      },
    },

    {
      name: "Cita",
      icon: "❝",
      desc: "Cita en bloque",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks([{ type: "quote" as any }], targetId, "after");
      },
    },

    {
      name: "Separador",
      icon: "—",
      desc: "Línea divisoria",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks([{ type: "horizontalRule" as any }], targetId, "after");
      },
    },

    {
      name: "Info",
      icon: "ℹ",
      desc: "Alerta informativa",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks(
          [{ type: "paragraph", content: "ℹ Info: " }],
          targetId,
          "after"
        );
      },
    },

    {
      name: "Aviso",
      icon: "⚠",
      desc: "Alerta de advertencia",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks(
          [{ type: "paragraph", content: "⚠ Aviso: " }],
          targetId,
          "after"
        );
      },
    },

    {
      name: "Error",
      icon: "✕",
      desc: "Mensaje de error",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks(
          [{ type: "paragraph", content: "✕ Error: " }],
          targetId,
          "after"
        );
      },
    },
    {
      name: "Embed Vídeo",
      icon: "<->",
      desc: "Insertar vídeo youtube",
      run: () => {
        const currentBlock = editor.getTextCursorPosition().block;
        const targetId = currentBlock?.id;
        if (!targetId) return;

        editor.insertBlocks([
        {
          type: "iframe",
        },
      ], targetId, "after");
      },
    },
  ];

  const containerCls = isFullscreen
    ? 'maya-bn-panel maya-bn-panel--fullscreen flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card overflow-hidden'
    : 'maya-bn-panel flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card overflow-hidden';

  // Dentro de tu componente
  const [showMarkdown, setShowMarkdown] = useState(false);
  const [markdown, setMarkdown] = useState("");

  const toggleMarkdownView = async () => {
    if (!showMarkdown) {
      // Activando Markdown: obtenemos el Markdown del editor
      const md = await editor.blocksToMarkdownLossy(editor.document);
      setMarkdown(md);
      setShowMarkdown(true);
    } else {
      // Volviendo al BlockNote: parseamos Markdown a bloques
      try {
        const blocks = await editor.tryParseMarkdownToBlocks(markdown);
        editor.replaceBlocks(editor.document, blocks);
      } catch {
        addToast({ message: t('blocks.pasteError', 'No se pudo pegar el contenido'), tone: 'danger' });
      }
      setShowMarkdown(false);
    }
  };

  return (
    <div
      ref={containerRef}
      className={containerCls}
      onClick={handleAreaClick}
    >
      {!editable && (
        <div className="mx-4 mt-3 px-3 py-2 text-xs text-text-muted dark:text-text-dark-muted bg-ui-body dark:bg-ui-dark-bg rounded border border-ui-border dark:border-ui-dark-border shrink-0">
          Este bloque está bloqueado y no puede editarse.
        </div>
      )}
      <div className="flex-1 min-h-0 relative overflow-y-auto">
        {!showMarkdown ? (
          <BlockNoteView
            editor={editor as any}
            editable={editable}
            theme={isDark ? 'dark' : 'light'}
            formattingToolbar={false}
            onChange={() => {
              if (debounceRef.current) clearTimeout(debounceRef.current);
              debounceRef.current = setTimeout(() => {
                onChange?.(editor.document);
              }, 200);
            }}
          >
            {editable && (
              <div className="order-first sticky top-0 z-10 w-full border-b border-ui-border dark:border-ui-dark-border bg-ui-card/95 dark:bg-ui-dark-card/95 backdrop-blur-md px-2 py-1 shadow-sm flex items-center gap-1">
                <div className="flex items-center gap-0.5 shrink-0 pr-1 border-r border-ui-border dark:border-ui-dark-border mr-1">
                  <button
                    type="button"
                    onClick={handleUndo}
                    aria-label={t('blocks.undoAria')}
                    title={t('blocks.undoTitle')}
                    className="p-1 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus-visible:ring-2 focus-visible:ring-odoo-purple/50 focus:outline-none"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                      <path d="M3 7v6h6" /><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    onClick={handleRedo}
                    aria-label={t('blocks.redoAria')}
                    title={t('blocks.redoTitle')}
                    className="p-1 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus-visible:ring-2 focus-visible:ring-odoo-purple/50 focus:outline-none"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                      <path d="M21 7v6h-6" /><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13" />
                    </svg>
                  </button>
                </div>
                <div className="flex flex-wrap gap-1 max-w">
                  <FormattingToolbar />
                  {SLASH_ACTIONS.map((action) => (
                    <button
                      key={action.name}
                      type="button"
                      onClick={action.run}
                      title={action.desc}
                      className="bn-fullscreen-btn shrink-0 p-1.5 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus:outline-none"
                >
                      <span>{action.icon}</span>
                    </button>
                  ))}
                  
                <button
                  type="button"
                  aria-label="Markdown"
                  onClick={toggleMarkdownView}
                  title="Markdown mode"
                  className="bn-fullscreen-btn shrink-0 p-1.5 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus:outline-none"
                >
                  M ↓
                </button>
                </div>
                <div className="flex items-end gap-0.5 shrink-0 pl-1 border-l border-ui-border dark:border-ui-dark-border ml-auto">
                  <button
                    type="button"
                    aria-label={t('documents:wizard.exitFullscreenAria')}
                    title={t('documents:wizard.exitFullscreenTitle')}
                    aria-pressed={isFullscreen}
                    title={isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
                    onClick={(e) => { e.stopPropagation(); applyFullscreen(!isFullscreen); document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))}}
                    className="bn-fullscreen-btn shrink-0 p-1.5 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus:outline-none"
                  >
                    <FullscreenIcon expanded={isFullscreen} />
                  </button>
                </div>
              </div>
            )}
          </BlockNoteView>
          ) : (
          <div className="relative h-full flex flex-col">
            
              <button
                onClick={toggleMarkdownView}
                className="bn-fullscreen-btn shrink-0 p-1.5 rounded hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus:outline-none"
              >
                Volver al editor
              </button>
            
            <textarea
              className="w-full h-full p-2 border rounded bg-ui-body dark:bg-ui-dark-card text-xs font-mono resize-none flex-1"
              value={markdown}
              onChange={(e) => setMarkdown(e.target.value)}
            />
          </div>
        )}
      </div>
    </div>
  );
}
