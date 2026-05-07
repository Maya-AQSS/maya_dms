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

  it('fullscreen applies sidebar-aware CSS class, not fixed inset-0', () => {
    const { container } = render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />,
    );
    const panel = container.querySelector('.maya-bn-panel') as HTMLElement;
    expect(panel.classList.contains('maya-bn-panel--fullscreen')).toBe(false);

    fireEvent.click(screen.getByRole('button', { name: /pantalla completa/i }));
    expect(panel.classList.contains('maya-bn-panel--fullscreen')).toBe(true);
    expect(panel.classList.contains('fixed')).toBe(false);
    expect(panel.classList.contains('inset-0')).toBe(false);
  });

  it('Escape key exits fullscreen', () => {
    render(<BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />);
    const btn = screen.getByRole('button', { name: /pantalla completa/i });
    fireEvent.click(btn);
    expect(btn.getAttribute('aria-pressed')).toBe('true');

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(btn.getAttribute('aria-pressed')).toBe('false');
  });

  it('exiting fullscreen restores original layout classes', () => {
    const { container } = render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />,
    );
    const panel = container.querySelector('.maya-bn-panel') as HTMLElement;
    const originalClasses = panel.className;

    const btn = screen.getByRole('button', { name: /pantalla completa/i });
    fireEvent.click(btn);
    fireEvent.click(btn);

    expect(panel.className).toBe(originalClasses);
  });

  it('fullscreen uses CSS class for positioning only, no inline styles that shift layout', () => {
    const { container } = render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} />,
    );
    const panel = container.querySelector('.maya-bn-panel') as HTMLElement;
    fireEvent.click(screen.getByRole('button', { name: /pantalla completa/i }));

    expect(panel.classList.contains('maya-bn-panel--fullscreen')).toBe(true);
    expect(panel.classList.contains('overflow-hidden')).toBe(true);
    // Positioning must come from the CSS class, not inline styles or Tailwind utilities
    expect(panel.style.position).toBe('');
    expect(panel.style.top).toBe('');
    expect(panel.style.left).toBe('');
    expect(panel.classList.contains('top-0')).toBe(false);
    expect(panel.classList.contains('inset-0')).toBe(false);
  });

  it('calls onFullscreenChange(true) when entering fullscreen', () => {
    const onFullscreenChange = vi.fn();
    render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} onFullscreenChange={onFullscreenChange} />,
    );
    fireEvent.click(screen.getByRole('button', { name: /pantalla completa/i }));
    expect(onFullscreenChange).toHaveBeenCalledTimes(1);
    expect(onFullscreenChange).toHaveBeenCalledWith(true);
  });

  it('calls onFullscreenChange(false) when exiting fullscreen via button then Escape', () => {
    const onFullscreenChange = vi.fn();
    render(
      <BlockNoteEditorPanel initialContent={null} editable={true} isDark={false} onFullscreenChange={onFullscreenChange} />,
    );
    const btn = screen.getByRole('button', { name: /pantalla completa/i });
    fireEvent.click(btn);
    fireEvent.click(btn);
    expect(onFullscreenChange).toHaveBeenNthCalledWith(1, true);
    expect(onFullscreenChange).toHaveBeenNthCalledWith(2, false);

    fireEvent.click(btn);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onFullscreenChange).toHaveBeenLastCalledWith(false);
  });
});
