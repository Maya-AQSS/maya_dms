import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';

const { createMock } = vi.hoisted(() => {
  const createMock = vi.fn(() => ({ document: [], focus: vi.fn() }));
  return { createMock };
});

vi.mock('@blocknote/core', () => ({
  BlockNoteEditor: { create: createMock },
}));

vi.mock('@blocknote/react', () => ({
  FormattingToolbar: () => null,
}));

vi.mock('@blocknote/ariakit', () => ({
  BlockNoteView: ({ children }: any) => <div data-testid="bn-view">{children}</div>,
}));

vi.mock('@blocknote/ariakit/style.css', () => ({}));
vi.mock('../../styles/blocknote-panel.css', () => ({}));

vi.mock('../../../utils/blockNoteRepair', () => ({
  repairBlockNoteBlocks: (b: any) => b,
}));

vi.mock('../../documents/lib/normalizeBlockContent', () => ({
  normalizeBlockContentForEditor: () => [],
}));

import { BlockNoteEditorPanel } from '../BlockNoteEditorPanel';

describe('BlockNoteEditorPanel', () => {
  beforeEach(() => {
    createMock.mockClear();
  });

  it('creates the editor only once per component instance', () => {
    const { rerender } = render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />,
    );
    // Multiple rerenders with the same instance should not create new editors
    rerender(<BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />);
    rerender(<BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />);
    expect(createMock).toHaveBeenCalledTimes(1);
  });

  it('creates a new editor when the component remounts (key change)', () => {
    const { rerender } = render(
      <BlockNoteEditorPanel key="a" initialContent={null} editable={true} isDark={false} />,
    );
    rerender(
      <BlockNoteEditorPanel key="b" initialContent={null} editable={true} isDark={false} />,
    );
    expect(createMock).toHaveBeenCalledTimes(2);
  });

  it('toggles fullscreen when the fullscreen button is clicked', () => {
    render(<BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />);
    const btn = screen.getByRole('button', { name: /pantalla completa/i });
    expect(btn.getAttribute('aria-pressed')).toBe('false');
    fireEvent.click(btn);
    expect(btn.getAttribute('aria-pressed')).toBe('true');
    fireEvent.click(btn);
    expect(btn.getAttribute('aria-pressed')).toBe('false');
  });

  it('does not render fullscreen button when editable=false', () => {
    render(<BlockNoteEditorPanel initialContent={null} editable={false} isDark={false} />);
    expect(screen.queryByRole('button', { name: /pantalla completa/i })).toBeNull();
  });
});
