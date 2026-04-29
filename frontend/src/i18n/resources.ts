import esCommon from'./locales/es/common.json';
import esNav from'./locales/es/nav.json';

import vaCommon from'./locales/va/common.json';
import vaNav from'./locales/va/nav.json';

export const SUPPORTED_LOCALES = ['es','va'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale ='es';

export const NAMESPACES = ['common','nav'] as const;
export type Namespace = (typeof NAMESPACES)[number];

export const resources = {
 es: { common: esCommon, nav: esNav },
 va: { common: vaCommon, nav: vaNav },
} as const;
