export type User = {
  id: string;
  name: string;
  email?: string;
  role?: string;
};

export type UsersSearchResponse = {
  data: User[];
};
