# Audit — maya_dms

Generated: 2026-05-15T00:00:00Z
Auditor: maya-architecture-auditor

## Compliance summary

| Layer | Total checks | Passing | Failing |
|-------|-------------|---------|---------|
| Backend B1–B9 | 9 | 5 | 4 |
| Frontend F1–F5 | 5 | 3 | 2 |
| Events/Audit E1–E5 | 5 | 2 | 3 |

## Backend violations

### B4 — Services return Eloquent models instead of DTOs [HIGH]

`DocumentServiceInterface` and `TemplateServiceInterface` declare return types as Eloquent models:

- `app/Services/Contracts/DocumentServiceInterface.php` — `findOrFail(): Document`, `create(): Document`, `clone(): Document`, `update(): Document`
- `app/Services/Contracts/TemplateServiceInterface.php` — same pattern with `Template` model returns

The DTO class (`app/DTOs/Documents/DocumentDto.php`) exists and is used, but only inside `DocumentResource` via `DocumentDto::fromModel()`. The service→controller boundary exposes raw Eloquent models. The conversion should happen at the service layer, not the resource layer.

### B7 — response()->json() bypasses Resource layer [MEDIUM]

- `app/Http/Controllers/Api/ReviewController.php` — `index()` returns `response()->json(['data' => $reviews])` instead of `ReviewResource::collection($reviews)`
- `app/Http/Controllers/Api/UserController.php` — `index()` returns `response()->json(['data' => $users])` instead of a UserResource collection
- `app/Http/Controllers/Api/CommentController.php` — wraps `CommentResource::collection(...)` inside `response()->json(['data' => ...])` instead of returning the Resource directly

### B8 — Listings without paginate() [MEDIUM]

- `app/Repositories/UserRepository.php` — `findAll()` uses `->get()` without pagination; the `UserController::index()` endpoint is a listing endpoint and should paginate

### B9 — Controllers exceed size limits [LOW]

- `app/Http/Controllers/Api/DocumentController.php` — 363 LOC, 14 public methods (limit: 200 LOC / 5 methods): `index`, `store`, `clone`, `creationOptions`, `createFromModule`, `templateVersionStatus`, `show`, `update`, `destroy`, `submit`, `publish`, `startNewVersion`, `destroyVersion`, `delegate`
- `app/Http/Controllers/Api/TemplateController.php` — 364 LOC, 16 public methods (limit: 200 LOC / 5 methods)

Recommended split: extract state-transition methods (`submit`, `publish`, `startNewVersion`, `destroyVersion`) into `DocumentStateController` and `TemplateStateController`.

## Frontend violations

### F1 — useEffect + useState + fetch triplets [HIGH]

Four components bypass TanStack Query (`createDataHook`) in favor of manual fetch patterns:

- `frontend/src/pages/ProcesosPage.tsx:24-52` — `useEffect + useState + fetchProcesses()` with manual loading/error state
- `frontend/src/features/hierarchy/hooks/useAcademicHierarchyLoad.ts:17-65` — `useEffect + useState + fetchAcademicHierarchy()` with module-level manual cache (anti-pattern even with caching)
- `frontend/src/features/dashboard/widgets/PendingValidationsWidget.tsx:6-35` — `useEffect + useState + fetchDashboard()` with manual loading state
- `frontend/src/features/templates/components/TemplateReviewView.tsx:118,122-128,158-169` — three separate `useEffect` blocks calling `apiFetchJson`, `fetchTemplateVersion`, and `fetchProcesses` with manual state management

All four should use `createDataHook` from `@maya/shared-auth-react` (as `useDocuments.ts` and `useFavoritesIds.ts` already do).

### F3 — `any` in application code [HIGH]

- `frontend/src/utils/blockNoteRepair.ts` — function returns `any[]`, parameters typed as `block: any`, `c: any`
- `frontend/src/pages/DocumentPreviewPage.tsx:432,968,1169,1196` — `b: any`, `block: any` in multiple map callbacks
- `frontend/src/pages/TemplatePreviewPage.tsx:531,534,537,564,577,614,638` — `t: any`, `s: any`, `m: any`, `block: any` throughout
- `frontend/src/features/documents/components/DocumentWizard.tsx:585,592,593,616,620` — `t: any`, `s: any` in render callbacks
- `frontend/src/features/templates/components/TemplateReviewView.tsx:78` — `(user as any)?.id` cast

The block-related `any` types likely reflect the BlockNote editor's own type exports being incomplete or not imported. The fix is to import BlockNote's `Block` type rather than using `any`.

## Eventos / Audit (E1–E5)

### E1 — Observer pattern with #[ObservedBy]

| Status | Detail |
|--------|--------|
| FAIL (CRITICAL) | No `#[ObservedBy]` attribute found on `Document`, `Template`, `DocumentVersion`, or `TemplateVersion` models. State transitions are dispatched as Laravel events (DocumentStateChanged, TemplateStateChanged) handled by Listeners, NOT by Model Observers. The E1 rule requires `#[ObservedBy(DocumentObserver::class)]` on each audited model. |

No `app/Observers/` directory exists. The current Listener-based approach works but diverges from the canonical Observer pattern defined in the architecture rules.

### E2 — Events implement AuditableEvent interface

| Status | Detail |
|--------|--------|
| FAIL (CRITICAL — PREREQUISITE) | `AuditableEvent` interface does NOT exist in `packages/maya-shared-messaging-laravel/src/`. It must be created there first before any project can comply. Existing events (`DocumentStateChanged`, `TemplateStateChanged`) have no `toAuditPayload(): array` method and implement no such interface. Once the interface is added to the shared package, all domain events in maya_dms must be updated to implement it. |

Files to update once prerequisite is resolved:
- `backend/app/Events/DocumentStateChanged.php`
- `backend/app/Events/TemplateStateChanged.php`

### E3 — Services must not call AuditPublisher directly

| Status | Detail |
|--------|--------|
| FAIL (HIGH) | Two services inject and call `AuditPublisher::publish()` directly instead of dispatching domain events. |

- `app/Services/DocumentReviewService.php:73` — calls `$this->auditPublisher->publish(...)` for `review_approved`
- `app/Services/DocumentReviewService.php:146` — calls `$this->auditPublisher->publish(...)` for `review_rejected`
- `app/Services/TemplateBlockService.php:115` — calls `$this->auditPublisher->publish(...)` for `blocks_reordered`
- `app/Services/TemplateBlockService.php:138` — calls `$this->auditPublisher->publish(...)` for `block_created`
- `app/Services/TemplateBlockService.php:197` — calls `$this->auditPublisher->publish(...)` for `block_state_changed`
- `app/Services/TemplateBlockService.php:223` — calls `$this->auditPublisher->publish(...)` for `block_deleted`
- `app/Services/TemplateBlockService.php:264` — calls `$this->auditPublisher->publish(...)` for bulk block update

Compliant services for comparison: `DocumentStateService` dispatches `event(new DocumentStateChanged(...))` and `TemplatePublishingService` dispatches `event(new TemplateStateChanged(...))` — both delegate to Listeners correctly.

Fix: create `DocumentReviewApproved`, `DocumentReviewRejected`, `BlockCreated`, `BlockDeleted`, `BlockStateChanged`, `BlocksReordered` events; move `AuditPublisher` calls to dedicated Listeners implementing `ShouldHandleEventsAfterCommit`.

### E4 — Observer wasChanged guard

| Status | Detail |
|--------|--------|
| N/A | No Observers exist (E1 not implemented). Cannot evaluate wasChanged guard pattern. |

### E5 — No duplicate listeners / wildcard RecordAuditableEvent

| Status | Detail |
|--------|--------|
| PASS (with caveat) | `RecordAuditableEvent` wildcard listener does not exist in `maya-shared-messaging-laravel`. Existing listeners (`RecordDocumentStateChange`, `RecordTemplateStateChange`, `RecordSegregationOfDutiesDenial`) are specific and non-duplicated. Once the wildcard listener is added to the shared package (prerequisite for E2), the specific document/template state listeners will become redundant and should be removed. |

### E1–E5 Summary table

| Rule | Status | Severity |
|------|--------|----------|
| E1 — ObservedBy on models | FAIL | CRITICAL |
| E2 — AuditableEvent interface (prerequisite missing in shared package) | FAIL | CRITICAL |
| E3 — Services must not call AuditPublisher directly | FAIL | HIGH |
| E4 — wasChanged guard in Observers | N/A | — |
| E5 — No duplicate listeners | PASS (caveat) | — |

## Extraction candidates

### Already extracted but consumed locally (HIGH — fix immediately)

- `backend/app/Http/Controllers/Api/HealthCheckController.php` — extends the local `App\Http\Controllers\Controller` with its own `HealthCheckService`. Both `maya_authorization` and `maya_logs` already extend `Maya\Http\Controllers\AbstractHealthCheckController` from `maya-shared-http-laravel`. maya_dms should do the same and delete the local implementation.

### New candidates (cross-project evidence)

- `backend/app/Events/DocumentStateChanged.php` / `TemplateStateChanged.php` — the pattern of a plain Laravel event with `Dispatchable` + `readonly` constructor properties is identical across maya_dms, maya_authorization, and maya_logs. The `AuditableEvent` interface (prerequisite) belongs in `maya-shared-messaging-laravel` to allow all projects to implement it uniformly.

### Local-only patterns (no extraction needed)

- `frontend/src/features/hierarchy/` — academic hierarchy data is specific to maya_dms document creation flows; keep local
- `frontend/src/features/templates/` — template block editor (BlockNote integration) is maya_dms-specific; keep local
- `backend/app/Services/TemplateBlockService.php` — block lifecycle management is unique to maya_dms; keep local
- `backend/app/Services/DocumentReviewService.php` — segregation-of-duties review workflow is unique to maya_dms; keep local

## Statistics

- Controllers audited: 9 (Document, Template, Review, Comment, User, Block, Module, HealthCheck, TemplateVersion)
- Services audited: 8 (DocumentService, TemplateService, DocumentStateService, TemplatePublishingService, DocumentReviewService, TemplateBlockService, UserService, CommentService)
- Repositories audited: 6 (DocumentRepository, TemplateRepository, UserRepository, CommentRepository, BlockRepository, ModuleRepository)
- DTOs found: 3 (DocumentDto, TemplateDto, TemplateVersionDto)
- FormRequests found: 14
- Events found: 2 (DocumentStateChanged, TemplateStateChanged)
- Listeners found: 3 (RecordDocumentStateChange, RecordTemplateStateChange, RecordSegregationOfDutiesDenial)
- Observers found: 0
- Frontend components: ~45 (features/documents + features/templates + features/dashboard + pages)
- Frontend hooks: ~12 (useDocuments, useFavoritesIds, useAcademicHierarchyLoad, and others)

## Notes

**B4 boundary decision**: The architecture decision to do DTO conversion inside the Resource rather than the Service is pragmatic (it avoids double-mapping) but inverts the dependency. The canonical pattern requires the Service to return DTOs so that the controller is never aware of Eloquent internals. The current approach means if a Service method is called from another Service (not a Controller), the caller receives a raw Eloquent model. Recommend updating service interface return types to DTOs and removing the `instanceof DocumentDto` branch from `DocumentResource`.

**E1 vs Listener pattern**: The current Listener-based audit pattern (event → Listener → AuditPublisher) is architecturally sound and functionally equivalent to the Observer pattern for state-change auditing. The E1 rule specifying `#[ObservedBy]` Observers may be reconsidered for maya_dms given the event-driven complexity of document/template state machines — Listeners are arguably more appropriate here than Model Observers. Raise with the architecture council before enforcing E1.

**TemplateBlockService E3**: The block lifecycle (create, reorder, delete) currently has no domain events. The AuditPublisher calls are embedded deep in the service. This is the highest-priority E3 fix: introduce `BlockLifecycleEvent` or per-action events, and move the AuditPublisher calls to a `RecordBlockLifecycle` listener with `ShouldHandleEventsAfterCommit`.

**B9 split recommendation**: `DocumentController` and `TemplateController` both have 14–16 methods because they mix CRUD with state machine transitions. A clean split:
- `DocumentController` → CRUD only (index, store, show, update, destroy, clone)
- `DocumentStateController` → transitions (submit, publish, startNewVersion, destroyVersion)
- `DocumentOptionsController` → creation helpers (creationOptions, createFromModule, templateVersionStatus, delegate)
Same pattern for Template.
