import { useRef, useState } from 'react';
import { Button } from '@ceedcv-maya/shared-ui-react';
import {
  uploadThemeAsset,
  type ThemeAssetKind,
} from '../../../api/themes';
import { ApiHttpError } from '../../../api/http';
import type { Theme } from '../../../types/themes';

interface ThemeAssetsSectionProps {
  theme: Theme;
  /** Llamado tras subir un asset con el Theme actualizado. */
  onUploaded: (theme: Theme) => void;
}

const ASSET_LABELS: Record<ThemeAssetKind, string> = {
  logo: 'Logo',
  background: 'Imagen de fondo',
  watermark: 'Marca de agua',
};

const ASSET_KEYS: Record<ThemeAssetKind, keyof Theme['assets']> = {
  logo: 'logo_path',
  background: 'background_image_path',
  watermark: 'watermark_path',
};

const ACCEPT = 'image/png,image/jpeg,image/svg+xml,image/webp';
const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

export function ThemeAssetsSection({ theme, onUploaded }: ThemeAssetsSectionProps) {
  return (
    <section className="space-y-3">
      <h2 className="text-base font-semibold">Assets visuales</h2>
      <p className="text-sm text-text-muted">
        PNG, JPEG, SVG o WebP. Máximo 5&nbsp;MB. Las imágenes se sirven con autenticación
        — no son URLs públicas.
      </p>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        {(['logo', 'background', 'watermark'] as ThemeAssetKind[]).map((kind) => (
          <AssetCard
            key={kind}
            theme={theme}
            kind={kind}
            onUploaded={onUploaded}
          />
        ))}
      </div>
    </section>
  );
}

interface AssetCardProps {
  theme: Theme;
  kind: ThemeAssetKind;
  onUploaded: (theme: Theme) => void;
}

function AssetCard({ theme, kind, onUploaded }: AssetCardProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const path = theme.assets[ASSET_KEYS[kind]];
  const hasAsset = typeof path === 'string' && path !== '';

  const previewUrl = hasAsset ? path : null;

  const handleFile = async (file: File) => {
    setError(null);
    if (file.size > MAX_BYTES) {
      setError('La imagen supera 5 MB.');
      return;
    }
    setUploading(true);
    try {
      const res = await uploadThemeAsset(theme.id, kind, file);
      onUploaded(res.data);
    } catch (e) {
      if (e instanceof ApiHttpError) {
        setError(e.message || `Error HTTP ${e.status}`);
      } else {
        setError(e instanceof Error ? e.message : 'Error al subir el archivo');
      }
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  return (
    <div className="flex flex-col gap-2 rounded border border-ui-border p-3 dark:border-ui-dark-border">
      <span className="text-sm font-medium">{ASSET_LABELS[kind]}</span>

      <div className="flex h-28 items-center justify-center rounded border border-dashed border-ui-border bg-ui-body dark:border-ui-dark-border dark:bg-ui-dark-card">
        {previewUrl ? (
          <img
            src={previewUrl}
            alt={`${ASSET_LABELS[kind]} actual`}
            className="max-h-full max-w-full object-contain"
          />
        ) : (
          <span className="text-xs text-text-muted">Sin imagen</span>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT}
        className="sr-only"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void handleFile(f);
        }}
        aria-label={`Subir ${ASSET_LABELS[kind].toLowerCase()}`}
      />

      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={() => inputRef.current?.click()}
        disabled={uploading}
      >
        {uploading ? 'Subiendo…' : hasAsset ? 'Reemplazar' : 'Subir imagen'}
      </Button>

      {error && (
        <p className="text-xs text-danger-dark" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
