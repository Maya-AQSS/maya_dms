/**
 * Bootstrap del cliente Echo/Reverb — delegado al factory compartido
 * `bootstrapRealtime` de @ceedcv-maya/shared-realtime-react (0.16), que
 * encapsula la lectura de `VITE_REVERB_*` y la derivación de los peer origins
 * `dms-reverb` / `dms-api`. Este shim conserva la ruta y la firma sin
 * argumentos que importa main.tsx.
 */
import { bootstrapRealtime as sharedBootstrapRealtime } from '@ceedcv-maya/shared-realtime-react'
import { getBearerToken } from '../api/http'

export function bootstrapRealtime(): void {
  sharedBootstrapRealtime('dms', getBearerToken)
}
