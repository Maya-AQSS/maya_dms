# Spike — `useSharedFavorites` ↔ `useFavoritesIds` (maya_dms)

Date: 2026-05-14
Origin: `AUDIT_SUMMARY.md` §2, hypothesis that `hooks/useFavoritesIds.ts` +
`api/favorites.ts` duplicate functionality of `@maya/shared-sidebar-react`'s
`useSharedFavorites`.

## Verdict

**MANTENER LOCAL.** The two hooks model entirely different concerns. The audit
hypothesis was incorrect; there is no duplication to consolidate.

## Side-by-side comparison

| Aspect | `useSharedFavorites` (shared) | `useFavoritesIds` (local DMS) |
|---|---|---|
| Domain | Application-level favorites for the ecosystem sidebar | Entity-level favorites within DMS (templates + documents) |
| Backend | `${dashboardApiUrl}/api/v1/dashboard/user/{sub}/favorites` (maya_dashboard owns this) | `${dmsApiUrl}/api/v1/favorites` (maya_dms `FavoriteController`) |
| Return shape | `{ favorites: SharedFavorite[] }` — full app objects (`id, name, slug, traefik_url`) | `{ templateIds: Set<string>, documentIds: Set<string> }` — two disjoint id sets |
| Entity types | 1 (Application) | 2 (Template + Document) |
| Mutation endpoints | `POST/DELETE …/favorites/{applicationId}` | `POST/DELETE /favorites/templates/{id}` and `POST/DELETE /favorites/documents/{id}` (4 routes) |
| Cross-tab sync | yes — visibilitychange polling + favoritesBus (BroadcastChannel) | window focus listener; no cross-app bus |
| Auth | Bearer via `useAuth()` from `@maya/shared-auth-react` | DMS local OIDC adapter (different Keycloak hook) |
| Caching | Local state + refetch tick | Local state + window focus refetch |
| Consumers in dms | `<SidebarFavorites>` (already wired in `App.tsx:79`) | 4 places: `pages/NuevaProgramacionSelectorPage`, `features/documents/components/DocumentsTable`, `features/templates/components/TemplatesContent`, `features/templates/components/TemplatesTable` |

## Why they cannot be unified

1. **Different data models.** Shared returns full Application objects (name +
   url, used to render sidebar tiles). Local returns ID-only sets, used by
   `FavoriteButton`/`FavoriteInlineMark` to render a star next to each row of a
   templates/documents table. Forcing a single shape would either bloat the
   sidebar payload with template metadata it does not need, or force the
   templates table to issue 50× full-object fetches.

2. **Different backends.** `useSharedFavorites` talks to maya_dashboard; DMS
   templates/documents live in maya_dms. Re-routing the DMS templates
   favorites through the dashboard would require duplicating the
   template/document tables across services or adding a foreign-data wrapper.
   Neither is justified by the size of the duplication (the local hook is
   45 LOC).

3. **Already coexist by design.** maya_dms imports `SidebarFavorites` at
   `App.tsx:4` for the shared application favorites bar (sidebar) AND uses
   `useFavoritesIds` for per-row star toggles inside templates/documents
   tables. The two layers complement each other.

## Follow-up

- Add a one-line comment in `useFavoritesIds.ts` clarifying it is intentional
  and disjoint from `useSharedFavorites`.
- During Phase 9 (DMS DTOs + B5/B8 cleanup) the `useFavoritesIds` triplet
  (`useState`+`useEffect`+`fetch`) will be migrated to TanStack Query via the
  shared `createDataHook` wrapper from Phase 3. That migration is unrelated to
  this spike.

## References

- `maya_infra/packages/maya-shared-sidebar-react/src/useSharedFavorites.ts`
- `maya_dms/frontend/src/hooks/useFavoritesIds.ts`
- `maya_dms/frontend/src/api/favorites.ts`
- `maya_dms/backend/app/Http/Controllers/Api/FavoriteController.php`
- `maya_dms/backend/routes/api.php:166-173`
