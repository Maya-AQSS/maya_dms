import { useEffect, useRef, useState } from 'react';
import {
  IndexBlockEditor,
  parseIndexConfig,
  type IndexConfig,
  type IndexSelectableBlock,
} from '../../templates/components/IndexBlockEditor';

interface IndexFillEditorProps {
  /** Bloques seleccionables (id = template_block_id en documentos). */
  blocks: IndexSelectableBlock[];
  /** Id del propio bloque índice (se excluye de la lista). */
  currentBlockId: string | null;
  /** Config actual del bloque (content del documento; o default_content). */
  value: unknown;
  /** Persiste la config (debounce interno). El wizard hace el PUT del bloque. */
  onPersist: (config: IndexConfig) => void | Promise<void>;
}

/**
 * Editor de RELLENO del índice en un documento. A diferencia de usar
 * `IndexBlockEditor` controlado contra el servidor (los checkboxes no
 * respondían hasta el round-trip), aquí llevamos estado LOCAL para feedback
 * inmediato y persistimos con debounce — mismo patrón que `CoverFillEditor`.
 */
export function IndexFillEditor({ blocks, currentBlockId, value, onPersist }: IndexFillEditorProps) {
  const [config, setConfig] = useState<IndexConfig>(() => parseIndexConfig(value));

  const onPersistRef = useRef(onPersist);
  onPersistRef.current = onPersist;
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const configRef = useRef(config);
  configRef.current = config;
  const pendingRef = useRef(false);

  // Vuelca el guardado pendiente al desmontar/cambiar de bloque (antes se
  // limpiaba el timer y se perdía la última selección).
  const flush = () => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    if (pendingRef.current) {
      pendingRef.current = false;
      void onPersistRef.current(configRef.current);
    }
  };
  useEffect(() => () => { flush(); }, []);

  const update = (next: IndexConfig) => {
    setConfig(next);
    pendingRef.current = true;
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { flush(); }, 800);
  };

  return (
    <IndexBlockEditor
      blocks={blocks}
      currentBlockId={currentBlockId}
      value={config}
      onChange={update}
    />
  );
}
