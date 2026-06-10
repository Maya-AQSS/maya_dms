import { fetchDocumentVersionDetail } from '../../../api/documents';
import { fetchTemplateVersion } from '../../../api/templates';
import {
  documentBlocksToComparable,
  mapSnapshotDocumentBlocks,
  templateSnapshotToComparable,
} from './mapSnapshotBlocks';
import type { ComparableBlock } from './versionBlockCompare';

export type VersionEntityType = 'document' | 'template';

export type ComparableVersion = {
  versionNumber: number;
  blocks: ComparableBlock[];
};

/**
 * Carga los bloques de una versión publicada normalizados para comparar.
 * Para documentos resuelve `snapshot_data.blocks`; para plantillas usa
 * `blocks_snapshot`. Ambos snapshots ya vienen reconstruidos por el backend
 * (resolutores de capas append-only), gated por `viewHistory`.
 */
export async function loadVersionComparable(
  entityType: VersionEntityType,
  entityId: string,
  versionId: string,
): Promise<ComparableVersion> {
  if (entityType === 'document') {
    const detail = await fetchDocumentVersionDetail(entityId, versionId);
    const snap =
      detail.snapshot_data && typeof detail.snapshot_data === 'object'
        ? (detail.snapshot_data as Record<string, unknown>)
        : {};
    return {
      versionNumber: detail.version_number,
      blocks: documentBlocksToComparable(mapSnapshotDocumentBlocks(snap.blocks)),
    };
  }

  const detail = await fetchTemplateVersion(versionId);
  return {
    versionNumber: detail.version_number,
    blocks: templateSnapshotToComparable(detail.blocks_snapshot ?? []),
  };
}
