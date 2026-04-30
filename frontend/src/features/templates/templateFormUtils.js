export function isoToDatetimeLocal(iso) {
    if (!iso)
        return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime()))
        return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
export function datetimeLocalToIso(s) {
    const t = s.trim();
    if (!t)
        return null;
    const d = new Date(t);
    if (Number.isNaN(d.getTime()))
        return null;
    return d.toISOString();
}
export function templateEditIsDirty(t, fields) {
    const descDraftNorm = fields.description.trim();
    const descStoredNorm = (t.description ?? '').trim();
    if (descDraftNorm !== descStoredNorm)
        return true;
    if (fields.name.trim() !== t.name)
        return true;
    if (fields.visibilityLevel !== t.visibility_level)
        return true;
    if (fields.deliveryDeadline !== isoToDatetimeLocal(t.delivery_deadline))
        return true;
    if (fields.studyTypeId.trim() !== (t.study_type_id ?? ''))
        return true;
    if (fields.studyId.trim() !== (t.study_id ?? ''))
        return true;
    if (fields.moduleId.trim() !== (t.module_id ?? ''))
        return true;
    if (fields.teamId.trim() !== (t.team_id ?? ''))
        return true;
    if (fields.status !== t.status)
        return true;
    if ((Number.parseInt(fields.reviewStages, 10) || 0) !== t.review_stages)
        return true;
    if (fields.reviewMode !== t.review_mode)
        return true;
    return false;
}
