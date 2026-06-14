import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, FieldLabel, Select, TextInput } from '@ceedcv-maya/shared-ui-react';
import type { ThemeBlockType, ThemeLayoutRegion } from '../../../types/themes';
import { ingestThemeImageUrl, uploadThemeImage } from '../../../api/themes';
import { labelForType } from '../themeBlocks';

/** Parche de geometría en mm. */
export type BoxPatch = { x?: number; y?: number; w?: number; h?: number };

interface BlockInspectorProps {
  region: ThemeLayoutRegion;
  themeId: string;
  /** Dimensiones de página en mm (para límites de los campos de posición). */
  page: { width: number; height: number };
  onUpdateProps: (patch: Record<string, unknown>) => void;
  onUpdateBox: (patch: BoxPatch) => void;
}

export function BlockInspector({ region, themeId, page, onUpdateProps, onUpdateBox }: BlockInspectorProps) {
  const { t } = useTranslation('themes');
  const p = region.props ?? {};
  const box = region.box;

  return (
    <div className="space-y-4 text-sm">
      <header>
        <h3 className="text-base font-semibold">{labelForType(region.type, t)}</h3>
        <p className="text-xs text-text-muted">ID: {region.id}</p>
      </header>

      {box && (
        <section className="space-y-2">
          <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">
            {t('inspector.positionSize')}
          </h4>
          <div className="grid grid-cols-2 gap-2">
            <NumField label={t('inspector.x')} value={box.x} min={0} max={page.width} step={1} onChange={(v) => onUpdateBox({ x: v })} />
            <NumField label={t('inspector.y')} value={box.y} min={0} max={page.height} step={1} onChange={(v) => onUpdateBox({ y: v })} />
            <NumField label={t('inspector.width')} value={box.w} min={1} max={page.width} step={1} onChange={(v) => onUpdateBox({ w: v })} />
            <NumField label={t('inspector.height')} value={box.h} min={1} max={page.height} step={1} onChange={(v) => onUpdateBox({ h: v })} />
          </div>
          <p className="text-xs text-text-muted">
            {t('inspector.layer')} <strong>{box.z ?? 1}</strong> {t('inspector.layerHint')}
          </p>
        </section>
      )}

      <section className="space-y-2">
        <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary">{t('inspector.properties')}</h4>
        {renderTypeFields(region.type, p, onUpdateProps, t, themeId)}
      </section>
    </div>
  );
}

export function EmptyInspector() {
  const { t } = useTranslation('themes');
  return (
    <div className="space-y-3 text-sm text-text-muted">
      <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">{t('inspector.title')}</h3>
      <p>
        {t('inspector.emptySelect')}{' '}
        <strong>{t('editor.addBlock')}</strong>.
      </p>
      <ul className="list-disc space-y-1 pl-4">
        <li>{t('inspector.emptyHintDrag')}</li>
        <li>{t('inspector.emptyHintResize')}</li>
        <li><strong>↑ / ↓</strong> {t('inspector.emptyHintLayer')}</li>
        <li>{t('inspector.emptyHintOverlap')}</li>
      </ul>
    </div>
  );
}

function renderTypeFields(
  type: ThemeBlockType,
  p: Record<string, unknown>,
  onChange: (patch: Record<string, unknown>) => void,
  t: (key: string) => string,
  themeId?: string,
): React.ReactNode {
  switch (type) {
    case 'text':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-text">{t('inspector.content')}</FieldLabel>
            <TextInput
              id="blk-text"
              value={(p.text as string) ?? ''}
              onChange={(e) => onChange({ text: e.target.value })}
            />
          </div>
          <NumField label={t('inspector.sizePt')} value={(p.size as number) ?? 9} min={6} max={48} onChange={(v) => onChange({ size: v })} />
          <div>
            <FieldLabel htmlFor="blk-color">{t('inspector.color')}</FieldLabel>
            <input
              id="blk-color"
              type="color"
              value={(p.color as string) ?? '#333333'}
              onChange={(e) => onChange({ color: e.target.value })}
              className="h-8 w-full cursor-pointer rounded border border-ui-border"
            />
          </div>
          <AlignField value={(p.align as string) ?? 'left'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'image':
      return themeId ? (
        <ImageBlockEditor props={p} themeId={themeId} onChange={onChange} />
      ) : (
        <p className="text-xs text-text-muted">{t('inspector.noThemeId')}</p>
      );
    case 'page_number':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-pn-fmt">{t('inspector.format')}</FieldLabel>
            <Select id="blk-pn-fmt" value={(p.format as string) ?? 'page-of-pages'} onChange={(e) => onChange({ format: e.target.value })}>
              <option value="page">{t('editor.pageNumber')}</option>
              <option value="page-of-pages">{t('editor.pageNumberOfTotal')}</option>
            </Select>
          </div>
          <AlignField value={(p.align as string) ?? 'right'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'date':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-date-fmt">{t('inspector.format')}</FieldLabel>
            <Select id="blk-date-fmt" value={(p.format as string) ?? 'short'} onChange={(e) => onChange({ format: e.target.value })}>
              <option value="short">{t('inspector.dateShort')}</option>
              <option value="long">{t('inspector.dateLong')}</option>
            </Select>
          </div>
          <AlignField value={(p.align as string) ?? 'left'} onChange={(v) => onChange({ align: v })} />
        </>
      );
    case 'content_slot':
      return (
        <>
          <div>
            <FieldLabel htmlFor="blk-label">{t('inspector.editorLabel')}</FieldLabel>
            <TextInput id="blk-label" value={(p.label as string) ?? ''} onChange={(e) => onChange({ label: e.target.value })} />
          </div>
          <p className="text-xs text-text-muted">
            {t('inspector.contentSlotHint')}
          </p>
        </>
      );
    default:
      return <p className="text-xs text-text-muted">{t('inspector.noEditableProps')}</p>;
  }
}

interface NumFieldProps {
  label: string;
  value: number;
  min?: number;
  max?: number;
  step?: number;
  onChange: (v: number) => void;
}

export function NumField({ label, value, min, max, step, onChange }: NumFieldProps) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
      <TextInput
        type="number"
        value={String(value)}
        min={min}
        max={max}
        step={step}
        onChange={(e) => {
          const v = step ? Number.parseFloat(e.target.value) : Number.parseInt(e.target.value, 10);
          if (!Number.isNaN(v)) onChange(v);
        }}
      />
    </div>
  );
}

function AlignField({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const { t } = useTranslation(['themes', 'common']);
  return (
    <div>
      <FieldLabel htmlFor="blk-align">{t('themes:editor.alignment')}</FieldLabel>
      <Select id="blk-align" value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="left">{t('common:align.left')}</option>
        <option value="center">{t('common:align.center')}</option>
        <option value="right">{t('common:align.right')}</option>
      </Select>
    </div>
  );
}

interface ImageBlockEditorProps {
  props: Record<string, unknown>;
  themeId: string;
  onChange: (patch: Record<string, unknown>) => void;
}

function ImageBlockEditor({ props, themeId, onChange }: ImageBlockEditorProps) {
  const { t } = useTranslation(['themes', 'common']);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [urlInput, setUrlInput] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const srcUrl = (props.srcUrl as string) || null;

  const handleFileUpload = async (file: File) => {
    setUploading(true);
    setError(null);
    try {
      const response = await uploadThemeImage(themeId, file);
      onChange({ src: response.src, srcUrl: response.url });
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (e) {
      setError(e instanceof Error ? e.message : t('themes:inspector.uploadError'));
    } finally {
      setUploading(false);
    }
  };

  const handleUrlIngest = async () => {
    if (!urlInput.trim()) return;
    setUploading(true);
    setError(null);
    try {
      const response = await ingestThemeImageUrl(themeId, urlInput);
      onChange({ src: response.src, srcUrl: response.url });
      setUrlInput('');
    } catch (e) {
      setError(e instanceof Error ? e.message : t('themes:inspector.urlIngestError'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="space-y-3">
      {srcUrl && (
        <div>
          <FieldLabel>{t('common:preview')}</FieldLabel>
          <div className="h-24 w-full overflow-hidden rounded border border-ui-border bg-ui-body">
            <img src={srcUrl} alt={t('common:preview')} className="h-full w-full object-cover" />
          </div>
        </div>
      )}

      <div>
        <FieldLabel htmlFor="blk-img-file">{t('themes:inspector.uploadImage')}</FieldLabel>
        <input
          ref={fileInputRef}
          id="blk-img-file"
          type="file"
          accept="image/*"
          disabled={uploading}
          onChange={(e) => {
            const file = e.currentTarget.files?.[0];
            if (file) handleFileUpload(file);
          }}
          className="block w-full text-xs file:mr-2 file:cursor-pointer file:rounded file:border-0 file:bg-primary file:px-2 file:py-1 file:text-xs file:text-white file:disabled:bg-text-muted disabled:opacity-50"
        />
      </div>

      <div>
        <FieldLabel htmlFor="blk-img-url">{t('themes:inspector.orUseUrl')}</FieldLabel>
        <div className="flex gap-1">
          <input
            id="blk-img-url"
            type="url"
            value={urlInput}
            onChange={(e) => setUrlInput(e.target.value)}
            disabled={uploading}
            placeholder={t('themes:inspector.urlPlaceholder')}
            className="flex-1 rounded border border-ui-border px-2 py-1 text-xs disabled:opacity-50"
          />
          <Button
            type="button"
            variant="primary"
            size="sm"
            loading={uploading}
            disabled={!urlInput.trim() || uploading}
            onClick={handleUrlIngest}
            className="shrink-0"
          >
            {t('themes:inspector.useUrlBtn')}
          </Button>
        </div>
      </div>

      {error && <p className="rounded bg-danger/10 p-2 text-xs text-danger-dark">{error}</p>}

      <div>
        <FieldLabel htmlFor="blk-img-alt">{t('themes:inspector.altText')}</FieldLabel>
        <TextInput id="blk-img-alt" value={(props.alt as string) ?? ''} onChange={(e) => onChange({ alt: e.target.value })} />
      </div>

      <NumField
        label={t('themes:inspector.opacity')}
        value={(props.opacity as number) ?? 1}
        min={0}
        max={1}
        step={0.05}
        onChange={(v) => onChange({ opacity: v })}
      />

      <NumField
        label={t('themes:inspector.rotation')}
        value={(props.rotate as number) ?? 0}
        min={-180}
        max={180}
        onChange={(v) => onChange({ rotate: v })}
      />

      <div>
        <FieldLabel htmlFor="blk-img-fit">{t('themes:inspector.imageFit')}</FieldLabel>
        <Select id="blk-img-fit" value={(props.objectFit as string) ?? 'contain'} onChange={(e) => onChange({ objectFit: e.target.value })}>
          <option value="contain">{t('themes:inspector.fitContain')}</option>
          <option value="cover">{t('themes:inspector.fitCover')}</option>
          <option value="stretch">{t('themes:inspector.fitStretch')}</option>
        </Select>
      </div>
    </div>
  );
}
