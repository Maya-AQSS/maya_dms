import esCommon from './locales/es/common.json';
import vaCommon from './locales/va/common.json';

export const SUPPORTED_LOCALES = ['es', 'va'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const DEFAULT_LOCALE: SupportedLocale = 'es';

export const NAMESPACES = ['common'] as const;
export type Namespace = (typeof NAMESPACES)[number];

export const resources = {
  es: { common: esCommon },
  va: { common: vaCommon },
} as const;
