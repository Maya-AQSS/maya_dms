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
 * con los campos académicos resueltos por `App\Repositories\Resolvers\FdwUserProfileResolver`.
 */
export type MeProfile = BaseMeProfile & {
  department: string | null;
  study_type_ids: string[];
  study_ids: string[];
  module_ids: string[];
  team_ids: string[];
  permissions: string[];
  teams: UserTeam[];
  source: 'fdw' | 'jwt_fallback';
};

export type UsersSearchResponse = {
  data: User[];
};
