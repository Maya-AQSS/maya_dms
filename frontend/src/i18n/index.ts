import { createAppI18n } from '@ceedcv-maya/shared-i18n-react';
import { NAMESPACES, resources, type SupportedLocale } from './resources';

const { i18n, changeLocale: changeAppLocale } = createAppI18n(resources, NAMESPACES);

export function changeLocale(locale: SupportedLocale): Promise<unknown> {
  return changeAppLocale(locale);
}

export { DEFAULT_LOCALE, SUPPORTED_LOCALES } from './resources';
export type { SupportedLocale } from './resources';
export default i18n;
