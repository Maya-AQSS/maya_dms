import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, FieldLabel, Select, TextInput } from '@ceedcv-maya/shared-ui-react';
import { AbsoluteCanvas } from '../../../components/canvas/AbsoluteCanvas';
import type { BoxPatch } from '../../../components/canvas/canvasModel';
import { uploadCoverImage } from '../../../api/templates';
import { pageDimsMm } from '../../themes/pageSizes';
import {
  COVER_CATALOG,
  coverLabelForType,
  newCoverRegion,
  type CoverContent,
  type CoverRegion,
  type CoverRegionType,
} from './coverModel';
import { CoverRegionPreview } from './CoverRegionPreview';

interface CoverDesignEditorProps {
  value: CoverContent;
  pageSize: string;
  /** Id de la plantilla, para subir imágenes de portada. */
  templateId: string;
  onChange: (next: CoverContent) => void;
}

/**
 * Editor de DISEÑO de portada (plantilla). Coloca elementos (texto, campos
 * rellenables, fecha, nº de página) sobre `AbsoluteCanvas` en modo edición.
 * Cada cambio emite un `CoverContent` nuevo (inmutable) vía `onChange`; la
 * persistencia (autosave) la gestiona el wizard que lo embebe.
 */
export function CoverDesignEditor({ value, pageSize, templateId, onChange }: CoverDesignEditorProps) {
  const { t } = useTranslation(['templates', 'common']);
  const page = useMemo(() => pageDimsMm(pageSize), [pageSize]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [addOpen, setAddOpen] = useState(false);

  const regions = value.regions;
  const selected = regions.find((r) => r.id === selectedId) ?? null;

  const emit = (nextRegions: CoverRegion[]) => onChange({ ...value, regions: nextRegions });

  const handleAdd = (type: CoverRegionType) => {
    const maxZ = regions.reduce((m, r) => Math.max(m, r.box.z ?? 0), 0);
    const r = newCoverRegion(type, maxZ);
    if (type === 'text_placeholder') {
      const n = regions.filter((x) => x.type === 'text_placeholder').length + 1;
      r.props = { ...r.props, key: `campo_${n}`, label: t('cover.fieldLabel', { n }) };
    }
    emit([...regions, r]);
    setSelectedId(r.id);
    setAddOpen(false);
  };

  const updateBox = (id: string, patch: BoxPatch) =>
    emit(regions.map((r) => (r.id === id ? { ...r, box: { ...r.box, ...patch } } : r)));

  const updateProps = (id: string, patch: Record<string, unknown>) =>
    emit(regions.map((r) => (r.id === id ? { ...r, props: { ...r.props, ...patch } } : r)));

  const handleRemove = (id: string) => {
    emit(regions.filter((r) => r.id !== id));
    if (selectedId === id) setSelectedId(null);
  };

  const handleZUp = (id: string) => {
    const maxZ = regions.reduce((m, r) => Math.max(m, r.box.z ?? 0), 0);
    emit(regions.map((r) => (r.id === id ? { ...r, box: { ...r.box, z: maxZ + 1 } } : r)));
  };

  const handleZDown = (id: string) =>
    emit(regions.map((r) => (r.id === id ? { ...r, box: { ...r.box, z: Math.max(0, (r.box.z ?? 0) - 1) } } : r)));

  return (
    <div className="flex h-full min-h-0 flex-col">
      {/* Toolbar */}
      <div className="flex shrink-0 items-center gap-2 border-b border-ui-border bg-white px-4 py-2 dark:border-ui-dark-border dark:bg-ui-dark-card">
        <div className="relative">
          <Button type="button" variant="primary" size="sm" onClick={() => setAddOpen((o) => !o)}>
            {t('cover.addElement')}
          </Button>
          {addOpen && (
            <ul
              role="menu"
              className="absolute left-0 top-full z-50 mt-1 w-72 rounded border border-ui-border bg-white py-1 shadow-lg dark:border-ui-dark-border dark:bg-ui-dark-card"
              onMouseLeave={() => setAddOpen(false)}
            >
              {COVER_CATALOG.map((c) => (
                <li key={c.type}>
                  <button
                    type="button"
                    role="menuitem"
                    className="block w-full px-3 py-1.5 text-left text-sm hover:bg-ui-body dark:hover:bg-ui-dark-card"
                    onClick={() => handleAdd(c.type)}
                  >
                    {c.label}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
        <span className="text-xs text-text-muted">
          {t('cover.pageHint', { pageSize })}
        </span>
      </div>

      <div className="flex min-h-0 flex-1 overflow-hidden">
        <AbsoluteCanvas
          pageMm={page}
          regions={regions}
          selectedId={selectedId}
          onSelect={setSelectedId}
          onChangeBox={updateBox}
          onZUp={handleZUp}
          onZDown={handleZDown}
          onRemove={handleRemove}
          renderRegion={(r) => <CoverRegionPreview region={r as CoverRegion} />}
          labels={{
            layerUp: t('cover.layerUp'),
            layerDown: t('cover.layerDown'),
            remove: t('common:actions.delete'),
            resize: t('common:actions.resize'),
          }}
        />

        <aside className="w-72 shrink-0 overflow-y-auto border-l border-ui-border bg-white p-4 dark:border-ui-dark-border dark:bg-ui-dark-card">
          {selected ? (
            <CoverInspector
              region={selected}
              page={page}
              templateId={templateId}
              onUpdateBox={(p) => updateBox(selected.id, p)}
              onUpdateProps={(p) => updateProps(selected.id, p)}
            />
          ) : (
            <div className="space-y-3 text-sm text-text-muted">
              <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">{t('cover.inspector')}</h3>
              <p>{t('cover.inspectorEmpty')}</p>
              <ul className="list-disc space-y-1 pl-4">
                <li>{t('cover.hintDrag')}</li>
                <li><strong>↑ / ↓</strong> {t('cover.hintLayer')}</li>
                <li><strong>{t('cover.fillableFields')}</strong> {t('cover.hintFields')}</li>
              </ul>
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}

/* ─── Inspector ────────────────────────────────────────────────────────── */

function CoverInspector({
  region,
  page,
  templateId,
  onUpdateBox,
  onUpdateProps,
}: {
  region: CoverRegion;
  page: { width: number; height: number };
  templateId: string;
  onUpdateBox: (patch: BoxPatch) => void;
  onUpdateProps: (patch: Record<string, unknown>) => void;
}) {
  const { t } = useTranslation('templates');
  const p = region.props ?? {};
  const box = region.box;

  return (
    <div className="space-y-4 text-sm">
      <header>
        <h3 className="text-base font-semibold">{coverLabelForType(region.type)}</h3>
      </header>

      <section className="space-y-2">
        <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">{t('cover.positionSize')}</h4>
        <div className="grid grid-cols-2 gap-2">
          <NumField label={t('cover.x')} value={box.x} min={0} max={page.width} onChange={(v) => onUpdateBox({ x: v })} />
          <NumField label={t('cover.y')} value={box.y} min={0} max={page.height} onChange={(v) => onUpdateBox({ y: v })} />
          <NumField label={t('cover.width')} value={box.w} min={1} max={page.width} onChange={(v) => onUpdateBox({ w: v })} />
          <NumField label={t('cover.height')} value={box.h} min={1} max={page.height} onChange={(v) => onUpdateBox({ h: v })} />
        </div>
      </section>

      <section className="space-y-2">
        <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">{t('cover.properties')}</h4>
        {region.type === 'text' && (
          <>
            <Labeled label={t('cover.content')}>
              <TextInput value={(p.text as string) ?? ''} onChange={(e) => onUpdateProps({ text: e.target.value })} />
            </Labeled>
            <TextProps p={p} onUpdateProps={onUpdateProps} />
          </>
        )}
        {region.type === 'text_placeholder' && (
          <>
            <Labeled label={t('cover.keyLabel')}>
              <TextInput
                value={(p.key as string) ?? ''}
                onChange={(e) => onUpdateProps({ key: e.target.value.replace(/\s+/g, '_') })}
              />
            </Labeled>
            <Labeled label={t('cover.labelLabel')}>
              <TextInput value={(p.label as string) ?? ''} onChange={(e) => onUpdateProps({ label: e.target.value })} />
            </Labeled>
            <Labeled label={t('cover.defaultText')}>
              <TextInput value={(p.defaultText as string) ?? ''} onChange={(e) => onUpdateProps({ defaultText: e.target.value })} />
            </Labeled>
            <TextProps p={p} onUpdateProps={onUpdateProps} />
          </>
        )}
        {region.type === 'date' && (
          <>
            <Labeled label={t('cover.format')}>
              <Select value={(p.format as string) ?? 'long'} onChange={(e) => onUpdateProps({ format: e.target.value })}>
                <option value="short">{t('cover.dateShort')}</option>
                <option value="long">{t('cover.dateLong')}</option>
              </Select>
            </Labeled>
            <TextProps p={p} onUpdateProps={onUpdateProps} />
          </>
        )}
        {region.type === 'page_number' && (
          <>
            <Labeled label={t('cover.format')}>
              <Select value={(p.format as string) ?? 'page-of-pages'} onChange={(e) => onUpdateProps({ format: e.target.value })}>
                <option value="page">{t('cover.pageN')}</option>
                <option value="page-of-pages">{t('cover.pageNofM')}</option>
              </Select>
            </Labeled>
            <TextProps p={p} onUpdateProps={onUpdateProps} />
          </>
        )}
        {region.type === 'image' && (
          <CoverImageEditor p={p} templateId={templateId} onUpdateProps={onUpdateProps} />
        )}
      </section>
    </div>
  );
}

/** Subida + ajustes de una imagen/logo de portada. */
function CoverImageEditor({
  p,
  templateId,
  onUpdateProps,
}: {
  p: Record<string, unknown>;
  templateId: string;
  onUpdateProps: (patch: Record<string, unknown>) => void;
}) {
  const { t } = useTranslation('templates');
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const srcUrl = (p.srcUrl as string) || null;

  const handleFile = async (file: File) => {
    setUploading(true);
    setError(null);
    try {
      const res = await uploadCoverImage(templateId, file);
      onUpdateProps({ src: res.src, srcUrl: res.url });
      if (fileRef.current) fileRef.current.value = '';
    } catch (e) {
      setError(e instanceof Error ? e.message : t('cover.uploadError'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-3">
      {srcUrl && (
        <div className="h-24 w-full overflow-hidden rounded border border-ui-border bg-ui-body">
          <img src={srcUrl} alt={t('common:preview')} className="h-full w-full object-contain" />
        </div>
      )}
      <Labeled label={t('cover.uploadImage')}>
        <input
          ref={fileRef}
          type="file"
          accept="image/png,image/jpeg,image/webp"
          disabled={uploading}
          onChange={(e) => {
            const f = e.currentTarget.files?.[0];
            if (f) void handleFile(f);
          }}
          className="block w-full text-xs file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-primary file:px-2 file:py-1 file:text-xs file:text-white disabled:opacity-50"
        />
      </Labeled>
      {uploading && <p className="text-xs text-text-muted">{t('common:uploading')}</p>}
      {error && <p className="rounded bg-danger/10 p-2 text-xs text-danger-dark">{error}</p>}
      <Labeled label={t('cover.altText')}>
        <TextInput value={(p.alt as string) ?? ''} onChange={(e) => onUpdateProps({ alt: e.target.value })} />
      </Labeled>
      <Labeled label={t('cover.fit')}>
        <Select value={(p.objectFit as string) ?? 'contain'} onChange={(e) => onUpdateProps({ objectFit: e.target.value })}>
          <option value="contain">{t('cover.fitContain')}</option>
          <option value="cover">{t('cover.fitCover')}</option>
          <option value="fill">{t('cover.fitFill')}</option>
        </Select>
      </Labeled>
    </div>
  );
}

/** Campos comunes de tipografía (tamaño, color, alineación, negrita). */
function TextProps({
  p,
  onUpdateProps,
}: {
  p: Record<string, unknown>;
  onUpdateProps: (patch: Record<string, unknown>) => void;
}) {
  const { t } = useTranslation(['templates', 'common']);
  return (
    <>
      <NumField label={t('templates:cover.sizePt')} value={(p.size as number) ?? 12} min={6} max={72} onChange={(v) => onUpdateProps({ size: v })} />
      <Labeled label={t('templates:cover.color')}>
        <input
          type="color"
          value={(p.color as string) ?? '#1a1a1a'}
          onChange={(e) => onUpdateProps({ color: e.target.value })}
          className="h-8 w-full cursor-pointer rounded border border-ui-border"
        />
      </Labeled>
      <Labeled label={t('templates:cover.alignment')}>
        <Select value={(p.align as string) ?? 'left'} onChange={(e) => onUpdateProps({ align: e.target.value })}>
          <option value="left">{t('common:align.left')}</option>
          <option value="center">{t('common:align.center')}</option>
          <option value="right">{t('common:align.right')}</option>
        </Select>
      </Labeled>
      <Labeled label={t('templates:cover.weight')}>
        <Select value={(p.weight as string) ?? 'normal'} onChange={(e) => onUpdateProps({ weight: e.target.value })}>
          <option value="normal">{t('templates:cover.weightNormal')}</option>
          <option value="bold">{t('templates:cover.weightBold')}</option>
        </Select>
      </Labeled>
    </>
  );
}

function Labeled({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
      {children}
    </div>
  );
}

function NumField({
  label,
  value,
  min,
  max,
  onChange,
}: {
  label: string;
  value: number;
  min?: number;
  max?: number;
  onChange: (v: number) => void;
}) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
      <TextInput
        type="number"
        value={String(value)}
        min={min}
        max={max}
        onChange={(e) => {
          const v = Number.parseFloat(e.target.value);
          if (!Number.isNaN(v)) onChange(v);
        }}
      />
    </div>
  );
}
