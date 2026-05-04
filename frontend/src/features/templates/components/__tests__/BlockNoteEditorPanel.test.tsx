import { render, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ── BlockNote mocks ─────────────────────────────────────────────────────────

// Single DOM node reused as the fake ProseMirror root so paste events can be
// dispatched on it and the useEffect listener attaches to it.
const fakePmDom = document.createElement('div');

const mockTryParseMarkdown = vi.fn().mockReturnValue([
  { id: 'p1', type: 'paragraph', props: {}, content: [{ type: 'text', text: 'hello', styles: {} }], children: [] },
]);
const mockGetCursorPos = vi.fn().mockReturnValue({
  block: { id: 'cursor', type: 'paragraph', props: {}, content: [], children: [] },
});
const mockReplaceBlocks = vi.fn();
const mockInsertBlocks = vi.fn();
const mockSetCursorPos = vi.fn();

const fakeEditor = {
  _tiptapEditor: { view: { dom: fakePmDom } },
  tryParseMarkdownToBlocks: mockTryParseMarkdown,
  getTextCursorPosition: mockGetCursorPos,
  replaceBlocks: mockReplaceBlocks,
  insertBlocks: mockInsertBlocks,
  setTextCursorPosition: mockSetCursorPos,
  document: [],
  focus: vi.fn(),
};

vi.mock('@blocknote/react', () => ({
  useCreateBlockNote: vi.fn(() => fakeEditor),
  FormattingToolbar: () => null,
}));

vi.mock('@blocknote/ariakit', () => ({
  BlockNoteView: ({ children, onChange }: any) => {
    void onChange;
    return <div data-testid="bn-view">{children}</div>;
  },
}));

vi.mock('@blocknote/ariakit/style.css', () => ({}));
vi.mock('../styles/blocknote-panel.css', () => ({}));

// ── Helper ───────────────────────────────────────────────────────────────────

function makePasteEvent(plain: string, html = ''): ClipboardEvent {
  const ev = new Event('paste', { bubbles: true, cancelable: true }) as ClipboardEvent;
  Object.defineProperty(ev, 'clipboardData', {
    value: {
      getData: (type: string) => (type === 'text/plain' ? plain : type === 'text/html' ? html : ''),
    },
  });
  return ev;
}

// ── Import target ────────────────────────────────────────────────────────────

import { BlockNoteEditorPanel, looksLikeMarkdown } from '../BlockNoteEditorPanel';

// ── looksLikeMarkdown unit tests ────────────────────────────────────────────

describe('looksLikeMarkdown', () => {
  it('detects heading + bullet list (2 markers)', () => {
    expect(looksLikeMarkdown('# Title\n- item one\n- item two')).toBe(true);
  });

  it('detects a code fence alone (counts as 2)', () => {
    expect(looksLikeMarkdown('```\nconsole.log("hi");\n```')).toBe(true);
  });

  it('detects bold + link', () => {
    expect(looksLikeMarkdown('**bold** and [a link](https://example.com)')).toBe(true);
  });

  it('does not trigger on a single bare asterisk', () => {
    expect(looksLikeMarkdown('price is 5 * 3 = 15')).toBe(false);
  });

  it('does not trigger on a single dash', () => {
    expect(looksLikeMarkdown('plain text - with dashes')).toBe(false);
  });

  it('does not trigger on plain prose', () => {
    expect(looksLikeMarkdown('This is just a normal sentence with no markup.')).toBe(false);
  });

  it('does not trigger on empty string', () => {
    expect(looksLikeMarkdown('')).toBe(false);
  });
});

// ── Paste handler integration tests ─────────────────────────────────────────

describe('BlockNoteEditorPanel paste handler', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Reset cursor to an empty paragraph (triggers replaceBlocks path).
    mockGetCursorPos.mockReturnValue({
      block: { id: 'cursor', type: 'paragraph', props: {}, content: [], children: [] },
    });
    mockTryParseMarkdown.mockReturnValue([
      { id: 'p1', type: 'paragraph', props: {}, content: [{ type: 'text', text: 'hello', styles: {} }], children: [] },
    ]);
  });

  it('intercepts Markdown paste and calls replaceBlocks on empty cursor block', async () => {
    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('# Heading\n- item one\n- item two'));
    });

    expect(mockTryParseMarkdown).toHaveBeenCalledOnce();
    expect(mockReplaceBlocks).toHaveBeenCalledOnce();
    expect(mockInsertBlocks).not.toHaveBeenCalled();
  });

  it('inserts after cursor when cursor block is non-empty', async () => {
    mockGetCursorPos.mockReturnValue({
      block: {
        id: 'cursor',
        type: 'paragraph',
        props: {},
        content: [{ type: 'text', text: 'existing text', styles: {} }],
        children: [],
      },
    });

    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('# Heading\n- item one'));
    });

    expect(mockInsertBlocks).toHaveBeenCalledOnce();
    expect(mockReplaceBlocks).not.toHaveBeenCalled();
  });

  it('does NOT intercept HTML paste (Word / Google Docs)', async () => {
    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    const richHtml = '<html><body><p>Content from Word</p></body></html>';
    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('Content from Word', richHtml));
    });

    expect(mockTryParseMarkdown).not.toHaveBeenCalled();
    expect(mockReplaceBlocks).not.toHaveBeenCalled();
  });

  it('does NOT intercept plain text without Markdown markers', async () => {
    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('Just a plain sentence without any markup.'));
    });

    expect(mockTryParseMarkdown).not.toHaveBeenCalled();
  });

  it('does NOT intercept paste when editable is false (locked block)', async () => {
    render(<BlockNoteEditorPanel initialContent={undefined} editable={false} isDark={false} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('# Heading\n- item one\n- item two'));
    });

    expect(mockTryParseMarkdown).not.toHaveBeenCalled();
  });

  it('does nothing on empty paste', async () => {
    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent(''));
    });

    expect(mockTryParseMarkdown).not.toHaveBeenCalled();
  });

  it('does nothing when tryParseMarkdownToBlocks returns empty array', async () => {
    mockTryParseMarkdown.mockReturnValueOnce([]);

    render(<BlockNoteEditorPanel initialContent={undefined} editable isDark={false} onChange={vi.fn()} />);

    await act(async () => {
      fakePmDom.dispatchEvent(makePasteEvent('# Heading\n- item one'));
    });

    expect(mockReplaceBlocks).not.toHaveBeenCalled();
    expect(mockInsertBlocks).not.toHaveBeenCalled();
  });
});
