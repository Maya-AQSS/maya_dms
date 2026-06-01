/**
 * Compatibility shim — the historical name `BlockNoteEditorPanel` is now
 * served by the new TipTap-backed `MayaEditorPanel`. Re-exported here so
 * existing call sites do not need to be touched at once.
 *
 * To force the legacy BlockNote-backed editor (rollback path), set
 * `VITE_EDITOR_BACKEND=blocknote` and import from
 * `./BlockNoteEditorPanel.legacy` directly. The legacy file is preserved
 * for that purpose.
 */
export { MayaEditorPanel as BlockNoteEditorPanel } from './MayaEditorPanel';
