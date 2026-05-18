/**
 * Re-exporta los símbolos del paquete compartido `@maya/shared-profile-react`
 * tipados con el `MeProfile` propio (con permissions, teams y academic ids).
 */
import {
  profileDisplayInitials,
  useUserProfile as useSharedUserProfile,
  type UserProfileContextValue,
} from '@maya/shared-profile-react';
import type { MeProfile } from '../../types/users';

export { UserProfileProvider } from './UserProfileProvider';

export function useUserProfile(): UserProfileContextValue<MeProfile> {
  return useSharedUserProfile<MeProfile>();
}

export { profileDisplayInitials };
export type { UserProfileContextValue };
