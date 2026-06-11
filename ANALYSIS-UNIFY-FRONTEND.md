# Análisis: Unificación Frontend Templates ↔ Documents (maya_dms)

> Generado por 4 agentes de análisis en paralelo (solo lectura) el 2026-06-11.
> Plan ejecutable derivado: [PLAN-UNIFY-FRONTEND.md](./PLAN-UNIFY-FRONTEND.md)
> Regla de oro: el estilo visual NO cambia. Regla de ecosistema: adoptar
> @ceedcv-maya/shared-*-react, nunca crear sustitutos locales.

---

## Agente A — Auditoría de paquetes compartidos (shared-*-react)

### Veredicto general
Las tablas de dms ya usan `DataTable` + `useServerTable` correctamente. No hay
sustituciones masivas de componentes shared. Los problemas son puntuales.

### SUSTITUIR (componente local que duplica uno shared)

| Local | Shared | Ubicación | Nota |
|---|---|---|---|
| Spinner inline con colores fuera de token (gray/purple-800) | `Spinner` (shared-ui) | WizardStep2Blocks.tsx:1206, DocumentWizard.tsx:2453 | El cambio corrige una desviación de marca (vuelta a odoo-purple) |
| Confirm inline hecho a mano | `ConfirmDialog`/`useConfirm` (shared-ui) | WizardStep3Users.tsx:215-259 | El estándar ConfirmDialog ya se usa en el propio dms |
| Tabs a mano | `Tabs` (shared-ui) | BlockEditorTabs.tsx, BlockChangesPanel.tsx | Gana a11y (roles/teclado); verificar paridad visual exacta |

### BLOQUEADO (requiere decisión o cambio en maya_platform)

| Componente | Bloqueo |
|---|---|
| TemplateReviewView tabs | Indicador TEAL; el `Tabs` shared es morado. Decisión usuario (Q-D) |
| VersionHistoryPanel / ProcessesDrawer → `Drawer` shared | El Drawer shared no soporta offset top-12 ni anclaje left-of-sidebar. Candidato a props nuevas en maya_platform |
| Modal centrado (ProcessFormModal, DocumentDiffModal) | shared-ui NO tiene Modal centrado. Legítimamente locales HOY; candidato a promover `Modal` a shared-ui-react (Q-E) |

### LEGÍTIMAMENTE LOCALES (no tocar)
ColorBadge, SequentialValidatorBadge, components/canvas/* (AbsoluteCanvas),
DocxBlockSplitter, paneles de diff (VersionComparePanel, BlockChangesPanel como
dominio), ErrorBoundaryWrapper (wrapper i18n documentado), StructuralBlockPreview,
CoverRegionPreview — dominio dms puro o gaps conocidos del paquete.

---

## Agente B — Divergencias Templates vs Documents (componentes/hooks)

### Divergencias con impacto funcional (unificar)

| ID | Divergencia | Lado correcto |
|---|---|---|
| D1 | Tipo de error en hooks de tabla: `string \| null` (templates) vs `Error \| null` (documents) | `Error \| null` + formato en render |
| D4 | Favoritos keyed por `version_id` (templates) vs entity id (documents) | VERIFICAR contra backend (UserFavorite*) — uno está mal |
| D5 | `displayDocuments` deps sin `filters.status` → memo stale | añadir dep |
| D6 | `canOpenDocument` no comprueba reviewer asignado; templates sí | paridad con templates (validar contra gates backend) |
| D9 | Params i18n de paginación divergentes | `{from, to, total}` en ambos |
| D12 | DocumentPreviewPage:89-114 duplica en local funciones de `documentWizardUtils` con typing débil | importar las canónicas |
| D13 | `effectiveDocumentReviewMode` existe pero :392 usa `review_mode` pelado | usar la efectiva |
| D14 | Comentarios: TanStack Query (documents) vs useState local (TemplatePreviewPage) | TanStack (`useTemplateCommentsQuery` ya existe) |
| D15/D19 | `documentCommentsKey` no exportada; query-key hardcodeada 2× | exportar y consumir |

### Menores
D2 `NO_MATCH_ID` duplicada; D7 formato de mensaje de error; D8 `type="search"`
faltante en TemplatesTable:322; D10 `canSeeLive` (divergencia JUSTIFICADA:
templates no tiene owner/share — documentar inline).

### Calidad estructural
- **Q1**: DocumentPreviewPage = 1572 líneas, SIN tests. Espejo natural: extraer `DocumentValidateView` como TemplateReviewView.
- **Q2**: DocumentWizard = 2890 líneas (límite del proyecto: 800). TemplateWizard ya está troceado en pasos.
- **Q3**: useTemplates.ts legacy con paginación client-side manual — deprecar.

### Positivo
10 componentes ya compartidos entre dominios (BlockChangesPanel, VersionHistoryPanel,
VersionComparePanel, StructuralBlockPreview, editor de bloques, etc.) — la dirección
de reuso cross-dominio ya existe; este plan la completa.

---

## Agente C — Capa API (src/api/*)

| ID | Hallazgo | Severidad | Acción |
|---|---|---|---|
| API-1 | **Contrato de retorno inconsistente**: documents.ts devuelve `T` pelado (desenvuelve internamente); templates.ts/themes.ts devuelven `{data: T}` y obligan a `.data` en cada call-site | ALTA | Unificar a pelado (estilo documents); migrar fetchTemplate/create/update/clone/startNewVersion/discard/submit/approve/reject + themes y sus hooks |
| API-2 | Patrón blob-download copiado ×3 (templates.ts:52-79, 387-421, documents.ts:506-533) | MEDIA | helper `downloadAuthenticatedBlob(apiPath, filename)` |
| API-3 | Query-string builders ×4 distintos | MEDIA | `buildQueryString(params)` (processes.ts solo CONSUME — otra sesión) |
| API-4 | Parseo de error duplicado ×5 | MEDIA | helper único |
| API-5 | `uploadMedia` lanza `Error` genérico, resto `ApiHttpError` | MEDIA | ApiHttpError |
| API-6 | `fetchThemes` no pasa por `normalizePaginatedResponse` | BAJA | normalizar |
| API-7 | `fetchDocuments` (full) no devuelve meta; templates sí | BAJA | igualar o documentar |
| — | `deleteTemplate` devuelve union con `hardDeleted` | JUSTIFICADA | NO tocar — el FE usa la señal (asimetría backend documentada en changes.md) |

---

## Agente D — Transversal (permisos, navegación, estado, i18n)

### Permisos (gate: security-reviewer)
- **P-01 (ALTA)**: `canMutateDocumentBlocks` solo mira permiso, ignora status del
  documento y ownership; el equivalente de templates valida ambos. Riesgo: UI ofrece
  acciones que el backend rechaza (o peor, si algún gate backend confía en contexto).
- **P-02**: guardia `status !== 'published'` de comentarios repetida inline — encapsular en permissions.ts.
- **P-04**: badge de warning no incluye `rejected` para documents (templates sí).

### Navegación
- **N-01**: ruta `/documentos/nuevo` en español vs resto del router en inglés (Q-F).
- **N-05**: DocumentPreviewPage hace de página de validación Y preview (vs TemplateReviewView separada).
- **N-06**: fallbacks de `useBackNavigation` incoherentes (/procesos, /dashboard, /) — unificar a /procesos.

### Estado/datos
- **V-01**: query-key de comentarios duplicada también en commentCache.
- **V-02**: TemplateReviewView hace `setQueryData` inline para add-comment — mover a helper de commentCache.
- **V-05**: DocumentWizard usa `fetchProcesses()`+useEffect en vez de `useProcessesQuery()` (cache 30s ya existente).
- **V-06 (ALTA)**: `fetchMe()` llamado redundantemente en DocumentWizard y DocumentPreviewPage cuando `useUserProfile` ya tiene `profile.id` — request extra por render de página.
- **V-07**: TemplateEditPage sin ErrorBoundaryWrapper (las demás páginas lo llevan).
- **V-08**: TemplateEdit/ReviewPage con fetch manual — candidatas a TanStack Query.

### i18n / visual-tokens
- **S-01**: `STATUS_LABEL` hardcodeado en Contents ignorando keys i18n existentes (es: byte-idéntico → cero cambio visual).
- **S-02**: `STATUS_CLASS` local en DocumentsContent vs `statusBadgeClass()` de shared-ui — verificar tokens idénticos ANTES de sustituir.
- **V-04**: strings hardcodeados + uso cross-namespace en TemplateBlockHistoryPanel (dentro de TemplateReviewView).

### Feature gaps
- **F-01**: favoritos NO visibles en la lista de documents (hook e i18n ya preparados) — gap, no decisión.
- **G-02**: DocumentWizard sin leave-guard de propiedades dirty (TemplateWizard lo tiene).
- **Q-C**: changelog del wizard de documents arranca vacío en cada envío; la preview sí pre-rellena y templates pre-rellena en ambos sitios — ¿olvido?

---

## Preguntas al usuario (consolidadas — bloquean partes del plan)

Ver tabla Q-A..Q-G al final de [PLAN-UNIFY-FRONTEND.md](./PLAN-UNIFY-FRONTEND.md).
