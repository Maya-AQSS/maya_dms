import { createI18n } from'@maya/shared-i18n-react';
import { NAMESPACES, resources, type SupportedLocale } from'./resources';

const i18n = createI18n(resources, NAMESPACES);

export function changeLocale(locale: SupportedLocale): Promise<unknown> {
 return i18n.changeLanguage(locale);
}

export { DEFAULT_LOCALE, SUPPORTED_LOCALES } from'./resources';
export type { SupportedLocale } from'./resources';
export default i18n;
