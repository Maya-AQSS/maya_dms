import { useEffect, useMemo, useRef, useState } from 'react';
import { FieldLabel, TextInput } from '@ceedcv-maya/shared-ui-react';
import { AbsoluteCanvas } from '../../../components/canvas/AbsoluteCanvas';
import { pageDimsMm } from '../../themes/pageSizes';
import {
  parseCoverContent,
  parseCoverFill,
  type CoverFill,
  type CoverRegion,
} from './coverModel';
import { CoverRegionPreview } from './CoverRegionPreview';

interface CoverFillEditorProps {
  /** Geometría de la portada (default_content del bloque de plantilla). */
  geometry: unknown;
  /** Valores actuales (content del bloque de documento). */
  value: unknown;
  /** Tamaño de página (del tema); por defecto A4. */
  pageSize?: string;
  /** Si false, sólo lectura (documento publicado). */
  editable?: boolean;
  /** Persiste el cover-fill (debounce interno). El wizard hace el PUT del bloque. */
  onPersist: (fill: CoverFill) => void | Promise<void>;
}

/**
 * Editor de RELLENO de portada (documento). La geometría queda BLOQUEADA
 * (`AbsoluteCanvas` con `editable={false}`): el usuario sólo introduce el texto
 * de los *placeholders* mediante un panel de campos. El lienzo muestra la
 * portada en vivo con los valores aplicados.
 */
export function CoverFillEditor({ geometry, value, pageSize = 'A4', editable = true, onPersist }: CoverFillEditorProps) {
  const cover = useMemo(() => parseCoverContent(geometry, pageSize), [geometry, pageSize]);
  const page = useMemo(() => pageDimsMm(cover.page.size), [cover.page.size]);
  const [values, setValues] = useState<Record<string, string>>(() => parseCoverFill(value));

  const placeholders = useMemo(
    () => cover.regions.filter((r) => r.type === 'text_placeholder'),
    [cover.regions],
  );

  // Autosave con debounce 1000ms: el wizard recibe el cover-fill y hace el PUT.
  const onPersistRef = useRef(onPersist);
  onPersistRef.current = onPersist;
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const valuesRef = useRef(values);
  valuesRef.current = values;
  const pendingRef = useRef(false);

  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved'>('idle');
  const saveStateRef = useRef(setSaveState);
  saveStateRef.current = setSaveState;

  // Vuelca el guardado pendiente AHORA (en blur o al desmontar/cambiar de bloque):
  // antes se limpiaba el timer al desmontar y se perdía lo escrito.
  const flush = () => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    if (pendingRef.current) {
      pendingRef.current = false;
      saveStateRef.current('saving');
      Promise.resolve(onPersistRef.current({ kind: 'cover-fill', values: valuesRef.current }))
        .then(() => saveStateRef.current('saved'))
        .catch(() => saveStateRef.current('idle'));
    }
  };
  useEffect(() => () => { flush(); }, []); // flush al desmontar (no clear)

  const setValue = (key: string, text: string) => {
    const next = { ...values, [key]: text };
    setValues(next);
    pendingRef.current = true;
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { flush(); }, 1000);
  };

  const noop = () => {};

  return (
    <div className="flex h-full min-h-0 flex-col md:flex-row">
      {/* Preview bloqueado */}
      <div className="min-h-0 flex-1 overflow-auto">
        <AbsoluteCanvas
          pageMm={page}
          regions={cover.regions}
          selectedId={null}
          onSelect={noop}
          onChangeBox={noop}
          onZUp={noop}
          onZDown={noop}
          onRemove={noop}
          editable={false}
          renderRegion={(r) => {
            const region = r as CoverRegion;
            const key = typeof region.props.key === 'string' ? region.props.key : '';
            const fillValue = region.type === 'text_placeholder' ? values[key] : undefined;
            return <CoverRegionPreview region={region} fillValue={fillValue} />;
          }}
        />
      </div>

      {/* Panel de campos rellenables */}
      <aside className="w-full shrink-0 overflow-y-auto border-t border-ui-border bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-card md:w-80 md:border-l md:border-t-0">
        <div className="mb-3 flex items-center justify-between gap-2">
          <h3 className="text-sm font-bold uppercase tracking-widest text-text-secondary">Campos de la portada</h3>
          {saveState !== 'idle' && (
            <span className="text-xs text-text-muted">
              {saveState === 'saving' ? 'Guardando…' : 'Guardado ✓'}
            </span>
          )}
        </div>
        {placeholders.length === 0 ? (
          <p className="text-sm text-text-muted">Esta portada no tiene campos rellenables.</p>
        ) : (
          <div className="space-y-3">
            {placeholders.map((ph) => {
              const key = typeof ph.props.key === 'string' ? ph.props.key : '';
              const label = typeof ph.props.label === 'string' ? ph.props.label : 'Campo';
              return (
                <div key={ph.id}>
                  <FieldLabel>{label}</FieldLabel>
                  <TextInput
                    value={values[key] ?? ''}
                    disabled={!editable || key === ''}
                    placeholder={(ph.props.defaultText as string) ?? ''}
                    onChange={(e) => setValue(key, e.target.value)}
                    onBlur={() => flush()}
                  />
                </div>
              );
            })}
          </div>
        )}
        {!editable && (
          <p className="mt-4 text-xs text-text-muted">El documento no es editable en su estado actual.</p>
        )}
      </aside>
    </div>
  );
}
