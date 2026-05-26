import { commonResources, deepMerge } from '@ceedcv-maya/shared-i18n-react';

import esAuth from './locales/es/auth.json';
import esCommon from './locales/es/common.json';
import esNav from './locales/es/nav.json';
import esThemes from './locales/es/themes.json';
import esDocuments from './locales/es/documents.json';
import esTemplates from './locales/es/templates.json';

import vaAuth from './locales/va/auth.json';
import vaCommon from './locales/va/common.json';
import vaNav from './locales/va/nav.json';
import vaThemes from './locales/va/themes.json';
import vaDocuments from './locales/va/documents.json';
import vaTemplates from './locales/va/templates.json';

import enAuth from './locales/en/auth.json';
import enCommon from './locales/en/common.json';
import enNav from './locales/en/nav.json';
import enThemes from './locales/en/themes.json';
import enDocuments from './locales/en/documents.json';
import enTemplates from './locales/en/templates.json';

export const SUPPORTED_LOCALES = ['es', 'va', 'en'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

export const NAMESPACES = ['auth', 'common', 'nav', 'themes', 'documents', 'templates'] as const;
export type Namespace = (typeof NAMESPACES)[number];

// Cada namespace fusiona el canon shared (actions, status, pagination,
// feedback, auth, errors, tables, nav, dashboard, etc.) con sus strings
// locales. Esto permite que `useTranslation('documents')` + `t('actions.close')`
// resuelva contra el canon — sin necesidad de `useTranslation(['ns','common'])`.
// El orden del spread garantiza que el local SIEMPRE gana sobre el canon en
// caso de colisión.
const baseEs = commonResources.es.common;
const baseVa = commonResources.va.common;
const baseEn = commonResources.en.common;

export const resources = {
  es: {
    auth: deepMerge(baseEs, esAuth),
    common: deepMerge(baseEs, esCommon),
    nav: deepMerge(baseEs, esNav),
    themes: deepMerge(baseEs, esThemes),
    documents: deepMerge(baseEs, esDocuments),
    templates: deepMerge(baseEs, esTemplates),
  },
  va: {
    auth: deepMerge(baseVa, vaAuth),
    common: deepMerge(baseVa, vaCommon),
    nav: deepMerge(baseVa, vaNav),
    themes: deepMerge(baseVa, vaThemes),
    documents: deepMerge(baseVa, vaDocuments),
    templates: deepMerge(baseVa, vaTemplates),
  },
  en: {
    auth: deepMerge(baseEn, enAuth),
    common: deepMerge(baseEn, enCommon),
    nav: deepMerge(baseEn, enNav),
    themes: deepMerge(baseEn, enThemes),
    documents: deepMerge(baseEn, enDocuments),
    templates: deepMerge(baseEn, enTemplates),
  },
} as const;
