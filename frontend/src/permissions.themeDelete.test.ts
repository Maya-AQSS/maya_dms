import { describe, expect, it } from 'vitest';
import { canDeleteTheme } from './permissions';

const allow = () => true;
const deny = () => false;

describe('canDeleteTheme', () => {
  it('permite al creador borrar su theme', () => {
    expect(canDeleteTheme(deny, 'u1', 'u1')).toBe(true);
  });

  it('permite a un admin con theme.delete borrar themes ajenos', () => {
    expect(canDeleteTheme(allow, 'u2', 'u1')).toBe(true);
  });

  it('niega a un no-creador sin theme.delete', () => {
    expect(canDeleteTheme(deny, 'u2', 'u1')).toBe(false);
  });

  it('niega borrar un theme de sistema aunque sea el creador', () => {
    expect(canDeleteTheme(deny, 'u1', 'u1', true)).toBe(false);
  });

  it('niega borrar un theme de sistema aunque tenga theme.delete', () => {
    expect(canDeleteTheme(allow, 'u2', 'u1', true)).toBe(false);
  });
});
