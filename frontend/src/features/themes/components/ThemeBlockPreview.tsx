import { useTranslation } from 'react-i18next';
import type { ThemeLayoutRegion } from '../../../types/themes';

/**
 * Render WYSIWYG de un bloque dentro del lienzo del editor. Refleja lo que el
 * Blade dibuja en el PDF (texto, imagen, nº de página, fecha) para que el
 * editor sea fiel. Las clases `theme-grid-slot*` viven en `theme-grid.css`.
 */
export function ThemeBlockPreview({ region }: { region: ThemeLayoutRegion }) {
  const { t } = useTranslation('themes');
  const p = region.props ?? {};
  switch (region.type) {
    case 'content_slot':
      return (
        <div className="theme-grid-slot theme-grid-slot--content">
          <span>{(p.label as string) ?? t('blocks.documentContent')}</span>
          <small>{t('editor.blocksPlaceholder')}</small>
        </div>
      );
    case 'text':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--text"
          style={{
            fontSize: `${(p.size as number) ?? 9}pt`,
            color: (p.color as string) ?? '#333',
            textAlign: ((p.align as string) ?? 'left') as React.CSSProperties['textAlign'],
          }}
        >
          {(p.text as string) ?? 'Texto'}
        </div>
      );
    case 'image': {
      const url = (p.srcUrl as string) || null;
      return (
        <div
          className={`theme-grid-slot theme-grid-slot--image${url ? '' : ' theme-grid-slot--image-empty'}`}
          style={{
            opacity: (p.opacity as number) ?? 1,
            transform: `rotate(${(p.rotate as number) ?? 0}deg)`,
          }}
        >
          {url ? (
            <img
              src={url}
              alt={(p.alt as string) ?? ''}
              style={{
                width: '100%',
                height: '100%',
                objectFit: ((p.objectFit as string) ?? 'contain') as React.CSSProperties['objectFit'],
              }}
            />
          ) : (
            <span className="text-text-muted">{t('editor.imageEmpty')}</span>
          )}
        </div>
      );
    }
    case 'page_number':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--meta"
          style={{ textAlign: ((p.align as string) ?? 'right') as React.CSSProperties['textAlign'] }}
        >
          {(p.format as string) === 'page-of-pages' ? t('editor.pageNumberOfTotal') : t('editor.pageNumber')}
        </div>
      );
    case 'date':
      return (
        <div
          className="theme-grid-slot theme-grid-slot--meta"
          style={{ textAlign: ((p.align as string) ?? 'left') as React.CSSProperties['textAlign'] }}
        >
          {(p.format as string) === 'long' ? '1 de enero de 2026' : '01/01/2026'}
        </div>
      );
    default:
      return (
        <div className="theme-grid-slot theme-grid-slot--legacy">
          <span>{region.type}</span>
          <small>(bloque legacy)</small>
        </div>
      );
  }
}
