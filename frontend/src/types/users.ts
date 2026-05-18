import type { BaseMeProfile, UserTeam as SharedUserTeam } from '@maya/shared-profile-react';

export type User = {
  id: string;
  name: string;
  email?: string;
  role?: string;
};

/**
 * Equipo del usuario tal como lo devuelve maya_dms (con `description` extra).
 * `SharedUserTeam` ya cubre id/name/role/is_department; aquí extendemos con
 * la descripción que solo dms expone hoy.
 */
export type UserTeam = SharedUserTeam & {
  description: string | null;
};

/**
 * Shape devuelto por `GET /api/v1/me` de maya_dms — extiende `BaseMeProfile`
 * (snake_case en inglés: `permissions`, `study_type_ids`, `study_ids`,
 * `module_ids`, `team_ids`, `teams`).
 *
 * `source` solo se mantiene como **opcional** para tests/fixtures legacy; el
 * backend `FdwUserProfileResolver` YA no lo expone en /me.
 */
export type MeProfile = BaseMeProfile & {
  source?: 'fdw' | 'jwt_fallback';
};

export type UsersSearchResponse = {
  data: User[];
};
