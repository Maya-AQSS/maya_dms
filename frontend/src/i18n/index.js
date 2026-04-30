import { createI18n } from '@maya/shared-i18n-react';
import { NAMESPACES, resources } from './resources';
const i18n = createI18n(resources, NAMESPACES);
export function changeLocale(locale) {
    return i18n.changeLanguage(locale);
}
export { DEFAULT_LOCALE, SUPPORTED_LOCALES } from './resources';
export default i18n;
