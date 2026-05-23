# Bloques especiales — guía rápida

> Documentación para editores y mantenedores del DMS sobre los bloques de página completa.

## Tipos de bloque

El DMS soporta cuatro tipos de bloque (`kind`), discriminados al crear cada bloque:

| Kind | Descripción | Editor | Paginación en PDF |
|------|-------------|--------|--------------------|
| `content` | Bloque de contenido normal (BlockNote) — texto, listas, tablas, imágenes. | Editor BlockNote nativo. | Fluye con el resto del documento, comparte página. |
| `cover` | Portada del documento. Lienzo A4 limpio, sin chrome del theme. | Editor BlockNote dentro de un wrapper A4. | Página propia, sin header/footer/marca de agua. |
| `blank` | Página en blanco intencional (útil para impresión a doble cara). | No editable — placeholder. | Página propia, sin header/footer/marca de agua. |
| `toc` | Índice / tabla de contenidos. | No editable — generado automáticamente. | Página propia con la lista de los headings del documento y sus números de página reales. |

Por defecto los bloques se crean con `kind = 'content'`. Para crear un bloque especial, el frontend
añade el campo `kind` en el payload de creación; el backend lo valida y persiste.

## Reglas y limitaciones

1. **El kind se fija al crear**: no se puede cambiar el tipo de un bloque tras crearlo. Si necesitas
   otro tipo, borra el bloque y crea uno nuevo.
2. **Solo un TOC por plantilla**: el FormRequest rechaza con 422 si intentas crear un segundo bloque
   `kind = 'toc'` en el mismo template.
3. **Contenido del TOC**: no se edita manualmente. Se genera automáticamente a partir de los headings
   (h1-h6) de los bloques `kind = 'content'` previos en el documento, con sus números de página reales
   calculados por WeasyPrint vía `target-counter()`.
4. **Portada sin theme**: la portada NO hereda el theme (paleta, tipografía, header/footer, marca de
   agua). Es un lienzo A4 blanco para que el usuario lo decore libremente con BlockNote (puede añadir
   imagen de fondo, logos, textos con tipografía propia, etc.).
5. **Página en blanco sin theme**: idem cover — la blank es realmente blanca, sin chrome.
6. **Portada de más de una página**: si el contenido excede 29.7cm, el PDF generará tantas páginas
   como hagan falta, todas marcadas como cover (sin chrome). El editor muestra una línea guía roja
   a la altura A4 y un banner ámbar avisando del número de páginas.
7. **Versionado**: el `kind` se versiona como atributo más del bloque en `template_version_block_layers.override_payload`.
   No se introduce nueva capa.

## Estructura típica recomendada

```
[ Portada (cover) ]
[ Índice (toc) ]
[ Bloques de contenido (content × N) ]
[ Página en blanco (blank) ]  ← si se imprime a doble cara
```

## Feature flag

```env
DMS_SPECIAL_BLOCKS_ENABLED=true   # backend (config/dms.php)
VITE_DMS_SPECIAL_BLOCKS_ENABLED=true  # frontend (.env)
```

Cuando ambos están `false` (default):
- El backend rechaza creación de bloques con `kind != 'content'` con 422 (`errors.kind`).
- El frontend oculta los 3 botones del catálogo de bloques especiales en el sidebar del editor.
- Los bloques pre-existentes con `kind` especial se siguen renderizando correctamente.

## Rollback

1. **Desactivar feature flag** en `.env`: `DMS_SPECIAL_BLOCKS_ENABLED=false`.
2. Los bloques especiales existentes permanecen en BD y siguen rindiéndose.
3. Si se quiere rollback total: soft-delete de los bloques no-content y `migrate:rollback --step=2`.

## Pipeline PDF/UA-1

- `DocumentRenderService` ordena los bloques por `sort_order`, mapea cada uno a `['kind', 'content']`
  y delega a `BlockNoteHtmlRenderer::renderDocument()`.
- El renderer:
  1. Asigna IDs determinísticos `block-{n}-h-{m}` a los headings de bloques `content`.
  2. Recoge entradas de TOC para los bloques `kind = 'toc'`.
  3. Envuelve cada bloque en `<section class="block-kind-{kind}">`.
- `resources/views/documents/render.blade.php` incluye CSS Paged Media para:
  - `@page cover` y `@page blank` con `margin: 0` y todos los slots de chrome a `content: none`.
  - `break-before/after: page` para forzar saltos.
  - Z-index masking + ancho A4 forzado en los `<section>` cover/blank para suprimir visualmente el
    overlay fijo del theme.
  - `.toc-page::before { content: target-counter(attr(data-href url), page); }` para los números
    de página del índice.
- WeasyPrint genera el PDF/UA-1 final. La blank lleva `role="presentation"` + `aria-hidden="true"`
  para no romper el tagged tree de PDF/UA.

## Tests

- **Backend unit**: `tests/Feature/Documents/RenderHtmlSnapshotTest.php` (4 tests, sin DB),
  `tests/Unit/Support/BlockNoteHtmlRendererTocTest.php` (8 tests, sin DB) — verifican el HTML
  generado y la determinismo de IDs.
- **Backend feature (requieren PostgreSQL)**: `tests/Feature/TemplateBlocks/TocUniquePerTemplateTest.php`,
  `tests/Feature/TemplateBlocks/KindPersistsInVersioningTest.php` — verifican validación 422 + versionado.
- **Frontend**: `frontend/src/__tests__/features/blocks-ui/CoverBlockEditor.test.tsx` (9 tests),
  `BlockEditor.test.tsx` (12 tests) — verifican branching y wrapper A4 con detección de overflow.

## Pendientes / mejoras futuras

- Validación PDF/UA-1 con VeraPDF en CI (no incluido en esta iteración inicial).
- Edición manual del texto del índice (decisión consciente: no soportado, edita los headings).
- Theme `cover_payload` para portadas institucionales fijas (si en el futuro algún cliente quiere
  portada gestionada por theme en lugar de por bloque editable).
