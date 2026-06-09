import { commonResources, notificationResources, deepMerge } from '@ceedcv-maya/shared-i18n-react';

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

export const NAMESPACES = ['auth', 'common', 'nav', 'themes', 'documents', 'templates', 'notifications'] as const;
export type Namespace = (typeof NAMESPACES)[number];

// Cada namespace fusiona el canon shared (actions, status, pagination,
// feedback, auth, errors, tables, nav, dashboard, etc.) con sus strings
// locales. Esto permite que `useTranslation('documents')` + `t('actions.close')`
// resuelva contra el canon — sin necesidad de `useTranslation(['ns','common'])`.
// El orden del spread garantiza que el local SIEMPRE gana sobre el canon en
// caso de colisión.
// `deepMerge` del paquete shared infiere su genérico del canon y exige que el
// segundo argumento sea `Partial<typeof canon>`. Aquí fusionamos el canon
// `common` con namespaces de forma distinta (themes/documents/templates/…) que
// no comparten propiedades con él, por lo que TS los rechaza (TS2559/TS2345).
// Este wrapper conserva el merge en runtime y tipa el resultado como la
// intersección canon ∩ local, sin tocar el paquete publicado.
const mergeNs = <C extends object, L extends object>(canon: C, local: L): C & L =>
  (deepMerge as unknown as (a: C, b: L) => C & L)(canon, local);

const baseEs = commonResources.es.common;
const baseVa = commonResources.va.common;
const baseEn = commonResources.en.common;

export const resources = {
  es: {
    auth: mergeNs(baseEs, esAuth),
    common: mergeNs(baseEs, esCommon),
    nav: mergeNs(baseEs, esNav),
    themes: mergeNs(baseEs, esThemes),
    documents: mergeNs(baseEs, esDocuments),
    templates: mergeNs(baseEs, esTemplates),
    notifications: notificationResources.es.notifications,
  },
  va: {
    auth: mergeNs(baseVa, vaAuth),
    common: mergeNs(baseVa, vaCommon),
    nav: mergeNs(baseVa, vaNav),
    themes: mergeNs(baseVa, vaThemes),
    documents: mergeNs(baseVa, vaDocuments),
    templates: mergeNs(baseVa, vaTemplates),
    notifications: notificationResources.va.notifications,
  },
  en: {
    auth: mergeNs(baseEn, enAuth),
    common: mergeNs(baseEn, enCommon),
    nav: mergeNs(baseEn, enNav),
    themes: mergeNs(baseEn, enThemes),
    documents: mergeNs(baseEn, enDocuments),
    templates: mergeNs(baseEn, enTemplates),
    notifications: notificationResources.en.notifications,
  },
} as const;
