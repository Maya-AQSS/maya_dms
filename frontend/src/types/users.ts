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

export type MeProfile = {
 id: string;
 email: string | null;
 name: string | null;
 department: string | null;
 study_type_ids: string[];
 study_ids: string[];
 module_ids: string[];
 team_ids: string[];
 permissions: string[];
 teams: UserTeam[];
 source:'fdw' |'jwt_fallback';
};

export type UsersSearchResponse = {
 data: User[];
};

export type MeProfileResponse = {
 data: MeProfile;
};
