import React, { Suspense, lazy } from 'react';
import type { TemplateBlock } from '../../types/blocks';
import { BlankBlockEditor } from './BlankBlockEditor';
import { TocBlockEditor } from './TocBlockEditor';

const BlockNoteEditorPanel = lazy(() =>
  import('../templates/components/BlockNoteEditorPanel').then(m => ({
    default: m.BlockNoteEditorPanel,
  }))
);

const CoverBlockEditor = lazy(() =>
  import('./CoverBlockEditor').then(m => ({
    default: m.CoverBlockEditor,
  }))
);

interface BlockEditorProps {
  block: TemplateBlock;
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange?: (content: unknown) => void;
  onFullscreenChange?: (isFullscreen: boolean) => void;
  uploadFile?: (file: File) => Promise<string>;
}

export function BlockEditor({
  block,
  initialContent,
  editable,
  isDark,
  onChange,
  onFullscreenChange,
  uploadFile,
}: BlockEditorProps) {
  const kind = block.kind ?? 'content';

  if (kind === 'blank') {
    return <BlankBlockEditor />;
  }

  if (kind === 'toc') {
    return <TocBlockEditor />;
  }

  if (kind === 'cover') {
    return (
      <Suspense fallback={<div className="p-4">Cargando editor...</div>}>
        <CoverBlockEditor
          block={block}
          onChange={onChange || (() => {})}
          isDark={isDark}
          editable={editable}
          onFullscreenChange={onFullscreenChange}
          uploadFile={uploadFile}
        />
      </Suspense>
    );
  }

  // Default: content
  return (
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
  );
}
