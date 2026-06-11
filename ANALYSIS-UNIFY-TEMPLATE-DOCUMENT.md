# Análisis consolidado: duplicación Templates ↔ Documents (maya_dms/backend)

> Generado por 4 agentes de análisis (capa HTTP, Services, DTOs/Repos/Models, architect shared-vs-DMS).
> Soporta el plan [PLAN-UNIFY-TEMPLATE-DOCUMENT.md](./PLAN-UNIFY-TEMPLATE-DOCUMENT.md).
> Todas las rutas son relativas a `backend/app/`.

## 1. Decisión arquitectónica: SHARED vs DMS-ONLY

**Veredicto: 0 paquetes maya_platform nuevos. Toda la unificación es abstracción local en maya_dms.**

Evidencia cross-repo (búsqueda en dashboard/audit/authz/logs):
- `entity_versions` / `EntityVersion` / `snapshot_data` → 0 matches fuera de dms.
- `TiptapHtmlRenderer` server-side → solo dms (`Services/Concerns/BlockRenderSupport.php`). Ya extraído en `shared-editor-laravel`.
- Máquina de estados con `transition()`/`*StateChanged` → solo dms.
- `Comment` de maya_logs es trivial (4 fillable); el de dms está acoplado a entity_versions/shares/reviewers. No comparten abstracción.
- Favoritos: dashboard tiene `UserFavoriteApplication` con esquema distinto.

| Candidato | Veredicto | Destino |
|---|---|---|
| Versionado append-only `entity_versions` + lifecycle + reconstruction + snapshots | DMS-ONLY | abstracción local |
| Render Tiptap puro JSON→HTML | SHARED (ya existe) | consumir `shared-editor-laravel` |
| Envelope/paginación API | SHARED (ya existe) | consumir `shared-http-laravel` (`RespondsWithEnvelope`, `PaginatedDto`, `FilterDto`, `PaginatedFilterRequest`) |
| `BlockRenderSupport`, `TocBuilderService`, `CoverRenderService` | DMS-ONLY | mantener locales |
| Workflow de estados, comentarios anclados, favoritos | DMS-ONLY | locales |
| `EntityVersionReconstructionService` | **watch-item** | extraer solo con 2º consumidor real + contrato estable ≥1 release |

Razones: regla "sin 2º consumidor no se extrae"; el esquema de entity_versions aún itera; `^0.x` no cruza minors → cada cambio publicado exigiría bump manual en 5 repos; las migraciones en paquete acoplarían el esquema dms al release del monorepo.

## 2. Duplicaciones en Services (clusters D)

| ID | Qué | Dónde | % | Extracción | Riesgo |
|---|---|---|---|---|---|
| D-01 | `payloadsEqual`+`normalizeForCompare` | `Services/TemplateVersionBlockLayerWriter.php:100-118` = `DocumentVersionBlockLayerWriter.php:101-115` | 100% | `Support/BlockLayerPayloadComparator` | BAJO |
| D-02 | `syncLayersForNewPublication` | `TemplateVersionBlockLayerWriter.php:29-93` vs `DocumentVersionBlockLayerWriter.php:30-94` | 85% | `AbstractBlockLayerWriter` (difieren FKs y fuente de snapshot previo: `blocksSnapshotRows` vs `snapshotData['blocks']`) | MEDIO |
| D-03 | `effectiveBlockPayload` (recursión herencia) | `TemplateVersionBlockLayerResolver.php:104-131` vs `DocumentVersionBlockLayerResolver.php:78-103` | 95% | `AbstractBlockLayerResolver` + interfaz de snapshot homogénea | MEDIO |
| D-04 | `blockFromSnapshotOnly` | `TemplateVersionBlockLayerResolver.php:133-145` vs `DocumentVersionBlockLayerResolver.php:106-114` | 90% | idem D-03 | MEDIO |
| D-05 | runner WeasyPrint stdin/stdout | `TemplatePdfService.php:28-52` = `ThemePdfService.php:29-52` (+`DocumentPdfService::runWeasyprint` variante) | 95% | `Support/WeasyPrintRunner`. ⚠️ Divergencia funcional: timeout 60 (template) vs 30 (theme) | BAJO |
| D-06 | `normalizeUpdateAttributes*` (nulificación de scope académico por `TemplateVisibilityLevel`) | `TemplateService.php:1069-1178` vs `DocumentService.php:889-1014`; bloque match idéntico ~1100-1135 / ~920-955 | 85% | `Support/AcademicScopeNormalizer` con validaciones extra por dominio antes/después | MEDIO |
| D-07 | `destroyVersion` | `TemplateService.php:497-548` vs `DocumentService.php:1074-1121` | 80% | `EntityVersionDestroyService` (ya comparten `EntityVersionRepository`; difieren layer-repo y evento) | MEDIO |
| D-08/09/10 | `hasPublishedSnapshot`, `findLatestPublishedVersion`, `resolveWorkingRevisionConflict` | `TemplateService.php:240-270` vs `DocumentService.php:265-296` | 90-100% | thin wrappers — no prioritario; agrupar si surge una fachada de versionado | BAJO |
| D-11 | `notifyValidationRequested` | `TemplateReviewService.php:300-350` vs `DocumentReviewService.php:310-365` | 85% | `ReviewValidationNotifier(broadcast: bool)`. Document hace broadcast adicional (`BroadcastNotificationCreated`) | BAJO |
| D-12 | `startNewRevisionCycle` | `TemplateService.php:390-440` vs `DocumentService.php:450-500` | 75% | **NO UNIFICAR** — fuentes de snapshot divergen; riesgo ALTO | — |

**Asimetrías justificadas (NO tocar)**: `DocumentRenderService::renderHtmlForVersion` (templates no renderizan histórico), `DocumentPdfService::generate` (persiste a disco; template/theme efímeros), `DocumentMigrationBlockDiffer/PayloadResolver` (migración es doc-only), `TemplateVersionBlockLayerResolver::backfillStructuralFields` (fix de snapshots legacy), broadcast realtime doc-only, `EntityVersionLifecycleService::publish` path template vs `createPublishedSnapshotVersion` doc. `TemplateContextResolver` es un misnomer: resuelve contexto académico al CREAR documentos.

## 3. Violaciones de arquitectura

### 3.1 CRITICAL — `DB::table` en Service saltando repositorio
- `Services/TemplateService.php:255-261` — `DB::table('template_reviewers')->insert()`
- `Services/TemplateService.php:1204-1208` — `DB::table('template_reviewers')->delete()`

### 3.2 Eloquent/relaciones en Services (19 sitios)
| Archivo | Líneas | Qué |
|---|---|---|
| `TemplateService.php` | 619, 622-641, 695-727, 766-779 | `reviewers()/documentReviewers()` create/delete/forceDelete |
| `TemplatePublishingService.php` | 89 | `$template->reviewers()->where(...)->first()` |
| `TemplateReviewService.php` | 48, 53, 197, 213-215, 289-295 | `blocks()->doesntExist()`, `load()`, reviewers queries/updates |
| `TemplateReviewerAssignmentService.php` | 39 | atributos directos + `DB::transaction` |
| `SnapshotService.php` | 79, 106, 118 | `load(['blocks','reviews'])` + map de colecciones Eloquent |
| `DocumentService.php` | 1914 | `comments()->exists()` |
| `DocumentRenderService.php` | 151 | `$document->template?->theme` |
| `DocumentPdfService.php` | 135-138 | `getAttribute('current_version')` |
| `DocumentMigrationPayloadResolver.php` | 80-86 | `$source->blocks` |

### 3.3 Controllers/Requests/Resources saltando capas
| Archivo | Líneas | Qué |
|---|---|---|
| `Http/Controllers/Api/TemplateController.php` | 97-115 | lógica de visibilidad inline en `show()` (vs `DocumentService::resolveDocumentViewerContext`) |
| `DocumentPreviewController.php` | 8, 27 | inyecta `DocumentRepositoryInterface`; `findOrFailForRefreshAfterMutation` directo |
| `DocumentBlockController.php` | 4, 77-78 | repo directo + `setRelation()` en `destroy()` |
| `ThemeController.php` | 64, 71 | `Theme::query()->findOrFail()` en publish/archive (patrón correcto `modelForPolicy` ya existe en la misma clase) |
| `ThemePreviewController.php` | 60 | `Theme::query()->findOrFail()` |
| `Requests/Documents/StoreDocumentRequest.php` | 35-44, 98-107 | 4 queries Eloquent duplicadas entre `authorize()` y `withValidator()` |
| `Requests/Templates/CloneTemplateRequest.php` / `Documents/CloneDocumentRequest.php` | 17 | `findOrFail` directo (usar traits `Resolves*ForAuthorization`) |
| `Requests/Documents/RejectDocumentReviewRequest.php` | 89-94 | query `Comment::withoutGlobalScopes()` en FormRequest |
| `Requests/Templates/PublishTemplateRequest.php` | 71 | `Template::query()->findOrFail()` en `resolveTemplate()` |
| `Http/Resources/TemplateVersionResource.php` | 71 | `DB::table('users')` en Resource |
| `Http/Resources/TemplateVersionSummaryResource.php` | 95, 44-87 | `DB::table('users')` + parser de snapshot JSONB duplicado con el anterior |

### 3.4 Seguridad / autorización
- **`Requests/Documents/PublishDocumentRequest.php:17`: `authorize()` → `return true`** mientras `PublishTemplateRequest` sí autoriza (`can('publish', resolveTemplate())`). La autorización de documents solo se ejerce en el controller. `resolveDocument()` existe como dead code. Igualar = cambio de comportamiento de seguridad → decisión Fase 6.3 + entrada CRITICAL en changes.md.

### 3.5 Services que devuelven array/Eloquent en vez de DTO (11 firmas)
| Archivo:línea | Método | Devuelve |
|---|---|---|
| `DocumentVersionService.php:36` | `findDocumentVersionOrFail` | array |
| `DocumentVersionService.php:66` | `findDocumentVersionDetailOrFail` | array |
| `DocumentVersionService.php:154` | `listDocumentVersions` | list<array> |
| `DocumentService.php:431` | `creationOptionsForModule` | list<array> |
| `DocumentService.php:553` | `templateVersionStatus` | array |
| `DocumentService.php:1442` | `getDocumentReviewerPool` | array |
| `DocumentReviewService.php:43` | `listReviews` | Collection<DocumentReview> |
| `TemplateReviewService.php:34/186/260` | submit/approve/rejectReview | `Template` Eloquent |
| `TemplateContextResolver.php:28` | `resolve` | array |

(Precedente del patrón correcto: DTOs `final readonly` de Comments/Themes; inconsistencia ya conocida en ProcessService.)

## 4. Capa HTTP — duplicaciones y divergencias

### 4.1 Duplicaciones
- `parseFavoriteIds()` **byte-idéntico**: `ListTemplatesRequest.php:57-64` = `ListDocumentsRequest.php:56-63` → trait.
- CSP string **triplicado**: `TemplatePreviewController.php:43` = `DocumentPreviewController.php:42` = `ThemePreviewController.php:37` → `PreviewHeaders::CSP`.
- `Submit*ForReviewRequest` 95% (solo difieren modelo, ability `submitForReview` vs `submit`, route param) → trait `ValidatesSubmissionChangelog`.
- `StartNew*RevisionRequest` 100% salvo trait usado — ya bien resuelto vía `Resolves*ForAuthorization` (estos dos traits son ~90% duplicados entre sí; el fallback usa nombres inconsistentes: `findOrFailWithoutCatalogScope` vs `findModelOrFailWithoutUserAccess`).
- Try/catch de resolución con fallback a snapshot publicado: 2x en `DocumentVersionController.php:32-39,59-65` + `DocumentExportController.php:143-158` (3 copias) → `DocumentServiceInterface::resolveWithPublishedFallback()`.
- `Clone*Request`: 100% duplicados entre sí.

### 4.2 Divergencias funcionales (anotar en changes.md si se igualan)
| Divergencia | Template | Document |
|---|---|---|
| `process_id` validación | `exists:processes,id` | sin `exists:` |
| `study_type_id/study_id/module_id` tipo | `string` | `uuid` |
| `sortBy` default | `updated_at` | `created_at` |
| Respuestas de mutación de estado | `Resource::response()` (wrap automático) | `response()->json(['data'=>...])` manual — wire format distinto |
| `destroy()` | 204 si hard-delete / Resource si archivado | siempre 204 |
| `store()/clone()` | Resource sin blocks | json manual con blocks inline (asimetría correcta por diseño) |
| `authorize()` publish | sí autoriza | `return true` |
| Timeout WeasyPrint | 60s | 30s (theme) |

### 4.3 Resources
`TemplateResource` vs `DocumentResource`: ~23 campos idénticos. **No unificar por herencia** — serialización plana deliberada, cambia junto a su DTO. Sí extraer: parser de snapshot (`TemplateSnapshotParser`) y resolución de nombres de usuario.

## 5. DTOs, Repositories y Models

### 5.1 DTOs
| Par | Overlap | Acción |
|---|---|---|
| `DocumentVersionBlockLayerDto` vs `TemplateVersionBlockLayerDto` | **100% estructural** (solo nombres de FK) | P1: `DTOs/Versioning/VersionBlockLayerDto` genérico (`versionId`, `blockId`) |
| `UpdateDocumentDto` (changedFields+has) vs `UpdateTemplateDto` (26 params `set*: bool`) | 50% campos, 0% patrón | P6: adoptar `changedFields` (Fase 7, opcional, ALTO) |
| `TemplateFilterDto` vs `FilterTemplatesDto` | 55% — DOS filter DTOs de template (paginado vs listado admin) | consolidar naming/responsabilidad |
| `DocumentVersionSnapshotDto` (`documentId`, `snapshotData`) vs `EntityVersionSnapshotDto` (`entityId`, `blocksSnapshotRows`) | conceptos gemelos, forma distinta | homogeneizar en Fase 4.3 |
| Create/Presentation DTOs | 45-65% | no unificar estructura; sí convenciones |

Inconsistencias: snake_case en `BlockUpdateDto`, `UpdateTemplateBlockDto`, `BulkUpdateTemplateBlocksDto` (C1); `TemplateBlockPayloadDto` mal ubicado en `DTOs/Templates/` (C3); `EntityVersionSnapshotDto` genérico viviendo en `DTOs/Templates/` (C4); `DocumentRenderDataDto` mutable vs `TemplateRenderDto` readonly (C6).

### 5.2 Repositories
- `*VersionBlockLayerRepository`: **100% overlap** → P2 repo genérico parametrizado (modelClass, versionFk, blockFk). 140→70 líneas.
- `DocumentRepository` vs `TemplateRepository`: `transaction()` byte-idéntico (también en `EntityVersionRepository`); `updateHeadVersionChangelog`/`clearHeadVersionChangelog` idénticos; `markFavorite/unmarkFavorite` idénticos; `minPendingReviewStage*` ~95%; `normalizeHeadSnapshotUpdates()` ~80% → P3 `AbstractVersionableEntityRepository`.
- `findBlocksAsPayloadDtosFor*` implementados pero **no declarados en interfaces** (C7/P9) — los services dependen de la implementación concreta.
- `TemplateVersionRepository` = thin wrapper de `EntityVersionRepository` (7 delegaciones + 1 assert) → P10 adelgazar/eliminar. `DocumentVersionRepository` se justifica (tabla legacy `document_versions` propia).

### 5.3 Models
- Creating hook `DB::table('entity_versions')->insert()` 95% idéntico: `Document.php:157-173` vs `Template.php:145-161` → P4 trait `HasEntityVersionHead` (ojo: aliases de scope distintos `document_head_ev`/`template_head_ev` referenciados por el global scope `user_access`).
- `applyAcademicOverlapForTableAlias` ~idéntico: `Document.php:249-297` vs `Template.php:267-315` → P5 trait `HasAcademicOverlapScope` (difiere solo la clase `*HeadSnapshot`).
- Deleting hook `Comment::where()->delete()` 100% idéntico: `DocumentBlock.php:47-50` = `TemplateBlock.php:77-80` → trait.
- `*VersionBlockLayer` Pivots 95% idénticos (solo tabla/FKs).
- Único trait compartido hoy: `HasCommentingStatus`.

## 6. Cobertura de tests relevante
- Feature tests de API son el oráculo de wire format (`*SortSearchTest`, `*PermissionsTest`, `DocumentDocxExportTest`, `DocumentRenderForVersionTest`, `ThemePreviewTest`...).
- `tests/Unit/Services/TemplateVersionBlockLayerResolverTest.php` existe — extender en Fase 4.3.
- Riesgo de refactor ALTO en: `TemplateService` (clone de reviewers), `Template/DocumentReviewService` (máquina de estados + broadcast side-effects).
- Correr siempre `pest --no-coverage` con Unit y Feature por separado (OOM 128M con pcov).
