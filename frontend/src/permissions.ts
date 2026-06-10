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

export function canCreateTheme(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.themeCreate);
}

/**
 * Editar un theme: creador (con acceso al catálogo) o `theme.update` (jefe de estudios+).
 */
export function canUpdateTheme(
  hasPermission: (slug: string) => boolean,
  profileId: string | undefined,
  createdBy: string | undefined,
): boolean {
  if (!profileId || !createdBy) {
    return hasPermission(DMS_PERMISSIONS.themeUpdate);
  }

  if (profileId === createdBy) {
    return canCreateTheme(hasPermission);
  }

  return hasPermission(DMS_PERMISSIONS.themeUpdate);
}

export function canCloneTheme(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.themeClone);
}

/**
 * Eliminar un theme: creador o `theme.delete` (admin).
 * Los themes de sistema (`is_system`) nunca se borran — espeja {@see ThemePolicy::delete}
 * del backend, que bloquea el borrado incluso para creador/admin.
 */
export function canDeleteTheme(
  hasPermission: (slug: string) => boolean,
  profileId: string | undefined,
  createdBy: string | undefined,
  isSystem?: boolean,
): boolean {
  if (isSystem) {
    return false;
  }

  if (profileId && createdBy && profileId === createdBy) {
    return true;
  }

  return hasPermission(DMS_PERMISSIONS.themeDelete);
}

/** Contexto de plantilla padre para permisos de bloques (alineado con TemplatePolicy). */
export type TemplateBlockPermissionContext = {
  created_by?: string;
  status?: string;
};

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

/**
 * Listar bloques de una plantilla concreta: `block.index` + vista sobre el padre.
 * El creador de la plantilla no necesita slugs de catálogo de plantilla.
 */
export function canListTemplateBlocks(
  hasPermission: (slug: string) => boolean,
  profileId: string | undefined,
  template?: TemplateBlockPermissionContext,
): boolean {
  if (!hasPermission(DMS_PERMISSIONS.blockIndex)) {
    return false;
  }

  if (!template) {
    return canAccessBlockCatalog(hasPermission);
  }

  if (profileId && template.created_by && profileId === template.created_by) {
    return true;
  }

  return (
    canAccessBlockCatalog(hasPermission) ||
    hasPermission(DMS_PERMISSIONS.templateShow) ||
    hasPermission(DMS_PERMISSIONS.documentCreate)
  );
}

export function canShowBlockDetail(hasPermission: (slug: string) => boolean): boolean {
  return hasPermission(DMS_PERMISSIONS.blockShow) && canAccessBlockCatalog(hasPermission);
}

export function canMutateTemplateBlocks(
  hasPermission: (slug: string) => boolean,
  profileId?: string,
  template?: TemplateBlockPermissionContext,
): boolean {
  if (
    profileId &&
    template?.created_by &&
    profileId === template.created_by &&
    template.status &&
    (template.status === 'draft' || template.status === 'rejected')
  ) {
    return true;
  }

  return (
    hasPermission(DMS_PERMISSIONS.templateCreate) || hasPermission(DMS_PERMISSIONS.templateUpdate)
  );
}

export function canMutateDocumentBlocks(hasPermission: (slug: string) => boolean): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.documentCreate) || hasPermission(DMS_PERMISSIONS.documentUpdate)
  );
}

export function canCreateTemplateBlock(
  hasPermission: (slug: string) => boolean,
  profileId?: string,
  template?: TemplateBlockPermissionContext,
): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.blockCreate) &&
    canMutateTemplateBlocks(hasPermission, profileId, template)
  );
}

export function canUpdateTemplateBlock(
  hasPermission: (slug: string) => boolean,
  profileId?: string,
  template?: TemplateBlockPermissionContext,
): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.blockUpdate) &&
    canMutateTemplateBlocks(hasPermission, profileId, template)
  );
}

export function canDeleteTemplateBlock(
  hasPermission: (slug: string) => boolean,
  profileId?: string,
  template?: TemplateBlockPermissionContext,
): boolean {
  return (
    hasPermission(DMS_PERMISSIONS.blockDelete) &&
    canMutateTemplateBlocks(hasPermission, profileId, template)
  );
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
