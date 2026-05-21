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
} as const;
