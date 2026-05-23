import React, { useRef, Suspense, lazy } from 'react';
import { useCoverOverflow } from './useCoverOverflow';
import type { TemplateBlock } from '../../types/blocks';

const BlockNoteEditorPanel = lazy(() =>
  import('../templates/components/BlockNoteEditorPanel').then(m => ({
    default: m.BlockNoteEditorPanel,
  }))
);

interface CoverBlockEditorProps {
  block: TemplateBlock;
  onChange: (content: unknown) => void;
  isDark: boolean;
  editable: boolean;
  onFullscreenChange?: (isFullscreen: boolean) => void;
  uploadFile?: (file: File) => Promise<string>;
}

export function CoverBlockEditor({
  block,
  onChange,
  isDark,
  editable,
  onFullscreenChange,
  uploadFile,
}: CoverBlockEditorProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const { isOverflowing, pageCount } = useCoverOverflow(containerRef);

  const initialContent = block.default_content
    ? typeof block.default_content === 'string'
      ? JSON.parse(block.default_content)
      : block.default_content
    : undefined;

  return (
    <div className="flex-1 min-h-0 p-6 flex flex-col overflow-auto bg-ui-body/30 dark:bg-ui-dark-bg">
      {isOverflowing && (
        <div className="sticky top-0 z-20 mb-4 px-4 py-3 rounded-lg border-l-4 border-warning bg-warning/10 dark:bg-warning-dark/30 text-warning-dark dark:text-warning-light flex items-center gap-2">
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="shrink-0"
            aria-hidden="true"
          >
            <path d="M12 8v4m0 4v.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span className="text-sm font-semibold">
            Esta portada generará {pageCount} página{pageCount !== 1 ? 's' : ''} en el PDF
          </span>
        </div>
      )}

      <div
        ref={containerRef}
        className="relative mx-auto flex-1 min-h-0 max-w-[21cm] w-full"
      >
        <div
          className="cover-editor-wrapper relative w-[21cm] min-h-[29.7cm] mx-auto bg-white text-black font-sans border border-ui-border dark:border-ui-dark-border shadow-lg"
          style={{
            fontFamily: 'system-ui, sans-serif',
            color: 'black',
            background: 'white',
          }}
        >
          {/* Punteada roja a 29.7cm */}
          <div
            className="absolute left-0 right-0 pointer-events-none"
            style={{
              top: '29.7cm',
              borderTopWidth: '1px',
              borderTopStyle: 'dashed',
              borderTopColor: '#ef4444',
              width: '100%',
            }}
            aria-hidden="true"
          />

          {/* Editor BlockNote */}
          <div className="relative z-10 h-full overflow-hidden">
            <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
              <BlockNoteEditorPanel
                initialContent={initialContent}
                onChange={onChange}
                editable={editable}
                isDark={isDark}
                onFullscreenChange={onFullscreenChange}
                uploadFile={uploadFile}
              />
            </Suspense>
          </div>
        </div>
      </div>
    </div>
  );
}
