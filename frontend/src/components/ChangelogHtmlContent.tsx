import { EditorContentHtml } from '@ceedcv-maya/shared-editor-react';

const VARIANT_CLASS = {
  compact:
    'maya-editor-content text-xs text-text-secondary dark:text-text-dark-secondary leading-snug [&_p]:m-0 [&_p+p]:mt-1 [&_ul]:my-0 [&_ol]:my-0 [&_li]:my-0',
  default:
    'maya-editor-content text-sm text-text-primary dark:text-text-dark-primary leading-relaxed [&_p]:m-0 [&_p+p]:mt-2 [&_ul]:my-1 [&_ol]:my-1',
} as const;

type Props = {
  html: string;
  variant?: keyof typeof VARIANT_CLASS;
};

/** Changelog guardado (texto plano legacy o HTML de MayaEditor). */
export function ChangelogHtmlContent({ html, variant = 'default' }: Props) {
  const trimmed = html.trim();
  if (trimmed === '') {
    return null;
  }

  return <EditorContentHtml html={trimmed} className={VARIANT_CLASS[variant]} />;
}
