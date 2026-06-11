import { useEffect, useState, type ReactNode, type CSSProperties, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { Button, PageTitle } from '@ceedcv-maya/shared-ui-react'

interface Props {
  /** Título grande centrado (mismo lugar en plantilla y documento). */
  title: ReactNode
  /** Subtítulo bajo el título. Default: i18n `navigation.preview`. */
  subtitle?: ReactNode
  /** Acción al pulsar la flecha "back". */
  onBack: () => void
  /** Etiqueta accesible del back button (cambia según contexto). */
  backLabel?: string
  /** Línea de info pequeña (autor · visibilidad · fecha límite, etc.). */
  metaInfo?: ReactNode
  /**
   * Toolbar de la vista. Incluye badges de estado/versión/favorito y los
   * botones de acción específicos del contexto (Historial, Editar, etc.).
   * El botón de "Pantalla completa" lo añade este layout al final.
   */
  actions?: ReactNode
  /** Si true, el wrapper es un overlay fixed (para modales). Default: false (modo página). */
  asOverlay?: boolean
  /** Ref opcional sobre el área del header (PageTitle) para calcular offsets de paneles fijos. */
  headerRef?: RefObject<HTMLDivElement | null>
  /** Sidebar opcional (usado para comentarios/info) que ocupa el 35% de la pantalla. */
  sidebar?: ReactNode
  children: ReactNode
  viewMode: string
}

function FullscreenIcon({ on }: { on: boolean }) {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {on ? (
        <>
          <polyline points="4 14 10 14 10 20" />
          <polyline points="20 10 14 10 14 4" />
          <line x1="14" y1="10" x2="21" y2="3" />
          <line x1="3" y1="21" x2="10" y2="14" />
        </>
      ) : (
        <>
          <polyline points="15 3 21 3 21 9" />
          <polyline points="9 21 3 21 3 15" />
          <line x1="21" y1="3" x2="14" y2="10" />
          <line x1="3" y1="21" x2="10" y2="14" />
        </>
      )}
    </svg>
  )
}

/**
 * Layout de previsualización tipo "papel" usado tanto por documentos como por
 * plantillas. Garantiza un mismo header (PageTitle + meta info + toolbar) y un
 * mismo contenedor de artículo. Cada vista pasa sus propios `actions` y el
 * contenido del `<article>` como `children`.
 *
 * - Modo página (`asOverlay=false`): se mete dentro de `<main>` del AppLayout
 *   y respeta sus paddings/bg automáticamente.
 * - Modo overlay (`asOverlay=true`): se posiciona fijo a la derecha del aside
 *   usando la variable CSS `--sidebar-w` (la fija AppLayout). El fondo es
 *   `bg-app-gradient` para que el aspecto sea idéntico al modo página.
 *
 * En modo "Pantalla completa" el artículo se expande al 100% del ancho
 * disponible (sin tapar el aside). Atajos: Esc (sale o cierra) y F.
 */
export function PaperPreviewLayout({
  title,
  subtitle,
  onBack,
  // Sin default: PageTitle resuelve la etiqueta vía i18n (`actions.back`).
  backLabel,
  metaInfo,
  actions,
  asOverlay = false,
  headerRef,
  sidebar,
  children,
  viewMode
}: Props) {
  const { t } = useTranslation('common')
  const [isFullscreen, setIsFullscreen] = useState(false)
  const resolvedSubtitle = subtitle ?? t('navigation.preview')

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        if (isFullscreen) setIsFullscreen(false)
        else if (asOverlay) onBack()
      } else if (e.key === 'f' || e.key === 'F') {
        const tag = (document.activeElement?.tagName || '').toLowerCase()
        const editable = (document.activeElement as HTMLElement | null)?.isContentEditable
        if (tag !== 'input' && tag !== 'textarea' && !editable) {
          setIsFullscreen((v) => !v)
        }
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [isFullscreen, asOverlay, onBack])

  // Clase del wrapper:
  // - Modo página: fluye dentro de <main>.
  // - Modo overlay: fijo, respeta aside via media query (md:left-[var(--sidebar-w)]).
  // - Modo fullscreen: igual que overlay pero z más alto y article 100%.
  // Nota: usamos Tailwind arbitrary-value con CSS var. La var --sidebar-w la
  // expone AppLayout en su raíz, y se hereda por cascada al overlay aunque sea
  // `position: fixed` (los CSS vars cascadan a través de la herencia DOM).
  const baseOverlayClass =
    'fixed inset-y-0 right-0 left-0 md:left-[var(--sidebar-w,0px)] overflow-y-auto bg-app-gradient p-4 sm:p-6 md:p-8 animate-in fade-in'

  const wrapperClass = isFullscreen
    ? `${baseOverlayClass} z-[70]`
    : asOverlay
      ? `${baseOverlayClass} z-[60]`
      : 'min-h-screen'

  // Tamaño del artículo:
  // - Modo normal (760px) idéntico al documento.
  // - Modo fullscreen: 100% del contenedor disponible (sin aside) con un
  //   max razonable para legibilidad en pantallas muy anchas.
  const articleStyle: CSSProperties = isFullscreen
  ? {
      width: '100%',
      maxWidth: 'none',
      minHeight: 'calc(100vh - 14rem)',
      padding: viewMode === 'themed' ? 0 : '64px 96px',
    }
  : {
      maxWidth: '760px',
      minHeight: 'calc(100vh - 12rem)',
      padding: viewMode === 'themed' ? 0 : '56px 72px',
    }

  const fullscreenButton = (
    <Button
      type="button"
      variant="outline"
      size="sm"
      onClick={() => setIsFullscreen((v) => !v)}
      title={isFullscreen ? t('navigation.exitFullscreenHint') : t('navigation.fullscreen')}
    >
      <span className="inline-flex items-center gap-1.5">
        <FullscreenIcon on={isFullscreen} />
        {isFullscreen ? t('navigation.reduce') : t('navigation.fullscreenShort')}
      </span>
    </Button>
  )

  return (
    <div className={wrapperClass}>
      <div ref={headerRef}>
      <PageTitle
        title={title}
        subtitle={resolvedSubtitle}
        onBack={isFullscreen ? () => setIsFullscreen(false) : onBack}
        backLabel={isFullscreen ? t('navigation.exitFullscreen') : backLabel}
        meta={
          <div className="space-y-3">
            {metaInfo}
            <div className="flex items-center justify-center gap-2 flex-wrap">
              {actions}
              {fullscreenButton}
            </div>
          </div>
        }
      />
      </div>

      <div className={sidebar ? 'flex flex-row flex-nowrap items-start min-h-screen relative overflow-visible gap-8' : ''}>
        <div className={sidebar ? 'shrink-0' : ''}>
          <article
            className="mx-auto bg-white dark:bg-ui-dark-card shadow-xl preview-content"
            style={articleStyle}
          >
            {children}
          </article>
        </div>

        {sidebar && (
          <div
            className="flex-1 min-w-0 sticky top-24 self-start z-30"
            style={{ minWidth: '320px', height: 'calc(100vh - 120px)' }}
          >
            <div className="h-full flex flex-col bg-white dark:bg-ui-dark-card shadow-xl rounded-xl overflow-hidden border border-ui-border dark:border-ui-dark-border">
              {sidebar}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
