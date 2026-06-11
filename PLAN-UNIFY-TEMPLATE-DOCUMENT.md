# Plan de Implementación: Unificación Templates ↔ Documents (maya_dms/backend)

> Rama: `refactor/unify-template-document` sobre `develop` de `maya_dms`.
> Frontend NO se toca. Todo DMS-local — 0 paquetes maya_platform nuevos (decisión architect vinculante).
> Stack: Laravel 13 / PHP 8.4 · Pest contra Postgres real · arquitectura obligatoria
> Controller(Resources, Requests) → Service(DTOs, sin Eloquent) → Repository(modelos) → BD.
>
> Análisis de soporte: [ANALYSIS-UNIFY-TEMPLATE-DOCUMENT.md](./ANALYSIS-UNIFY-TEMPLATE-DOCUMENT.md)
> Registro de cambios funcionales: [changes.md](./changes.md)

## Resumen ejecutivo

El análisis (4 agentes, consolidado) identificó tres clases de deuda entre los dominios **Templates** y **Documents**:

1. **Duplicación de lógica** en Services, Requests, DTOs, Repositories y Models (12 clusters `D-*`, 10 propuestas `P*`, varios traits HTTP).
2. **Violaciones de arquitectura**: 1 CRITICAL (`DB::table('template_reviewers')` directo en Service), 19 sitios Eloquent en Services, varios Controllers/Requests saltándose la capa Service, 11 métodos de Service devolviendo `array`/Eloquent en lugar de DTO.
3. **Divergencias funcionales** entre dominios espejo que, al unificarse, podrían alterar comportamiento observable (timeouts, validaciones, `authorize()`, defaults de sort, wire format). Cada igualación se documenta en `changes.md`.

El plan se ordena por **riesgo creciente**. Las fases tempranas (0–2) son extracciones mecánicas de bajo riesgo y corrección de la violación CRITICAL; las intermedias (3–5) abstraen jerarquías de Services/Repos/Models; las tardías (6–7) tocan contratos y patrones PATCH de alto blast-radius. Cada fase produce **commits atómicos verificables** con `pest --no-coverage` (Unit y Feature por separado por el OOM 128M).

**Decisión SHARED vs DMS-ONLY (vinculante)**: ninguna abstracción nuclear tiene hoy un 2º consumidor fuera de maya_dms (`entity_versions`, snapshots, workflow de estados, comentarios anclados → 0 matches en dashboard/audit/authz/logs). Por tanto **todo se unifica como abstracción local en maya_dms**; solo se consume lo ya publicado: `Maya\Editor\TiptapHtmlRenderer` (shared-editor-laravel) y `RespondsWithEnvelope`/`PaginatedDto`/`FilterDto` (shared-http-laravel). Watch-item para futura extracción: `EntityVersionReconstructionService` (algoritmo puro, candidato limpio cuando exista 2º consumidor y contrato estable ≥1 release).

**Política de asimetrías**: las asimetrías justificadas (`renderHtmlForVersion`, `DocumentPdfService::generate` persistente, migración de docs, `backfillStructuralFields`, broadcast realtime, `startNewRevisionCycle`/D-12) **NO se unifican**. `PublishDocumentRequest::authorize()` es un cambio de seguridad deliberado: se decide en Fase 6 y se anota en `changes.md`.

### Rutas reales verificadas (namespaces base)

| Tipo | Ruta |
|---|---|
| Services | `backend/app/Services/` (+ `Contracts/`, `Concerns/`) |
| DTOs | `backend/app/DTOs/{Templates,Documents,TemplateBlocks,Versioning,...}/` |
| Repositories | `backend/app/Repositories/{Contracts,Eloquent}/` |
| Models | `backend/app/Models/` (+ `Concerns/`) |
| Requests | `backend/app/Http/Requests/{Templates,Documents}/` (+ `Concerns/`) |
| Resources | `backend/app/Http/Resources/` |
| Support | `backend/app/Support/` |
| Tests | `backend/tests/{Unit,Feature}/` (Pest) |

---

## Fase 0 — Baseline + andamiaje (BLOQUEANTE, no paralelizable)

**Objetivo**: capturar el baseline de tests (hay fails preexistentes conocidos en `develop`), crear la rama y el protocolo `changes.md`. Sin esto no se puede distinguir regresión de fallo preexistente.

| # | Tarea | Archivos | Agente | Done |
|---|---|---|---|---|
| 0.1 | Crear rama `refactor/unify-template-document` desde `develop` | — | (manual) | ✅ hecha |
| 0.2 | Ejecutar y **guardar** baseline: `pest --no-coverage tests/Unit` y `pest --no-coverage tests/Feature` por separado, dentro del contenedor backend del slot | — | (manual) | `baseline-unit.txt` / `baseline-feature.txt` con pass/fail/skip por nombre de test |
| 0.3 | Crear `changes.md` con cabecera + protocolo (ver abajo) | `maya_dms/changes.md` | (manual) | ✅ hecho |
| 0.4 | Commit inicial de tracking de la rama | — | (manual) | `chore: plan + baseline refactor/unify-template-document` |

**Criterio de done de la fase**: baseline reproducible; cualquier fase posterior compara contra estos dos ficheros, no contra "todo verde".
**Riesgo**: N/A (no toca código de producción).
**changes.md generado**: ninguno (crea el archivo).

> ⚠️ **Seguridad de la BD de tests**: NUNCA correr la suite contra el Postgres de runtime del slot. `tests/bootstrap.php` fuerza `DB_DATABASE=maya_dms_test`. Verificar antes de 0.2.

---

## Fase 1 — Extracciones mecánicas de bajo riesgo (alta paralelización)

**Objetivo**: extraer helpers idénticos/casi-idénticos sin tocar comportamiento. Cada tarea es independiente en archivos → máxima paralelización.

| # | ID hallazgo | Objetivo | Archivos afectados | Nueva clase | Agente | Riesgo |
|---|---|---|---|---|---|---|
| 1.1 | D-01 | Extraer `payloadsEqual` + `normalizeForCompare` (100% idéntico) | `Services/TemplateVersionBlockLayerWriter.php:100-118`, `Services/DocumentVersionBlockLayerWriter.php:101-115` | `Support/BlockLayerPayloadComparator.php` | tdd-guide | BAJO |
| 1.2 | HTTP | Extraer `parseFavoriteIds()` (byte-idéntico) a trait | `Requests/Templates/ListTemplatesRequest.php:57-64`, `Requests/Documents/ListDocumentsRequest.php:56-63` | `Requests/Concerns/ParsesFavoriteIds.php` | refactor-cleaner | BAJO |
| 1.3 | HTTP | Centralizar CSP string triplicado | `DocumentPreviewController`, `TemplatePreviewController`, `ThemePreviewController` | `Support/PreviewHeaders.php` (const `CSP`) | refactor-cleaner | BAJO |
| 1.4 | HTTP | Trait `ValidatesSubmissionChangelog` (95% idéntico) | `Requests/Templates/SubmitTemplateForReviewRequest.php`, `Requests/Documents/SubmitDocumentForReviewRequest.php` | `Requests/Concerns/ValidatesSubmissionChangelog.php` | refactor-cleaner | BAJO |
| 1.5 | Models | Trait para `deleting` hook `Comment::where()->delete()` (100% idéntico) | `Models/DocumentBlock.php:47-50`, `Models/TemplateBlock.php:77-80` | `Models/Concerns/PurgesBlockComments.php` | tdd-guide | BAJO |
| 1.6 | P7/P8 | Mover `TemplateBlockPayloadDto` → `DTOs/TemplateBlocks/`, `EntityVersionSnapshotDto` → `DTOs/Versioning/` | `DTOs/Templates/TemplateBlockPayloadDto.php`, `DTOs/Templates/EntityVersionSnapshotDto.php` + imports | — | refactor-cleaner | BAJO |
| 1.7 | C1 | Renombrar campos snake_case → camelCase en DTOs | `DTOs/Documents/BlockUpdateDto.php`, `DTOs/TemplateBlocks/UpdateTemplateBlockDto.php`, `DTOs/TemplateBlocks/BulkUpdateTemplateBlocksDto.php` + call sites | — | refactor-cleaner | BAJO |

**Criterio de done por tarea**: clase/trait creado con test unitario propio (1.1, 1.5); call sites migrados; baseline Unit+Feature sin **nuevas** regresiones.
**Paralelización**: 3 agentes paralelos → A:{1.1, 1.5}, B:{1.2, 1.3, 1.4}, C:{1.6, 1.7} (1.6/1.7 tocan ambos `DTOs/` → mismo agente para evitar colisión de imports).
**changes.md generado**: ninguno (cero cambio de comportamiento; son extracciones puras).
**Gate de fase**: `code-reviewer` sobre el diff completo de Fase 1.

---

## Fase 2 — Violación CRITICAL + saneo Eloquent en Services (riesgo BAJO-MEDIO, paralelización parcial)

**Objetivo**: eliminar accesos directos a la BD/Eloquent desde Services, empezando por el CRITICAL.

| # | ID hallazgo | Objetivo | Archivos | Repo/clase destino | Agente | Riesgo |
|---|---|---|---|---|---|---|
| 2.1 | **CRITICAL** | Mover `DB::table('template_reviewers')` a repositorio | `Services/TemplateService.php:255-261, 1204-1208` | nuevo `Repositories/{Contracts,Eloquent}/TemplateReviewerRepository` | tdd-guide → security-reviewer | MEDIO |
| 2.2 | 19-sitios | `TemplateService` reviewers `create/forceDelete` | `Services/TemplateService.php:619-779` | `TemplateReviewerRepository` (de 2.1) | tdd-guide | MEDIO |
| 2.3 | 19-sitios | `TemplateReviewService` Eloquent (líneas 48-295) | `Services/TemplateReviewService.php` | repos existentes + `TemplateReviewerRepository` | tdd-guide | MEDIO |
| 2.4 | 19-sitios | `TemplatePublishingService:89`, `TemplateReviewerAssignmentService:39` | esos archivos | repos correspondientes | refactor-cleaner | BAJO |
| 2.5 | 19-sitios | `SnapshotService:79-118` load/map | `Services/SnapshotService.php` | `EntityVersionRepository` / nuevo método | tdd-guide | MEDIO |
| 2.6 | 19-sitios | `DocumentService:1914` `comments()->exists` | `Services/DocumentService.php` | `CommentRepository` | refactor-cleaner | BAJO |
| 2.7 | 19-sitios | `DocumentRenderService:151`, `DocumentPdfService:135-138` | esos archivos | repos correspondientes | refactor-cleaner | BAJO |
| 2.8 | 19-sitios | `DocumentMigrationPayloadResolver:80-86` | ese archivo | `EntityVersionRepository`/`DocumentVersionRepository` | refactor-cleaner | BAJO |

**Criterio de done por tarea**: 0 `DB::`/`::query()`/Eloquent directo en el Service tocado (verificar con grep); método nuevo del repo cubierto por test; baseline sin nuevas regresiones.
**Dependencias internas**: 2.1 → {2.2, 2.3} (mismo repo nuevo).
**Paralelización**: bloque Template A:{2.1→2.2→2.3, 2.4, 2.5} ∥ bloque Document B:{2.6, 2.7, 2.8} — 2 agentes, uno por dominio, sin solape de archivos.
**changes.md generado**: ninguno esperado. Si algún método de repo cambia orden de resultados o manejo de `null`, anotar.
**Gate de fase**: `security-reviewer` obligatorio (toca auth/reviewers) + `code-reviewer`.

---

## Fase 3 — Saneo de capa en Controllers/Requests/Resources (riesgo MEDIO, paralelización parcial)

**Objetivo**: eliminar lógica de negocio y Eloquent de Controllers, Requests y Resources, delegando a Services/Repos. Aquí aparecen las **primeras divergencias funcionales** a anotar.

| # | ID hallazgo | Objetivo | Archivos | Agente | Riesgo | changes.md |
|---|---|---|---|---|---|---|
| 3.1 | Controllers | `DocumentPreviewController` inyecta repo → pasar por Service (`findForPreview`) | `DocumentPreviewController` | tdd-guide | MEDIO | no |
| 3.2 | Controllers | `DocumentBlockController::destroy` repo directo + `setRelation` → Service | `DocumentBlockController` | tdd-guide | MEDIO | no |
| 3.3 | Controllers | `ThemeController:64,71` y `ThemePreviewController:60` `Theme::query()` → patrón `modelForPolicy` ya existente / `ThemeService` | esos controllers | refactor-cleaner | BAJO | no |
| 3.4 | Requests | `StoreDocumentRequest` 4 queries Eloquent (duplicadas entre `authorize()` y `withValidator()`) → resolución cacheada vía Service | `Requests/Documents/StoreDocumentRequest.php` | tdd-guide | MEDIO | posible (ver 3.9) |
| 3.5 | Requests | `CloneTemplate/CloneDocumentRequest:17` `findOrFail` directo → traits `Resolves*ForAuthorization` existentes | esos requests | refactor-cleaner | BAJO | no |
| 3.6 | Requests | `RejectDocumentReviewRequest:89-94` query `Comment` → `CommentRepository` | ese request | refactor-cleaner | BAJO | no |
| 3.7 | Resources | `TemplateVersionResource:71`, `TemplateVersionSummaryResource:95` `DB::table('users')` + parser snapshot duplicado | esos resources | tdd-guide | MEDIO | no |
| 3.8 | Controllers | `TemplateController::show()` lógica inline (97-115) → `TemplateService::resolveTemplateViewerContext` espejo de `DocumentService::resolveDocumentViewerContext` | `TemplateController`, `TemplateService` | tdd-guide | MEDIO | **sí** si cambia wire format de `show` |
| 3.9 | Divergencias | Documentar (no necesariamente igualar): `process_id` sin `exists:` en Documents; `study_type_id` string vs uuid | `StoreDocumentRequest`, requests de listado | code-reviewer (decisión) | — | **sí si se igualan** |

**Para 3.7**: extraer `Support/TemplateSnapshotParser.php` (parser JSONB duplicado) + resolver nombres de usuario vía `UserDirectoryService`/trait. El Resource no debe tocar la BD.

**Criterio de done**: Controllers/Requests/Resources sin Eloquent ni `DB::` (grep); flujo HTTP idéntico salvo lo anotado en `changes.md`.
**Paralelización**: 3 agentes — A:{3.1, 3.2, 3.4, 3.6}, B:{3.3}, C:{3.5, 3.7, 3.8}. ⚠️ 3.8 toca `TemplateService.php` igual que Fase 2 (2.1/2.2) → secuenciar con Fase 2 o mismo agente.
**Gate de fase**: `code-reviewer` + `security-reviewer` (3.4, 3.5, 3.6 tocan validación/autorización).

---

## Fase 4 — Abstracción de Services duplicados (riesgo MEDIO)

**Objetivo**: introducir abstracciones/colaboradores compartidos para los clusters D de duplicación MEDIA. Depende de Fase 1 (helpers extraídos) y Fase 2 (Services sin Eloquent directo).

| # | ID hallazgo | Objetivo | Archivos | Nueva clase | Agente | Riesgo | changes.md |
|---|---|---|---|---|---|---|---|
| 4.1 | D-05 | Unificar runner WeasyPrint (95%) | `TemplatePdfService.php:28-52`, `ThemePdfService.php:29-52`, `DocumentPdfService::runWeasyprint` | `Support/WeasyPrintRunner.php` | tdd-guide | BAJO | **SÍ** — timeout 60 vs 30 + mensaje de error se igualan |
| 4.2 | D-11 | `notifyValidationRequested` (85%) con flag broadcast | `TemplateReviewService.php`, `DocumentReviewService.php` | `Support/ReviewValidationNotifier.php` (param `broadcast: bool`) | tdd-guide | BAJO | no (broadcast queda condicional) |
| 4.3 | D-03/D-04 | `effectiveBlockPayload` + `blockFromSnapshotOnly` (90-95%) | ambos `*VersionBlockLayerResolver.php` | `Services/Concerns/AbstractBlockLayerResolver.php` + homogeneizar snapshot DTOs (`blocksSnapshotRows` vs `snapshotData['blocks']`) | tdd-guide | MEDIO | posible si cambia forma de snapshot leída |
| 4.4 | D-02 | `syncLayersForNewPublication` (85%) | ambos `*VersionBlockLayerWriter.php` | `Services/Concerns/AbstractBlockLayerWriter.php` (usa `BlockLayerPayloadComparator` de 1.1) | tdd-guide | MEDIO | no |
| 4.5 | D-06 | `normalizeUpdateAttributes` (85%) | `TemplateService.php:1069-1178`, `DocumentService.php:889-1014` | `Support/AcademicScopeNormalizer.php` (match `TemplateVisibilityLevel` idéntico) | tdd-guide | MEDIO | posible si cambia normalización de scopes nulos |
| 4.6 | D-07 | `destroyVersion` (80%) | `TemplateService.php:497-548`, `DocumentService.php:1074-1121` | `Services/EntityVersionDestroyService.php` | tdd-guide | MEDIO | posible (orden de borrado / cascada) |

**Decisión vinculante**: D-12 (`startNewRevisionCycle`, 75%, fuentes de snapshot divergentes) **NO se unifica** — riesgo ALTO. Documentar la decisión en `changes.md` como "asimetría preservada".

**Criterio de done**: lógica única tras la abstracción; tests de resolvers/writers (existe `tests/Unit/Services/TemplateVersionBlockLayerResolverTest.php` — extender) verdes; para 4.1 test que cubra ambos timeouts post-unificación.
**Dependencias**: 4.4 usa el comparador de 1.1; 4.3 y 4.4 tocan los mismos pares de archivos → mismo agente, 4.3 antes que 4.4.
**Paralelización**: hasta 4 agentes — A:{4.1}, B:{4.2}, C:{4.3→4.4}, D:{4.5}, E:{4.6} (4.5 y 4.6 ambos tocan TemplateService+DocumentService → mismo agente o secuenciar).
**Gate de fase**: `code-reviewer` + `security-reviewer` (4.2 notificaciones, 4.5 scopes académicos = autorización).

---

## Fase 5 — Abstracción de Repositories y Models (riesgo BAJO-MEDIO)

**Objetivo**: parametrizar/abstraer repos y models espejo. Depende de Fase 2 (repos como única vía de datos).

| # | ID hallazgo | Objetivo | Archivos | Nueva clase | Agente | Riesgo |
|---|---|---|---|---|---|---|
| 5.1 | P1 | `VersionBlockLayerDto` genérico (100% overlap estructural) | `DTOs/Templates/TemplateVersionBlockLayerDto.php`, `DTOs/Documents/DocumentVersionBlockLayerDto.php` | `DTOs/Versioning/VersionBlockLayerDto.php` | refactor-cleaner | BAJO |
| 5.2 | P2 | Repo genérico parametrizado (140→70 líneas) | ambos `*VersionBlockLayerRepository.php` | `Repositories/Eloquent/VersionBlockLayerRepository.php` parametrizado (modelClass, versionFk, blockFk) | tdd-guide | BAJO |
| 5.3 | P3 | Abstract `VersionableEntityRepository` (transaction, updateHeadVersionChangelog, markFavorite, minPendingReviewStage ~95%) | `{Template,Document}Repository.php` | `Repositories/Eloquent/AbstractVersionableEntityRepository.php` | tdd-guide | MEDIO |
| 5.4 | P4 | Trait `HasEntityVersionHead` (creating hook insert 95% idéntico) | `Models/Document.php:157-173`, `Models/Template.php:145-161` | `Models/Concerns/HasEntityVersionHead.php` | tdd-guide | MEDIO |
| 5.5 | P5 | Trait `HasAcademicOverlapScope` (`applyAcademicOverlapForTableAlias` ~idéntico) | `Models/Document.php:249-297`, `Models/Template.php:267-315` | `Models/Concerns/HasAcademicOverlapScope.php` | refactor-cleaner | BAJO |
| 5.6 | P10 | Adelgazar/eliminar `TemplateVersionRepository` (thin wrapper: 7 delegaciones + 1 assert) | `TemplateVersionRepository.php`, su interfaz + call sites | — | refactor-cleaner | BAJO |
| 5.7 | P9 | Declarar `findBlocksAsPayloadDtosFor*` en interfaces | `Repositories/Contracts/{Document,Template}RepositoryInterface.php` | — | refactor-cleaner | BAJO |

**Criterio de done**: repos/models comparten abstracción sin cambiar SQL emitido (vigilar SQL Postgres-específico: `GREATEST`, operadores `jsonb`); `EntityVersionsModelTest`, `EntityVersionLifecycleServiceTest` verdes.
**Dependencias**: 5.1 antes de 5.2. 5.6 tras Fase 4 (que no deje nuevas dependencias sobre `TemplateVersionRepository`).
**Paralelización**: 3 agentes — A:{5.4, 5.5} (Models), B:{5.1→5.2, 5.7} (Repos+DTO), C:{5.3→5.6} (Repos abstract).
**Gate de fase**: `code-reviewer` + `database-reviewer` (SQL Postgres-específico).

---

## Fase 6 — Contratos, DTOs de retorno y decisiones de seguridad (riesgo ALTO, secuencial)

**Objetivo**: cerrar violaciones de contrato (Services devolviendo array/Eloquent) y tomar las decisiones de seguridad. Alto blast-radius → **una tarea a la vez, sin paralelización**.

| # | ID hallazgo | Objetivo | Archivos | Agente | Riesgo | changes.md |
|---|---|---|---|---|---|---|
| 6.1 | 11-DTO | Services que devuelven `array`/Eloquent → DTO | `DocumentVersionService:36,66,154`; `DocumentService:431,553,1442`; `DocumentReviewService:43`; `TemplateContextResolver:28` | tdd-guide | ALTO | **sí si cambia wire format** |
| 6.2 | 11-DTO | `TemplateReviewService` devuelve `Template` Eloquent en submit/approve/reject → DTO | `Services/TemplateReviewService.php` | tdd-guide | ALTO | **sí si cambia wire format** |
| 6.3 | HTTP | Decidir `PublishDocumentRequest::authorize()` (`return true` hoy; el Template sí autoriza con `can('publish')`) | `Requests/Documents/PublishDocumentRequest.php` | **security-reviewer** + decisión usuario | ALTO | **SÍ — cambio de seguridad, entrada CRITICAL** |
| 6.4 | HTTP | Homogeneizar nombres del fallback de resolución (`findOrFailWithoutCatalogScope` vs `findModelOrFailWithoutUserAccess`) | `Requests/*/Concerns/Resolves*ForAuthorization.php` + services | refactor-cleaner | MEDIO | no |
| 6.5 | HTTP | Consolidar try/catch fallback de `DocumentVersionController` (2x) + `DocumentExportController::resolveDocumentForHistory` (3 copias) | esos controllers + `DocumentServiceInterface::resolveWithPublishedFallback()` | refactor-cleaner | MEDIO | no |
| 6.6 | Divergencias | Decidir/documentar: `sortBy` default `updated_at` vs `created_at`; `Resource::response()` vs `response()->json(['data'=>...])` manual (wire format distinto); `destroy` 204 vs Resource | requests/controllers afectados | code-reviewer (decisión) | MEDIO | **sí si se igualan** |

**Decisión 6.3 (crítica)**: igualar el `authorize()` cambia el comportamiento de seguridad del endpoint de publicación de documentos (hoy la autorización solo se ejerce en el controller). Requiere decisión explícita del usuario antes de tocar. Si se iguala → entrada CRITICAL en `changes.md` con endpoint, política anterior y nueva.

**Criterio de done**: todo retorno público de Service es DTO o lista de DTOs, nunca Eloquent crudo; wire format verificado test-a-test contra baseline (los Feature tests de API son el oráculo).
**Paralelización**: ninguna. Secuencial con verificación de baseline entre tareas.
**Gate de fase**: `code-reviewer` + `security-reviewer` obligatorios. Revisión del usuario para 6.3.

---

## Fase 7 — Unificación del patrón PATCH (riesgo ALTO, última, OPCIONAL)

**Objetivo**: P6 — adoptar el patrón `changedFields + has()` de `UpdateDocumentDto` en `UpdateTemplateDto` (hoy 26 params `set*: bool`). Muchos call sites; máximo riesgo de regresión silenciosa en updates parciales.

| # | ID | Objetivo | Archivos | Agente | Riesgo |
|---|---|---|---|---|---|
| 7.1 | P6 | Migrar `UpdateTemplateDto` al patrón `changedFields` | `DTOs/Templates/UpdateTemplateDto.php`, `TemplateService::update`, `UpdateTemplateRequest`, todos los call sites | tdd-guide | ALTO |

**Criterio de done**: PATCH parcial conserva semántica (campo no enviado ≠ campo enviado como `null`); test específico "update parcial no borra campos ausentes"; baseline sin regresiones.
**Recomendación**: fase separable — mergeable de forma independiente o posponible. Si coste/beneficio no compensa, documentar y NO ejecutar.
**Gate**: `code-reviewer` + `security-reviewer`.

---

## Mapa de dependencias entre fases

```
Fase 0 (baseline + rama + changes.md)   ── BLOQUEANTE para todo
   │
   ├─► Fase 1 (extracciones mecánicas)         ─┐
   │                                            │ 1.1 alimenta 4.4
   ├─► Fase 2 (CRITICAL + Eloquent en Services) ─┤ repos saneados habilitan Fase 5
   │                                            │
   ├─► Fase 3 (saneo capa Controllers/Req/Res)  ─┤ (paralela a 2 salvo overlap TemplateService)
   │                                            │
   ▼                                            ▼
Fase 4 (abstracción Services) ──┐      Fase 5 (abstracción Repos/Models)
   │  (necesita 1 y 2)          │         (necesita 2)
   └──────────────┬─────────────┘
                  ▼
            Fase 6 (contratos/DTO retorno + seguridad)   ── necesita 4 y 5 estables
                  │
                  ▼
            Fase 7 (patrón PATCH, opcional)               ── última, aislada
```

---

## Matriz de paralelización de agentes

| Fase | Agentes paralelos (sin overlap de archivos) | Conflictos a coordinar |
|---|---|---|
| 0 | — (secuencial) | — |
| 1 | A:{1.1,1.5} · B:{1.2,1.3,1.4} · C:{1.6,1.7} | 1.6/1.7 ambos en `DTOs/` → mismo agente |
| 2 | A:{2.1→2.2→2.3, 2.4, 2.5} (Template) · B:{2.6, 2.7, 2.8} (Document) | 2.2/2.3 dependen del repo de 2.1 |
| 3 | A:{3.1,3.2,3.4,3.6} · B:{3.3} · C:{3.5,3.7,3.8} | 3.8 toca `TemplateService` → secuenciar con Fase 2 |
| 4 | A:{4.1} · B:{4.2} · C:{4.3→4.4} · D:{4.5→4.6} | 4.3/4.4 mismos archivos; 4.5/4.6 ambos en Template+DocumentService |
| 5 | A:{5.4,5.5} (Models) · B:{5.1→5.2, 5.7} · C:{5.3→5.6} | 5.1 antes de 5.2; 5.6 tras Fase 4 |
| 6 | **ninguna** (secuencial, riesgo wire format) | 6.1/6.2 Resources compartidos |
| 7 | **ninguna** (aislada) | — |

**Cross-fase**: Fase 2 (Services) ∥ Fase 3 (Controllers/Requests/Resources) es la mayor oportunidad de paralelismo, vigilando el único punto de contacto `TemplateService.php`.

---

## Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Regresión de **wire format** al introducir DTOs de retorno (Fase 6) | ALTA | Feature tests de API como oráculo; comparar JSON test-a-test contra baseline; no mergear 6.1/6.2 sin diff de wire format |
| Cambio de seguridad en `PublishDocumentRequest::authorize()` (6.3) | ALTA | Decisión explícita del usuario + `security-reviewer` + entrada CRITICAL en `changes.md` |
| OOM 128M al correr la suite completa | MEDIA | `tests/Unit` y `tests/Feature` **por separado**, siempre `--no-coverage` |
| Correr tests contra Postgres de runtime (borraría BD viva) | CRÍTICA | Verificar `DB_DATABASE=maya_dms_test` antes de cada `pest`; `tests/bootstrap.php` lo fuerza |
| Confundir fail preexistente con regresión | MEDIA | Baseline nominal por nombre de test (0.2); comparar listas, no totales |
| SQL Postgres-específico roto al abstraer repos (5.2/5.3) | MEDIA | `database-reviewer` en Fase 5; tests de `GREATEST`/`jsonb` verdes |
| Colisión de agentes paralelos en `TemplateService.php` / `DTOs/` / Resources | MEDIA | Matriz de paralelización: mismo archivo → mismo agente |
| Unificar `startNewRevisionCycle` (D-12) por error | ALTA | Política explícita: **NO unificar**; documentado como asimetría preservada |

---

## Protocolo `changes.md`

`maya_dms/changes.md` registra **toda igualación de comportamiento divergente** introducida por el refactor. No registra refactors que preservan comportamiento.

**Formato de cada entrada**:

```markdown
## [FASE X.Y] <título corto del cambio>

- **Fecha**: YYYY-MM-DD
- **Severidad**: CRITICAL | HIGH | MEDIUM
- **Qué cambió**: comportamiento antes → después
- **Por qué**: razón de la igualación (clase compartida / decisión de seguridad)
- **Endpoint(s) afectado(s)**: método HTTP + ruta
- **Impacto en cliente**: ¿lo percibe el frontend o un consumidor API?
- **Decidido por**: agente/usuario que aprobó el cambio
```

**Entradas esperadas (mínimo)**: 4.1 (timeout WeasyPrint 30↔60 + mensaje de error), 6.3 (authorize publish — CRITICAL), y cualquier 3.8/3.9/4.3/4.5/4.6/6.1/6.2/6.6 que iguale comportamiento. Si una fase no produce entrada, no se escribe nada.

---

## Criterios de verificación global

Antes de considerar el refactor completo y mergeable a `develop`:

1. **Baseline respetado**: `pest --no-coverage tests/Unit` y `tests/Feature` (por separado, contenedor del slot) sin **ninguna regresión nueva** respecto a los baselines de Fase 0.
2. **Arquitectura limpia**: grep de `DB::table`, `DB::select`, `::query()`, `->create(`, `->forceDelete(`, `->save(` en `app/Services/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Resources/` → 0 resultados ilegítimos (Eloquent solo en `app/Repositories/Eloquent/` y `app/Models/`).
3. **Contratos DTO**: ningún método público de Service retorna `array`/Eloquent crudo de las 11 firmas listadas.
4. **Asimetrías preservadas**: `renderHtmlForVersion`, `DocumentPdfService::generate`, migración de docs, `backfillStructuralFields`, broadcast realtime, `startNewRevisionCycle` (D-12) **no fueron unificadas**.
5. **Pint**: `vendor/bin/pint --test` limpio.
6. **`changes.md` completo**: toda igualación documentada; en particular la decisión 6.3.
7. **Gates superados**: `code-reviewer` en todas las fases; `security-reviewer` en 2, 3 (parcial), 4.2/4.5, 6; `database-reviewer` en 5.
8. **Frontend intacto**: 0 archivos modificados fuera de `maya_dms/backend/` (y los .md de la raíz del repo).
9. **PR**: `git diff develop...HEAD` revisado; commits atómicos por tarea, Conventional Commits (`refactor:`, `fix:` para el CRITICAL 2.1); sin atribución de IA.
