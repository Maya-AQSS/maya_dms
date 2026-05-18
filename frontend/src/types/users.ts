import type { BaseMeProfile } from '@maya/shared-profile-react';

export type User = {
  id: string;
  name: string;
  email?: string;
  role?: string;
};

export type UserTeam = {
  id: string;
  name: string;
  description: string | null;
  role: string;
  is_department: boolean;
};

/**
 * Shape devuelto por `GET /api/v1/me` de maya_dms — extiende `BaseMeProfile`
 * (que aporta los campos canónicos cross-app `permisos`, `tipo_estudios`,
 * `estudios`, `modulos`, `equipos` en español).
 *
 * Los campos legacy (`study_type_ids`, `study_ids`, `module_ids`, `team_ids`,
 * `permissions`, `teams`, `department`, `source`) se mantienen como
 * **opcionales** únicamente para no romper tests/fixtures que aún los
 * referencian directamente — el backend `FdwUserProfileResolver` YA no
 * los expone en /me. Eliminar tras refactorizar los tests.
 */
export type MeProfile = BaseMeProfile & {
  department?: string | null;
  study_type_ids?: string[];
  study_ids?: string[];
  module_ids?: string[];
  team_ids?: string[];
  permissions?: string[];
  teams?: UserTeam[];
  source?: 'fdw' | 'jwt_fallback';
};

export type UsersSearchResponse = {
  data: User[];
};
