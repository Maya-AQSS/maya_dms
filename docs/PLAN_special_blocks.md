# Plan: Bloques especiales de página completa (cover, blank, toc)

> **Rama**: `feature/special-blocks` (creada desde `feature/images`)
> **Fecha**: 2026-05-23
> **Decisiones validadas por**: council (Architect + Skeptic + Pragmatist + Critic)
> **Feature flag**: `DMS_SPECIAL_BLOCKS_ENABLED`

## Contexto y decisiones cerradas

Este plan implementa cuatro tipos de bloques en **maya_dms**: `content` (defecto, hoy), `cover` (portada), `blank` (página en blanco), y `toc` (índice generado). Las decisiones ya validadas son:

1. **Modelo**: columna `kind` (string, default `'content'`) en `template_blocks` y `document_blocks` con enum PHP `BlockKind { Content, Cover, Blank, Toc }`. **NO** se introduce capa de "sections" — sigue siendo lista plana ordenada por `sort_order`.
2. **Versionado**: `kind` viaja en `override_payload` del pivote `template_version_block_layers` (sin cambios de estructura).
3. **Render backend**: cada `kind` genera HTML marcado diferente. `cover` y `blank` suprimen **TODO** el chrome del theme (header/footer/marca de agua/paleta/tipografía). `toc` es derivado en render-time (no se persiste contenido).
4. **Frontend**: catálogo con 3 tipos nuevos en sidebar + editor branching por kind. La portada usa BlockNote dentro de wrapper A4 con overflow visible y banner de aviso permisivo (opción b).
5. **Tests**: snapshot HTML, PDF/UA-1 validado con VeraPDF, e2e de flujo completo.
6. **Feature flag**: `dms.special_blocks_enabled` para rollback seguro sin migración inversa.

## Fases

### Fase 1 — Backend: modelo y versionado (8h)

1. **Crear enum BlockKind** — `backend/app/Enums/BlockKind.php`
   - Acción: enum string-backed con casos `Content='content', Cover='cover', Blank='blank', Toc='toc'`. Añadir método estático `values(): array` que devuelva `array_column(self::cases(), 'value')`.
   - Por qué: type-safety para discriminación en render y validaciones.
   - Riesgo: bajo.

2. **Migración: añadir kind a template_blocks** — `backend/database/migrations/2026_05_23_000001_add_kind_to_template_blocks.php`
   - Acción: `$table->string('kind', 16)->default('content')->after('sort_order');` + `$table->index(['template_id', 'kind']);`. En `down()`: `$table->dropIndex([...]); $table->dropColumn('kind');`.
   - Por qué: persistencia del tipo de bloque en plantilla.
   - Riesgo: bajo — default garantiza retrocompat.

3. **Migración: añadir kind a document_blocks** — `backend/database/migrations/2026_05_23_000002_add_kind_to_document_blocks.php`
   - Acción: idem template_blocks, después de `sort_order`.
   - Por qué: permite herencia del tipo de la plantilla (DocumentBlock mirror 1:1).
   - Riesgo: bajo.

4. **TemplateBlock model** — `backend/app/Models/TemplateBlock.php` (líneas 24-40)
   - Acción: añadir `'kind'` a `$fillable`. Añadir cast `'kind' => BlockKind::class` en `casts()`.
   - Por qué: asignación masiva segura y casting automático de enum.
   - Riesgo: bajo.

5. **DocumentBlock model** — `backend/app/Models/DocumentBlock.php` (líneas 25-44)
   - Acción: idem TemplateBlock.
   - Riesgo: bajo.

6. **TemplateBlockDto** — `backend/app/DTOs/TemplateBlocks/TemplateBlockDto.php`
   - Acción: añadir propiedad `public readonly BlockKind $kind` en constructor. En `fromModel()`: `kind: $m->kind` (el cast Eloquent ya devuelve el enum).
   - Por qué: exposición de `kind` en la capa DTO.
   - Riesgo: bajo.

7. **DocumentBlockDto** — `backend/app/DTOs/Documents/DocumentBlockDto.php`
   - Acción: idem TemplateBlockDto.
   - Riesgo: bajo.

8. **TemplateBlockResource** — `backend/app/Http/Resources/TemplateBlockResource.php`
   - Acción: añadir `'kind' => $this->kind->value` en `toArray()`.
   - Por qué: serialización JSON para frontend.
   - Riesgo: bajo.

9. **DocumentBlockResource** — `backend/app/Http/Resources/DocumentBlockResource.php`
   - Acción: idem TemplateBlockResource.
   - Riesgo: bajo.

10. **FormRequests** — `StoreTemplateBlockRequest.php` + `UpdateTemplateBlockRequest.php`
    - Acción: añadir `'kind' => ['sometimes', 'string', Rule::in(BlockKind::values())]`. En Store, añadir hook `after(function ($validator) { ... })` que valida unicidad de toc: si `kind === 'toc'`, query raw `TemplateBlock::where('template_id', $tid)->where('kind', 'toc')->exists()` y si existe → `$validator->errors()->add('kind', 'Solo se permite un bloque de índice por plantilla.')`.
    - Por qué: validación server-side del enum + regla de negocio.
    - Riesgo: medio — la validación de unicidad debe ser case-insensitive y considerar soft-deletes.

11. **Verificar versionado** — `backend/app/Services/TemplateVersionBlockLayerWriter.php` + `TemplateVersionBlockLayerResolver.php`
    - Acción: añadir `kind` al array de campos que se serializan en `override_payload`. Verificar que el resolver lo deserializa al reconstruir bloques de una publicación previa.
    - Por qué: el `kind` debe versionarse igual que `block_state`, `title`, `default_content`.
    - Riesgo: medio — si se olvida, los bloques especiales pierden su `kind` al publicar/recuperar.

12. **Seeders / factories** — `backend/database/factories/TemplateBlockFactory.php` + `DocumentBlockFactory.php`
    - Acción: añadir `'kind' => 'content'` por defecto. Crear `state('cover')`, `state('blank')`, `state('toc')` para tests.
    - Por qué: tests necesitan crear bloques de cada kind.
    - Riesgo: bajo.

### Fase 2 — Backend: render y CSS Paged Media (4h)

1. **BlockNoteHtmlRenderer — firma + branching por kind** — `backend/app/Support/BlockNoteHtmlRenderer.php`
   - Acción: cambiar firma de `renderBlocks(array $blocks)` a `renderBlocks(array $blocksWithMetadata)` donde cada item es `['kind' => string, 'content' => array]`. Branching:
     - `kind=content` → comportamiento actual, envolver en `<section class="block-kind-content">…</section>`.
     - `kind=cover` → mismo render BlockNote envuelto en `<section class="block-kind-cover">…</section>`.
     - `kind=blank` → emite `<section class="block-kind-blank" role="presentation" aria-hidden="true"></section>`.
     - `kind=toc` → invoca método `generateToc()` (paso 5).
   - Por qué: marcar cada bloque con su clase CSS para que el CSS Paged Media discrimine.
   - Riesgo: medio — backwards-compatible si el caller actualiza la forma del array.

2. **DocumentRenderService — pasar metadata** — `backend/app/Services/DocumentRenderService.php` (líneas 59-86)
   - Acción: en línea 71-73, cambiar de:
     ```php
     $document->blocks->map(fn ($block) => (array) $block->content)->all()
     ```
     a:
     ```php
     $document->blocks->map(fn ($b) => ['kind' => $b->kind->value, 'content' => (array) $b->content])->all()
     ```
   - Por qué: propagar `kind` al renderer.
   - Riesgo: bajo.

3. **TemplateRenderService — idem** — `backend/app/Services/TemplateRenderService.php`
   - Acción: misma actualización del payload pasado al renderer.
   - Riesgo: bajo.

4. **CSS Paged Media en render.blade.php** — `backend/resources/views/documents/render.blade.php`
   - Acción: añadir bloque `<style>` con:
     ```css
     /* Páginas especiales sin chrome */
     @page cover { 
         margin: 0; 
         background: white;
         @top-left{content:none} @top-center{content:none} @top-right{content:none}
         @bottom-left{content:none} @bottom-center{content:none} @bottom-right{content:none}
         @left-top{content:none} @left-middle{content:none} @left-bottom{content:none}
         @right-top{content:none} @right-middle{content:none} @right-bottom{content:none}
     }
     @page blank { /* idem cover */ }
     
     /* Selección de páginas nombradas y saltos */
     .block-kind-cover { page: cover; break-before: page; break-after: page; }
     .block-kind-blank { page: blank; break-before: page; break-after: page; }
     .block-kind-toc { break-before: page; break-after: page; }
     
     /* Reset CSS del theme en cover/blank */
     .block-kind-cover, .block-kind-blank {
         --primary: initial; --secondary: initial; --text: black; --background: white;
         font-family: initial; color: black; background: white;
     }
     
     /* Suprimir watermark del theme en cover/blank */
     .block-kind-cover .theme-watermark,
     .block-kind-blank .theme-watermark { display: none; }
     
     /* TOC */
     .toc { list-style: none; padding: 0; margin: 1em 0; }
     .toc li { margin: 0.5em 0; display: flex; justify-content: space-between; }
     .toc a { color: inherit; text-decoration: none; }
     .toc-page::before { content: target-counter(attr(data-href url), page); }
     ```
   - Por qué: supresión total de chrome en cover/blank + saltos garantizados + numeración auto en TOC.
   - Riesgo: alto — CSS Paged Media es frágil en WeasyPrint. Validar con VeraPDF en CI.

5. **TOC single-pass** — `backend/app/Support/BlockNoteHtmlRenderer.php`
   - Acción: implementar `generateToc(array $contentBlocks): string` que:
     - Itera bloques con `kind='content'` y recorre su árbol BlockNote.
     - Extrae cada heading (`type='heading'`) con texto inline y nivel.
     - Asigna ID determinístico `block-{blockIndex}-h-{headingIndex}` y lo inserta en el HTML del heading correspondiente.
     - Emite `<ol class="toc">` con `<li class="toc-h{level}"><a href="#…">{texto}</a><span class="toc-page" data-href="#…"></span></li>`.
   - Por qué: índice auto-generado sin persistencia, números reales calculados por WeasyPrint vía `target-counter()`.
   - Riesgo: alto — IDs deben ser determinísticos (índice basado, no hash) para evitar colisiones entre renderizados.

### Fase 3 — Frontend: catálogo y editor (7h)

1. **Tipos TypeScript** — `frontend/src/types/blocks.ts`
   - Acción: añadir `export type BlockKind = 'content' | 'cover' | 'blank' | 'toc';`. Actualizar interfaz `Block` con `kind: BlockKind`.
   - Riesgo: bajo.

2. **Hook useCoverOverflow** — `frontend/src/features/blocks-ui/useCoverOverflow.ts`
   - Acción: hook que recibe un `RefObject<HTMLElement>` y un `pageHeightPx` (≈1122 a 96dpi). Devuelve `{ isOverflowing: boolean, pageCount: number }`. Mide `scrollHeight` después de cada cambio del contenido (debounce 200ms) usando `ResizeObserver`.
   - Por qué: detectar portadas que exceden A4.
   - Riesgo: bajo.

3. **Componente CoverBlockEditor** — `frontend/src/features/blocks-ui/CoverBlockEditor.tsx`
   - Acción: wrapper con clase `cover-editor-wrapper`:
     ```css
     width: 21cm;
     min-height: 29.7cm;
     background: white;
     color: black;
     font-family: system-ui, sans-serif;
     position: relative;
     overflow: visible;
     box-shadow: 0 0 0 1px #e5e7eb;
     ```
     Dentro: el componente BlockNote estándar (mismo que content).
     Pseudo-elemento `::after` con línea punteada roja a `top: 29.7cm`.
     Banner ámbar sticky cuando `useCoverOverflow().isOverflowing === true`, con texto dinámico "Esta portada generará {pageCount} páginas en el PDF".
   - Por qué: WYSIWYG real, opción permisiva con aviso.
   - Riesgo: bajo.

4. **Componente BlankBlockEditor** — `frontend/src/features/blocks-ui/BlankBlockEditor.tsx`
   - Acción: placeholder no-editable con texto "Página intencionadamente en blanco". Aspecto similar al wrapper de cover (lienzo A4 sombreado) para coherencia visual.
   - Riesgo: bajo.

5. **Componente TocBlockEditor** — `frontend/src/features/blocks-ui/TocBlockEditor.tsx`
   - Acción: placeholder no-editable con texto "Índice generado automáticamente en el PDF a partir de los encabezados de los bloques de contenido. Edita los headings de los bloques para modificar las entradas del índice."
   - Riesgo: bajo.

6. **Sidebar — añadir 3 tipos** — `frontend/src/features/templates/components/TemplateBlockSidebar.tsx` (o el componente equivalente del catálogo)
   - Acción: añadir 3 botones nuevos junto al actual "Añadir bloque": Portada, Índice, Página en blanco. Cada uno crea un bloque con su `kind`. El botón "Índice" se deshabilita si `blocks.some(b => b.kind === 'toc')`.
   - Riesgo: medio — verificar que el ordering por sort_order respeta la posición de inserción.

7. **BlockEditor branching** — `frontend/src/features/blocks-ui/BlockEditor.tsx` (o equivalente)
   - Acción: switch sobre `block.kind`:
     - `content` → componente BlockNote actual.
     - `cover` → `<CoverBlockEditor block={block} onChange={...} />`.
     - `blank` → `<BlankBlockEditor />`.
     - `toc` → `<TocBlockEditor />`.
   - Riesgo: bajo.

8. **Feature flag en frontend** — `frontend/src/api/config.ts` o equivalente
   - Acción: leer `import.meta.env.VITE_DMS_SPECIAL_BLOCKS_ENABLED` y ocultar los 3 botones del sidebar si está deshabilitado.
   - Riesgo: bajo.

### Fase 4 — Tests (4h)

1. **Pest: snapshot HTML por kind** — `backend/tests/Feature/Documents/RenderHtmlSnapshotTest.php`
   - Acción: para cada combinación (solo content, cover+content, cover+toc+content, cover+toc+content+blank+content), renderizar y comparar HTML contra snapshot. Verificar:
     - Presencia de `<section class="block-kind-cover">` etc.
     - `<ol class="toc">` cuando hay bloque toc.
     - Ausencia de strings del header/footer en el HTML correspondiente a cover/blank.
   - Riesgo: bajo.

2. **Pest: toc único por template** — `backend/tests/Feature/TemplateBlocks/TocUniquePerTemplateTest.php`
   - Acción: crear primer bloque kind=toc → 201. Segundo intento → 422 con mensaje específico.
   - Riesgo: bajo.

3. **Pest: kind persiste en versionado** — `backend/tests/Unit/Services/TemplateVersionBlockLayerKindTest.php`
   - Acción: crear template con bloques de varios kinds → publicar → leer la publicación → verificar que cada bloque mantiene su `kind`.
   - Riesgo: medio — clave para asegurar Fase 1 paso 11.

4. **Pest: e2e con WeasyPrint** — `backend/tests/Feature/Documents/SpecialBlocksPdfE2eTest.php`
   - Acción: template completo (cover+toc+3 content+blank+2 content) → publicar → crear documento → generar PDF → con `\Smalot\PdfParser\Parser` o similar:
     - Verificar nº de páginas (≥7).
     - Verificar que página 1 no contiene strings del header/footer del theme.
     - Verificar que la página del toc contiene los headings del documento.
   - Riesgo: medio — requiere WeasyPrint en el container de tests.

5. **VeraPDF en CI** — `.github/workflows/test-pdf-ua.yml`
   - Acción: job que descarga `verapdf/verapdf:latest` docker, descarga el PDF generado por el test e2e como artifact, ejecuta `verapdf --flavour ua1` y falla el job si no valida.
   - Riesgo: bajo.

6. **Vitest: CoverBlockEditor overflow** — `frontend/src/__tests__/features/blocks-ui/CoverBlockEditor.test.tsx`
   - Acción: render con contenido pequeño → no banner. Inyectar contenido grande (mock de scrollHeight) → banner visible con texto correcto.
   - Riesgo: bajo.

7. **Vitest: branching del editor** — `frontend/src/__tests__/features/blocks-ui/BlockEditor.test.tsx`
   - Acción: render con cada kind → verifica componente correcto.
   - Riesgo: bajo.

8. **E2E Playwright** (opcional) — `frontend/e2e/special-blocks.spec.ts`
   - Acción: crear template con los 4 kinds desde la UI → publicar → crear documento → descargar PDF → verificar nº de páginas.
   - Riesgo: bajo (skip si no hay setup E2E todavía).

### Fase 5 — Validación y feature flag (2h)

1. **Feature flag backend** — `backend/config/dms.php`
   - Acción: añadir `'special_blocks_enabled' => env('DMS_SPECIAL_BLOCKS_ENABLED', false)`.
   - Riesgo: bajo.

2. **Middleware de feature flag** — `backend/app/Http/Middleware/EnsureSpecialBlocksEnabled.php`
   - Acción: middleware que, si `kind !== 'content'` en el request body de Store/Update TemplateBlock, verifica `config('dms.special_blocks_enabled')`. Si false → 403.
   - Registrar en `routes/api.php` sobre las rutas de blocks.
   - Riesgo: medio — debe permitir reads de bloques pre-existentes con `kind != content` aunque el flag esté off.

3. **Validación manual en staging**
   - Acción: crear template con los 4 kinds usando un caso real de programación didáctica (25_26_DAW_0613_DWES.md). Generar PDF. Imprimir doble cara y verificar que `kind=blank` cumple su función.
   - Riesgo: bajo.

4. **Documentación** — `backend/docs/SPECIAL_BLOCKS.md`
   - Acción: guía corta para editores: qué hace cada kind, cuántos se pueden tener, cómo se versiona, limitaciones (TOC no editable, etc.).
   - Riesgo: bajo.

## Criterios de aceptación

- [ ] Enum `BlockKind` con 4 casos creado en `backend/app/Enums/BlockKind.php`.
- [ ] Migraciones aplicadas sin pérdida de datos. Default `'content'` para registros existentes.
- [ ] Models, DTOs, Resources, FormRequests reflejan `kind` correctamente.
- [ ] Versionado: bloques especiales sobreviven publicación + reconstrucción manteniendo su `kind`.
- [ ] Render HTML emite `<section class="block-kind-{kind}">` para todos los bloques.
- [ ] CSS Paged Media suprime header/footer/watermark del theme en páginas cover y blank.
- [ ] Reset CSS suprime paleta y tipografía del theme en cover/blank.
- [ ] TOC generado en render-time desde headings de bloques `kind=content`, números de página reales calculados por WeasyPrint.
- [ ] Frontend: 3 botones nuevos en sidebar, botón TOC se deshabilita si ya existe uno.
- [ ] CoverBlockEditor con wrapper A4 visible, línea guía punteada roja a 29.7cm, banner ámbar cuando overflow.
- [ ] BlankBlockEditor y TocBlockEditor no-editables con texto explicativo.
- [ ] Validación server-side: 422 si se intenta crear segundo bloque toc por template.
- [ ] PDF generado con cover+toc+content+blank+content tiene ≥7 páginas y portada sin chrome.
- [ ] VeraPDF valida PDF/UA-1 del PDF de e2e en CI.
- [ ] Feature flag `DMS_SPECIAL_BLOCKS_ENABLED=false` rechaza creación de bloques no-content con 403 (datos existentes intactos).
- [ ] Cobertura de tests ≥80% en `BlockNoteHtmlRenderer`, `DocumentRenderService`, `BlockKind` enum.

## Riesgos conocidos asumidos

1. **TOC sin edición manual**: si el usuario quiere personalizar texto del índice, debe cambiar headings de los bloques de contenido. Decisión consciente (consistencia con LaTeX/Word).
2. **Portada de N páginas**: si el usuario crea una portada que se desborda, las N páginas resultantes salen sin chrome. Aceptable.
3. **WeasyPrint + PDF/UA-1**: validación obligatoria con VeraPDF en CI; si rompe, bloquea merge.

## Rollback

Si algo va mal con el feature flag activo, los pasos son:

1. **Desactivar feature flag**: `DMS_SPECIAL_BLOCKS_ENABLED=false` en `.env`.
2. **Middleware bloquea creación nueva**: cualquier intento de crear/editar bloque con kind != content devuelve 403.
3. **Datos existentes intactos**: bloques con kind especial siguen en BD y se renderizan, pero no se pueden crear nuevos.
4. **Rollback total** (si fuera necesario): borrar bloques `kind != 'content'` (soft-delete), luego revertir migraciones:
   ```bash
   php artisan tinker --execute="App\Models\TemplateBlock::where('kind', '!=', 'content')->delete();"
   php artisan tinker --execute="App\Models\DocumentBlock::where('kind', '!=', 'content')->delete();"
   php artisan migrate:rollback --step=2
   ```

## Esfuerzo total estimado

| Fase | Horas |
|------|-------|
| 1 — Backend modelo y versionado | 8h |
| 2 — Backend render y CSS Paged Media | 4h |
| 3 — Frontend catálogo y editor | 7h |
| 4 — Tests | 4h |
| 5 — Validación y feature flag | 2h |
| **Total** | **~25h** |

**Hito crítico**: Fase 2, paso 4 (CSS Paged Media) — sin VeraPDF green, no se procede a Fase 3.

## Archivos clave (verificados en código)

| Archivo | Acción |
|---------|--------|
| `backend/app/Models/TemplateBlock.php` (l. 24-40) | Añadir kind a fillable/casts |
| `backend/app/Models/DocumentBlock.php` (l. 25-44) | Idem |
| `backend/app/Enums/BlockKind.php` | Crear |
| `backend/app/DTOs/TemplateBlocks/TemplateBlockDto.php` | Añadir kind |
| `backend/app/DTOs/Documents/DocumentBlockDto.php` | Añadir kind |
| `backend/app/Http/Resources/TemplateBlockResource.php` | Exponer kind en JSON |
| `backend/app/Http/Resources/DocumentBlockResource.php` | Idem |
| `backend/app/Http/Requests/TemplateBlocks/StoreTemplateBlockRequest.php` | Validar kind + toc único |
| `backend/app/Http/Requests/TemplateBlocks/UpdateTemplateBlockRequest.php` | Validar kind |
| `backend/app/Services/DocumentRenderService.php` (l. 71-73) | Pasar kind en metadata |
| `backend/app/Services/TemplateRenderService.php` | Idem |
| `backend/app/Services/TemplateVersionBlockLayerWriter.php` | Serializar kind en override_payload |
| `backend/app/Services/TemplateVersionBlockLayerResolver.php` | Deserializar kind |
| `backend/app/Support/BlockNoteHtmlRenderer.php` (l. 26-67) | Branching por kind + generateToc |
| `backend/resources/views/documents/render.blade.php` | Añadir CSS Paged Media |
| `backend/database/migrations/2026_05_23_000001_*` | Crear migración template_blocks |
| `backend/database/migrations/2026_05_23_000002_*` | Crear migración document_blocks |
| `backend/database/factories/TemplateBlockFactory.php` | Factory states |
| `backend/config/dms.php` | Feature flag |
| `backend/app/Http/Middleware/EnsureSpecialBlocksEnabled.php` | Crear middleware |
| `frontend/src/types/blocks.ts` | Añadir BlockKind |
| `frontend/src/features/blocks-ui/CoverBlockEditor.tsx` | Crear |
| `frontend/src/features/blocks-ui/BlankBlockEditor.tsx` | Crear |
| `frontend/src/features/blocks-ui/TocBlockEditor.tsx` | Crear |
| `frontend/src/features/blocks-ui/useCoverOverflow.ts` | Crear hook |
| `frontend/src/features/blocks-ui/BlockEditor.tsx` | Branching por kind |
| `frontend/src/features/templates/components/TemplateBlockSidebar.tsx` | Añadir 3 botones + lógica disable toc |
| `.github/workflows/test-pdf-ua.yml` | CI VeraPDF |

---

**Estado**: pendiente de aprobación para empezar implementación.
