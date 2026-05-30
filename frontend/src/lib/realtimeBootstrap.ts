import { createEcho } from '@ceedcv-maya/shared-realtime-react'
import { getBearerToken } from '../api/http'
import { peerOrigin } from './peerService'

export function bootstrapRealtime(): void {
  const env = import.meta.env as Record<string, string | undefined>
  const appKey = env.VITE_REVERB_APP_KEY?.trim()
  if (!appKey) return // sin config no hay realtime

  const host = env.VITE_REVERB_HOST?.trim() || new URL(peerOrigin('dms-reverb')).hostname
  const scheme = (env.VITE_REVERB_SCHEME === 'http' ? 'http' : 'https') as 'http' | 'https'
  const port = Number.parseInt(env.VITE_REVERB_PORT ?? '', 10) || (scheme === 'https' ? 443 : 80)
  const authEndpoint = `${peerOrigin('dms-api')}/api/v1/broadcasting/auth`

  createEcho({ appKey, host, port, scheme, authEndpoint, getBearerToken })
}
