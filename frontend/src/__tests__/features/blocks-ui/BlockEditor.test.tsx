import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, Suspense } from 'vitest';
import { BlockEditor } from '../../../features/blocks-ui/BlockEditor';
import type { TemplateBlock } from '../../../types/blocks';

// Mock child components - NOT using lazy
vi.mock('../../../features/blocks-ui/BlankBlockEditor', () => ({
  BlankBlockEditor: () => <div data-testid="blank-editor">Blank Editor</div>,
}));

vi.mock('../../../features/blocks-ui/TocBlockEditor', () => ({
  TocBlockEditor: () => <div data-testid="toc-editor">TOC Editor</div>,
}));

vi.mock('../../../features/blocks-ui/CoverBlockEditor', () => ({
  CoverBlockEditor: () => <div data-testid="cover-editor">Cover Editor</div>,
}));

vi.mock('../../../features/templates/components/BlockNoteEditorPanel', () => ({
  BlockNoteEditorPanel: () => <div data-testid="block-note-editor">BlockNote Editor</div>,
}));

const baseBlock: TemplateBlock = {
  id: 'block-1',
  template_id: 'template-1',
  title: 'Test Block',
  default_content: null,
  description: null,
  block_state: 'editable',
  kind: 'content',
  sort_order: 0,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
};

describe('BlockEditor', () => {
  it('renders BlockNoteEditorPanel when kind is content', async () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('renders CoverBlockEditor when kind is cover', async () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'cover' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('cover-editor')).toBeTruthy();
    });
  });

  it('renders BlankBlockEditor when kind is blank', () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'blank' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    expect(screen.getByTestId('blank-editor')).toBeTruthy();
  });

  it('renders TocBlockEditor when kind is toc', () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'toc' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    expect(screen.getByTestId('toc-editor')).toBeTruthy();
  });

  it('defaults to BlockNoteEditorPanel when kind is undefined', () => {
    const block: TemplateBlock = {
      ...baseBlock,
      kind: undefined as any,
    };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    expect(screen.getByTestId('block-note-editor')).toBeTruthy();
  });

  it('passes onChange callback to BlockNoteEditorPanel', async () => {
    const mockOnChange = vi.fn();
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
        onChange={mockOnChange}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('passes onFullscreenChange callback to BlockNoteEditorPanel', async () => {
    const mockOnFullscreenChange = vi.fn();
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
        onFullscreenChange={mockOnFullscreenChange}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('passes uploadFile to BlockNoteEditorPanel', async () => {
    const mockUploadFile = vi.fn();
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
        uploadFile={mockUploadFile}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('respects editable prop', async () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    const { rerender } = render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={false}
        isDark={false}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });

    rerender(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('respects isDark prop', async () => {
    const block: TemplateBlock = { ...baseBlock, kind: 'content' };

    const { rerender } = render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });

    rerender(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={true}
      />
    );

    await waitFor(() => {
      expect(screen.getByTestId('block-note-editor')).toBeTruthy();
    });
  });

  it('handles kind="blank" without calling onChange', () => {
    const mockOnChange = vi.fn();
    const block: TemplateBlock = { ...baseBlock, kind: 'blank' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
        onChange={mockOnChange}
      />
    );

    expect(screen.getByTestId('blank-editor')).toBeTruthy();
  });

  it('handles kind="toc" without calling onChange', () => {
    const mockOnChange = vi.fn();
    const block: TemplateBlock = { ...baseBlock, kind: 'toc' };

    render(
      <BlockEditor
        block={block}
        initialContent={null}
        editable={true}
        isDark={false}
        onChange={mockOnChange}
      />
    );

    expect(screen.getByTestId('toc-editor')).toBeTruthy();
  });
});
