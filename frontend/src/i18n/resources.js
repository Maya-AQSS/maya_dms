import esCommon from './locales/es/common.json';
import esNav from './locales/es/nav.json';
import vaCommon from './locales/va/common.json';
import vaNav from './locales/va/nav.json';
export const SUPPORTED_LOCALES = ['es', 'va'];
export const DEFAULT_LOCALE = 'es';
export const NAMESPACES = ['common', 'nav'];
export const resources = {
    es: { common: esCommon, nav: esNav },
    va: { common: vaCommon, nav: vaNav },
};
