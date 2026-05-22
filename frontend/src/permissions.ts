/** Slugs de permisos de DocuCEED (maya-dms), alineados con maya_authorization. */
export const DMS_PERMISSIONS = {
  login: 'dms.login',
  index: 'dms.index',
  show: 'dms.show',
  dashboardUpdate: 'dms.dashboard.update',
  processIndex: 'process.index',
  processShow: 'process.show',
  processCreate: 'process.create',
  processUpdate: 'process.update',
  processDelete: 'process.delete',
  templateIndex: 'template.index',
  templateShow: 'template.show',
  templateCreate: 'template.create',
  templateUpdate: 'template.update',
  templateDelete: 'template.delete',
  templateReview: 'template.review',
  templateAssignReview: 'template.assign-review',
  templateVersion: 'template.version',
  templateClone: 'template.clone',
  templateHistoryView: 'template.history.view',
  documentIndex: 'document.index',
  documentShow: 'document.show',
  documentCreate: 'document.create',
  documentUpdate: 'document.update',
  documentDelete: 'document.delete',
  documentReview: 'document.review',
  documentVersion: 'document.version',
  documentClone: 'document.clone',
  documentHistoryView: 'document.history.view',
  blockIndex: 'block.index',
  blockShow: 'block.show',
  blockCreate: 'block.create',
  blockUpdate: 'block.update',
  blockDelete: 'block.delete',
  commentBlockCreate: 'comment-block.create',
  commentBlockDelete: 'comment-block.delete',
  themeIndex: 'theme.index',
  themeShow: 'theme.show',
  themeCreate: 'theme.create',
  themeClone: 'theme.clone',
  themeUpdate: 'theme.update',
  themeDelete: 'theme.delete',
} as const;

/**
 * Sección Themes en navegación y gestión (no el selector del wizard de plantilla).
 * Requiere theme.index y theme.show; el profesor no los tiene.
 */
export function canManageThemesCatalog(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.themeIndex) && hasPermission(DMS_PERMISSIONS.themeShow);
}

/** `block.index` / `block.show` requieren además mutación de plantilla o documento (catálogo). */
export function canAccessBlockCatalog(hasPermission: (slug: string) => boolean): boolean {
  const hasMutation =
    hasPermission(DMS_PERMISSIONS.templateCreate) ||
    hasPermission(DMS_PERMISSIONS.templateUpdate) ||
    hasPermission(DMS_PERMISSIONS.documentCreate) ||
    hasPermission(DMS_PERMISSIONS.documentUpdate);

  return hasMutation;
}

export function canListBlocks(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockIndex) && canAccessBlockCatalog(hasPermission);
}

export function canShowBlockDetail(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockShow) && canAccessBlockCatalog(hasPermission);
}

export function canMutateTemplateBlocks(hasPermission: (slug: string) => boolean): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.templateCreate) || hasPermission(DMS_PERMISSIONS.templateUpdate)
  );
}

export function canMutateDocumentBlocks(hasPermission: (slug: string) => boolean): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.documentCreate) || hasPermission(DMS_PERMISSIONS.documentUpdate)
  );
}

export function canCreateTemplateBlock(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockCreate) && canMutateTemplateBlocks(hasPermission);
}

export function canUpdateTemplateBlock(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockUpdate) && canMutateTemplateBlocks(hasPermission);
}

export function canDeleteTemplateBlock(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockDelete) && canMutateTemplateBlocks(hasPermission);
}

export function canUpdateDocumentBlock(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockUpdate) && canMutateDocumentBlocks(hasPermission);
}

export function canDeleteDocumentBlock(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockDelete) && canMutateDocumentBlocks(hasPermission);
}

/** Crear comentarios en bloque; el contexto (creador/revisor) lo valida la API. */
export function canCreateBlockComment(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.commentBlockCreate);
}

export function canDeleteBlockComment(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.commentBlockDelete);
}

/** Editar texto: solo el autor del comentario (sin slug en catálogo). */
export function canEditOwnBlockComment(
  profileId: string | undefined,
  authorId: string | undefined,
): boolean {
  return !!profileId && !!authorId && profileId === authorId;
}
