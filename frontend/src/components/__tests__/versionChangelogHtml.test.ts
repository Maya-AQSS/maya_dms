import { describe, expect, it } from 'vitest';
import {
  normalizeChangelogHtml,
  plainTextFromChangelogHtml,
} from '../versionChangelogHtml';

describe('versionChangelogHtml', () => {
  it('plainTextFromChangelogHtml strips empty paragraph', () => {
    expect(plainTextFromChangelogHtml('<p></p>')).toBe('');
    expect(plainTextFromChangelogHtml('<p><br></p>')).toBe('');
  });

  it('plainTextFromChangelogHtml keeps visible text', () => {
    expect(plainTextFromChangelogHtml('<p><strong>Nota</strong></p>')).toBe('Nota');
  });

  it('normalizeChangelogHtml trims', () => {
    expect(normalizeChangelogHtml('  <p>x</p>  ')).toBe('<p>x</p>');
  });
});
