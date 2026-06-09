import type { FC, ReactNode } from 'react';
import { withTranslation } from 'react-i18next';
import { ErrorBoundary } from '@ceedcv-maya/shared-ui-react';

/**
 * The `ErrorBoundary` exported from `@ceedcv-maya/shared-ui-react` is the raw
 * class component whose `Props extends WithTranslation`, so it requires the
 * `t`/`i18n`/`tReady` props injected by i18next. Consuming it bare triggers
 * TS2769 ("No overload matches this call").
 *
 * This wrapper applies `withTranslation('common')` once so every consumer can
 * use it like a plain boundary with just `children` (and an optional `fallback`).
 */
export const ErrorBoundaryWrapper = withTranslation('common')(ErrorBoundary) as FC<{
  children: ReactNode;
  fallback?: ReactNode;
}>;
