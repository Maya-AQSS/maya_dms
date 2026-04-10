export type GroupMember = {
  id: string;
  group_id: string;
  user_id: string;
  role: string;
  created_at?: string;
  updated_at?: string;
};

export type Group = {
  id: string;
  name: string;
  description: string | null;
  owner_id: string;
  created_at?: string;
  updated_at?: string;
  members?: GroupMember[];
};

export type GroupsListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type GroupsListResponse = {
  data: Group[];
  meta: GroupsListMeta;
};
