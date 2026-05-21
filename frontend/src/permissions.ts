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
} as const;

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
