import esAuth from './locales/es/auth.json';
import esCommon from './locales/es/common.json';
import esNav from './locales/es/nav.json';

import vaAuth from './locales/va/auth.json';
import vaCommon from './locales/va/common.json';
import vaNav from './locales/va/nav.json';

import enAuth from './locales/en/auth.json';
import enCommon from './locales/en/common.json';
import enNav from './locales/en/nav.json';

export const SUPPORTED_LOCALES = ['es', 'va', 'en'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

export const NAMESPACES = ['auth', 'common', 'nav'] as const;
export type Namespace = (typeof NAMESPACES)[number];

export const resources = {
  es: { auth: esAuth, common: esCommon, nav: esNav },
  va: { auth: vaAuth, common: vaCommon, nav: vaNav },
  en: { auth: enAuth, common: enCommon, nav: enNav },
} as const;
