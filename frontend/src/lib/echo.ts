import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { peerOrigin } from './peerService'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<'reverb'> | undefined
  }
}

window.Pusher = Pusher

interface ReverbConfig {
  appKey: string
  host: string
  port: number
  scheme: 'http' | 'https'
}

function readReverbConfig(): ReverbConfig {
  const env = import.meta.env as Record<string, string | undefined>
  const appKey = env.VITE_REVERB_APP_KEY?.trim()
  if (!appKey) {
    throw new Error('VITE_REVERB_APP_KEY is required to bootstrap the Reverb client')
  }

  const host = env.VITE_REVERB_HOST?.trim() || new URL(peerOrigin('dms-reverb')).hostname
  const scheme: 'http' | 'https' = env.VITE_REVERB_SCHEME === 'http' ? 'http' : 'https'
  const port = Number.parseInt(env.VITE_REVERB_PORT ?? '', 10) || (scheme === 'https' ? 443 : 80)

  return { appKey, host, port, scheme }
}

let echoInstance: Echo<'reverb'> | null = null

export function getEcho(): Echo<'reverb'> {
  if (echoInstance) return echoInstance

  const cfg = readReverbConfig()
  const authEndpoint = `${peerOrigin('dms-api')}/api/v1/broadcasting/auth`

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: cfg.appKey,
    wsHost: cfg.host,
    wsPort: cfg.port,
    wssPort: cfg.port,
    forceTLS: cfg.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    auth: {
      headers: {
        Accept: 'application/json',
      },
    },
    authorizer: (channel) => ({
      authorize: (socketId: string, callback: (error: boolean, data: unknown) => void) => {
        const token = getBearerToken()
        if (!token) {
          callback(true, { error: 'no_bearer_token' })
          return
        }
        fetch(authEndpoint, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
        })
          .then(async (response) => {
            if (!response.ok) {
              callback(true, { status: response.status })
              return
            }
            callback(false, await response.json())
          })
          .catch((err: unknown) => callback(true, err))
      },
    }),
  })

  window.Echo = echoInstance
  return echoInstance
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
    window.Echo = undefined
  }
}
