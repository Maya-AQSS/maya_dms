# Plan: Unificación y saneo del Frontend Templates ↔ Documents (maya_dms)

> Rama: continúa en `refactor/unify-template-document-impl`.
> REGLA DE ORO: el estilo visual NO cambia. Solo lógica, consistencia y adopción
> de paquetes compartidos. Acciones iguales → mismo comportamiento y mismos retornos.
> Regla de ecosistema: NO crear componentes locales que sustituyan a los de
> @ceedcv-maya/shared-*-react; la dirección es adoptar el paquete, no reemplazarlo.
> Análisis de soporte: [ANALYSIS-UNIFY-FRONTEND.md](./ANALYSIS-UNIFY-FRONTEND.md)
>
> ⚠️ Hay OTRA SESIÓN activa con cambios sin commitear (paginatedList, api/processes,
> useServer*Table, dataTablePageSize, wizards goBack, tablas/páginas de procesos y
> temas). Ningún agente debe modificar esos archivos hasta que esa sesión commitee.

## Fase F0 — Baseline (bloqueante)

- Capturar baseline vitest: `npx vitest run` en el contenedor frontend → conteo por archivo.
- Verificación visual de referencia: capturas de las superficies que se tocan
  (listas, preview, wizard) ANTES de cambiar — comparación manual al final.

## Fase F1 — Bugs y quick-wins de paridad (riesgo BAJO, 3 agentes paralelos)

| # | Hallazgo | Fix | Archivos |
|---|---|---|---|
| F1.1 | D12: funciones locales duplican `documentWizardUtils` con typing débil | borrar locales, importar | DocumentPreviewPage.tsx:89-114 |
| F1.2 | D13: `effectiveDocumentReviewMode` ignorado (usa review_mode pelado) | 1 línea | DocumentPreviewPage.tsx:392 |
| F1.3 | D15/D19/V-01: `documentCommentsKey` no exportada; key hardcodeada 2× + duplicada en commentCache | exportar y consumir | useDocumentComments.ts, commentCache.ts, DocumentPreviewPage |
| F1.4 | V-06: `fetchMe()` redundante en DocumentWizard/DocumentPreviewPage | usar `profile.id` de useUserProfile | 2 archivos |
| F1.5 | V-07: TemplateEditPage sin ErrorBoundaryWrapper | envolver | TemplateEditPage.tsx |
| F1.6 | V-05: `fetchProcesses()`+useEffect en DocumentWizard | `useProcessesQuery()` (cache 30s) | DocumentWizard.tsx |
| F1.7 | D5: deps de `displayDocuments` sin `filters.status` | añadir dep | DocumentsTable.tsx:147 |
| F1.8 | D8: `type="search"` faltante | 1 línea | TemplatesTable.tsx:322 |
| F1.9 | D2: `NO_MATCH_ID` duplicada | extraer a lib | ambos hooks de tabla |
| F1.10 | S-02: `STATUS_CLASS` local vs `statusBadgeClass()` shared | usar shared (verificar tokens idénticos ANTES) | DocumentsContent.tsx |
| F1.11 | S-01: STATUS_LABEL hardcodeado ignorando i18n keys existentes | usar t() (es: byte-idéntico) | Templates/DocumentsContent |
| F1.12 | V-04: strings hardcodeados + cross-namespace en TemplateBlockHistoryPanel | keys en templates.json | TemplateReviewView.tsx |
| F1.13 | P-04: warning badge sin `rejected` en documents | añadir condición | DocumentsContent.tsx |
| F1.14 | N-06: fallbacks de useBackNavigation incoherentes (/procesos vs /dashboard vs /) | unificar a /procesos | 3 archivos |
| F1.15 | V-02: add-comment con setQueryData inline en TemplateReviewView | helper en commentCache | TemplateReviewView.tsx |

⚠️ F1.7/F1.10/F1.11/F1.13 tocan DocumentsContent/DocumentsTable/TemplatesContent —
verificar antes que la otra sesión no los tenga modificados; si los tiene, posponer.

## Fase F2 — Capa API unificada (riesgo MEDIO, 1 agente)

1. `api/blobDownload.ts`: `downloadAuthenticatedBlob(apiPath, filename)` — sustituye
   las 3 copias del patrón fetch+blob+`<a>` (templates.ts:52-79/387-421, documents.ts:506-533).
2. `api/queryString.ts`: `buildQueryString(params)` — sustituye los 4 builders.
   (processes.ts SOLO consumir, no editar — otra sesión).
3. Parseo de error unificado (5 copias) + `uploadMedia` pasa a `ApiHttpError` (D-09).
4. **Decisión de contrato**: unificar retornos al estilo "pelado" (T directo, el de
   documents): migrar `fetchTemplate/createTemplate/updateTemplate/cloneTemplate/
   startTemplateNewVersion/discard/submit/approve/reject` y los de themes a devolver
   T sin envelope, actualizando hooks/call-sites (`useTemplateQuery`...). EXCEPCIÓN
   justificada que NO se toca: `deleteTemplate` (union hardDeleted — el FE usa la señal).
5. `fetchDocuments` (full) devuelve también meta como templates, o se documenta por qué no.
6. themes.ts pasa por `normalizePaginatedResponse` como el resto.

## Fase F3 — Hooks/tablas: paridad de comportamiento (riesgo MEDIO, 1-2 agentes)

1. D1/D7: tipo de error unificado `Error | null` en ambos hooks de tabla; formato
   de render en el componente (i18n con message en ambos).
2. D9: paginación i18n al formato `{from, to, total}` en ambos.
3. D6: `canOpenDocument` añade check de reviewer asignado (paridad con templates) —
   verificar contra gates del backend con Feature tests como referencia.
4. D10: `canSeeLive` — confirmar contra el modelo (templates no tiene owner/share → divergencia justificada, documentar inline).
5. F-01: favoritos visibles en lista de documentos (hook y i18n ya listos — feature gap).
6. D4/F-02: keying de favoritos (version_id vs entity_id) — VERIFICAR contra backend
   (UserFavorite* models/endpoints) y corregir el lado que esté mal o documentar.
7. P-01: `canMutateDocumentBlocks(hasPermission, profileId?, document?)` con check de
   status+ownership (paridad con templates). Gate: security-reviewer.
8. P-02: encapsular guardia `status !== published` de comentarios en permissions.ts.

## Fase F4 — Adopción de shared-ui (riesgo MEDIO, 1 agente; estilo verificado caso a caso)

| Acción | Veredicto del análisis |
|---|---|
| Spinner inline (WizardStep2Blocks:1206, DocumentWizard:2453) → `Spinner` | SUSTITUIR — además corrige colores fuera de token (gray/purple-800 → odoo-purple). Cambio visual MÍNIMO y deseado (vuelta a marca) |
| Confirm inline de WizardStep3Users:215-259 → `ConfirmDialog`/`useConfirm` | SUSTITUIR — estándar ya usado en el propio dms |
| BlockEditorTabs + BlockChangesPanel tabs → `Tabs` shared | MIGRAR — gana a11y; verificar paridad visual exacta |
| TemplateReviewView tabs (indicador teal) | BLOQUEADO — decisión usuario (teal→purple o parametrizar Tabs en shared) |
| VersionHistoryPanel / ProcessesDrawer → `Drawer` | BLOQUEADO — Drawer compartido no soporta offset top-12/left-sidebar; candidato a props nuevas en maya_platform |
| Inputs manuales (inputClass/labelClass) → TextInput/Select/FieldLabel | SUSTITUIR donde el visual sea idéntico (campo a campo) |
| Modal centrado (ProcessFormModal, DocumentDiffModal, confirm) | NO duplicar más — candidato a `Modal` en shared-ui-react (decisión usuario: tocar maya_platform) |

## Fase F5 — Arquitectura (riesgo ALTO, secuencial, decisión de alcance pendiente)

1. D14: TemplatePreviewPage migra comentarios de useState local a TanStack Query
   (`useTemplateCommentsQuery` ya existe) — coherencia de caché con documents.
2. N-05/Q1: extraer `DocumentValidateView` de DocumentPreviewPage (1572 líneas, sin tests)
   espejo de TemplateReviewView. Solo reorganización, cero cambio visual.
3. Q2: trocear DocumentWizard (2890 líneas; límite del proyecto 800) en pasos como TemplateWizard.
4. Q3: useTemplates.ts legacy (paginación client-side manual) → deprecar/migrar.
5. V-08: TemplateEdit/ReviewPage a TanStack Query.

## Gates y verificación

- Por fase: vitest sin regresiones vs baseline F0 + verificación visual manual de
  las superficies tocadas (el estilo es invariante del proyecto).
- F3.7 (permisos): security-reviewer obligatorio.
- typecheck: por-archivo (tsc -b completo OOMea en el contenedor — gotcha conocido).
- Gate final: code-reviewer sobre el diff frontend completo.

## Preguntas abiertas al usuario (bloquean partes concretas)

| # | Pregunta | Bloquea |
|---|---|---|
| Q-A | ¿Asignación de revisores de documentos debe tener permiso granular `document.assign-review` (como template) o basta `document.update`? | F3 |
| Q-B | ¿Documents tendrá estado `archived` como templates? (faltarían i18n key + badge) | F1.11 |
| Q-C | El changelog del wizard de documentos empieza en blanco en cada envío (la preview sí pre-rellena, y templates pre-rellena en ambos sitios). ¿Olvido a corregir o intencional? | F1 |
| Q-D | Tabs de TemplateReviewView usan indicador TEAL (shared Tabs es morado): ¿cambiar a morado estándar o parametrizar color en shared-ui (toca maya_platform)? | F4 |
| Q-E | Modal centrado: ¿promovemos un `Modal` genérico a shared-ui-react (release de maya_platform) o seguimos con copias locales en dms? | F4 |
| Q-F | Ruta `/documentos/nuevo` (español) vs resto en inglés: ¿renombrar a `/documents/new`? (rompe bookmarks/deeplinks existentes) | F1 |
| Q-G | ¿F5 entero (DocumentValidateView + trocear DocumentWizard) dentro de esta tarea o rama aparte? | F5 |
