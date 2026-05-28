import type { Template } from '../../types/templates';

/**
 * Borrador inicial (sin publicación previa): abrir editor.
 * Borrador o revisión sobre una versión ya publicada: preview (descartar, enviar a validar, etc.).
 */
export function shouldOpenTemplateEditorFromList(
  template: Pick<Template, 'status' | 'latest_published_version_id'>,
  isOwner: boolean,
): boolean {
  return isOwner && template.status === 'draft' && !template.latest_published_version_id;
}
