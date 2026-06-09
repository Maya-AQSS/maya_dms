import type { CSSProperties } from 'react';
import type { CoverRegion, TextAlign } from './coverModel';

/**
 * Render WYSIWYG de una región de portada dentro del lienzo. Espeja lo que el
 * `CoverRenderService` (PHP) dibuja en el PDF: texto, placeholder, fecha y
 * nº de página. `fillValue` (modo relleno de documento) sustituye el texto del
 * placeholder por el valor introducido.
 */
export function CoverRegionPreview({
  region,
  fillValue,
}: {
  region: CoverRegion;
  fillValue?: string;
}) {
  const p = region.props ?? {};
  const textStyle: CSSProperties = {
    fontSize: `${(p.size as number) ?? 12}pt`,
    color: (p.color as string) ?? '#1a1a1a',
    textAlign: ((p.align as TextAlign) ?? 'left'),
    fontWeight: (p.weight as string) === 'bold' ? 700 : 400,
    width: '100%',
    height: '100%',
    overflow: 'hidden',
    lineHeight: 1.25,
  };

  switch (region.type) {
    case 'text':
      return <div style={textStyle}>{(p.text as string) || ' '}</div>;
    case 'text_placeholder': {
      const value = fillValue ?? (p.defaultText as string) ?? '';
      const isEmpty = value.trim() === '';
      return (
        <div
          style={{
            ...textStyle,
            color: isEmpty ? '#9ca3af' : textStyle.color,
            fontStyle: isEmpty ? 'italic' : 'normal',
            outline: '1px dashed #c4b5fd',
            outlineOffset: -1,
          }}
        >
          {isEmpty ? `⟨${(p.label as string) || 'Campo'}⟩` : value}
        </div>
      );
    }
    case 'date':
      return (
        <div style={textStyle}>
          {(p.format as string) === 'long' ? '1 de enero de 2026' : '01/01/2026'}
        </div>
      );
    case 'page_number':
      return (
        <div style={textStyle}>
          {(p.format as string) === 'page-of-pages' ? 'Página 1 de 1' : 'Página 1'}
        </div>
      );
    case 'image': {
      const url = (p.srcUrl as string) || null;
      return (
        <div
          style={{
            width: '100%',
            height: '100%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            overflow: 'hidden',
            opacity: (p.opacity as number) ?? 1,
            // Solo el placeholder vacío muestra el recuadro guía; con imagen va
            // a sangre para poder usarse como fondo de portada completa.
            background: url ? undefined : 'rgba(0,0,0,0.03)',
            border: url ? undefined : '1px solid rgba(0,0,0,0.08)',
          }}
        >
          {url ? (
            <img
              src={url}
              alt={(p.alt as string) ?? ''}
              style={{
                width: '100%',
                height: '100%',
                objectFit: ((p.objectFit as string) ?? 'contain') as CSSProperties['objectFit'],
              }}
            />
          ) : (
            <span style={{ fontSize: 11, color: '#9ca3af' }}>Imagen</span>
          )}
        </div>
      );
    }
    default:
      return null;
  }
}
