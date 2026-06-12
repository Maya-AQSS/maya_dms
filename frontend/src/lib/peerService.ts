/**
 * Re-export del helper canónico de @ceedcv-maya/shared-auth-react (0.16).
 *
 * La implementación local se eliminó al adoptar el paquete compartido; este
 * shim conserva la ruta `lib/peerService` que importan App.tsx y otros
 * módulos del repo.
 */
export { peerOrigin, resolveServiceUrl } from '@ceedcv-maya/shared-auth-react';
