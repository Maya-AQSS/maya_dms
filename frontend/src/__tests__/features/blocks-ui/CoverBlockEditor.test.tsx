import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CoverBlockEditor } from '../../../features/blocks-ui/CoverBlockEditor';
import type { TemplateBlock } from '../../../types/blocks';

// Mock useCoverOverflow
const coverOverflowMock = {
  isOverflowing: false,
  pageCount: 1,
};

vi.mock('../../../features/blocks-ui/useCoverOverflow', () => ({
  useCoverOverflow: vi.fn(() => coverOverflowMock),
}));

// Mock BlockNoteEditorPanel
vi.mock('../../../features/templates/components/BlockNoteEditorPanel', () => ({
  BlockNoteEditorPanel: ({ onChange }: any) => (
    <div data-testid="block-note-editor" onClick={() => onChange?.(['text'])} />
  ),
}));

const mockBlock: TemplateBlock = {
  id: 'block-1',
  template_id: 'template-1',
  title: 'Cover Block',
  default_content: null,
  description: null,
  block_state: 'editable',
  kind: 'cover',
  sort_order: 0,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
};

describe('CoverBlockEditor', () => {
  beforeEach(() => {
    coverOverflowMock.isOverflowing = false;
    coverOverflowMock.pageCount = 1;
  });

  it('renders with cover-editor-wrapper className when kind is cover', () => {
    const { container } = render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const coverWrapper = container.querySelector('.cover-editor-wrapper');
    expect(coverWrapper).toBeTruthy();
  });

  it('does not show overflow banner when isOverflowing is false', () => {
    coverOverflowMock.isOverflowing = false;
    render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const banner = screen.queryByText(/Esta portada generará/);
    expect(banner).toBeNull();
  });

  it('shows overflow banner with page count when isOverflowing is true', () => {
    coverOverflowMock.isOverflowing = true;
    coverOverflowMock.pageCount = 2;

    render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const banner = screen.getByText(/Esta portada generará 2 páginas en el PDF/);
    expect(banner).toBeTruthy();
  });

  it('shows singular "página" when pageCount is 1', () => {
    coverOverflowMock.isOverflowing = true;
    coverOverflowMock.pageCount = 1;

    render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const banner = screen.getByText(/Esta portada generará 1 página en el PDF/);
    expect(banner).toBeTruthy();
  });

  it('renders BlockNoteEditorPanel with correct props', () => {
    const mockOnChange = vi.fn();
    const mockUploadFile = vi.fn();

    render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={mockOnChange}
        isDark={true}
        editable={true}
        onFullscreenChange={vi.fn()}
        uploadFile={mockUploadFile}
      />
    );

    const editor = screen.getByTestId('block-note-editor');
    expect(editor).toBeTruthy();
  });

  it('displays content from block.default_content when it is JSON string', () => {
    const blockWithContent: TemplateBlock = {
      ...mockBlock,
      default_content: JSON.stringify([
        {
          type: 'paragraph',
          content: [{ type: 'text', text: 'Cover text' }],
        },
      ]),
    };

    render(
      <CoverBlockEditor
        block={blockWithContent}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const editor = screen.getByTestId('block-note-editor');
    expect(editor).toBeTruthy();
  });

  it('shows A4 page boundary dashed line', () => {
    const { container } = render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    // A4 page is 29.7cm, dashed line at that position
    const dashedLine = container.querySelector('div[style*="border-top-style: dashed"]');
    expect(dashedLine).toBeTruthy();
  });

  it('applies dark mode styles when isDark is true', () => {
    const { container } = render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={true}
        editable={true}
      />
    );

    const outerDiv = container.firstChild as HTMLElement;
    expect(outerDiv.className).toContain('dark:bg-ui-dark-bg');
  });

  it('maintains A4 dimensions (21cm x 29.7cm)', () => {
    const { container } = render(
      <CoverBlockEditor
        block={mockBlock}
        onChange={vi.fn()}
        isDark={false}
        editable={true}
      />
    );

    const coverWrapper = container.querySelector('.cover-editor-wrapper') as HTMLElement;
    const style = window.getComputedStyle(coverWrapper);

    // Check width constraint
    expect(coverWrapper.className).toContain('w-[21cm]');
    expect(coverWrapper.className).toContain('min-h-[29.7cm]');
  });
});
