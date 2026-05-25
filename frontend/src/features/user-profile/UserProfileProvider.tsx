import type { ReactNode } from 'react';
import { UserProfileProvider as SharedUserProfileProvider } from '@ceedcv-maya/shared-profile-react';
import { fetchMe } from '../../api/users';
import type { MeProfile } from '../../types/users';

/** Adapta el `fetchMe()` local (envuelto en `{ data }`) al provider compartido. */
async function fetchProfile(): Promise<MeProfile> {
  const res = await fetchMe();
  return res.data;
}

export function UserProfileProvider({ children }: { children: ReactNode }) {
  return (
    <SharedUserProfileProvider<MeProfile> fetchProfile={fetchProfile}>
      {children}
    </SharedUserProfileProvider>
  );
}
