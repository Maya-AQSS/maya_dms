import type { BaseMeProfile, UserTeam as SharedUserTeam } from '@ceedcv-maya/shared-profile-react';

export type User = {
  id: string;
  name: string;
  email?: string;
  role?: string;
};

/**
 * Equipo del usuario, materializado en dms con `description` adicional para
 * UI. Los objetos completos provienen de `GET /api/v1/me/academic-context`
 * (paquete shared-profile-laravel) — `/me` solo expone `team_ids`.
 */
export type UserTeam = SharedUserTeam & {
  description: string | null;
};

/**
 * Shape devuelto por `GET /api/v1/me` de maya_dms — extiende `BaseMeProfile`
 * (snake_case en inglés: `permissions`, `study_type_ids`, `study_ids`,
 * `module_ids`, `team_ids`). NO incluye `teams` con objetos completos: para
 * obtener nombres se usa `GET /me/academic-context` vía `useHierarchy()`.
 *
 * `source` solo se mantiene como **opcional** para tests/fixtures legacy; el
 * backend `FdwUserProfileResolver` YA no lo expone en /me.
 */
export type MeProfile = Omit<BaseMeProfile, 'teams'> & {
  source?: 'fdw' | 'jwt_fallback';
};

export type UsersSearchResponse = {
  data: User[];
};
