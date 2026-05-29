import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho } from '../../../lib/echo'

interface BroadcastPayload {
  id?: number
  type?: string
  title?: string
  body?: string
  is_critical?: boolean
  scope?: 'user' | 'dashboard' | 'both'
}

interface UseRealtimeNotificationsOptions {
  userId: string | null | undefined
  onNotification?: (payload: BroadcastPayload) => void
}

/**
 * Suscribe el cliente al canal privado `notifications.{userId}` y, cuando llega un
 * evento `notif.created`, invalida la query de notificaciones para que TanStack
 * Query la refresque. El polling de 60s permanece como fallback si el WebSocket
 * cae o el navegador queda en background.
 */
export function useRealtimeNotifications({ userId, onNotification }: UseRealtimeNotificationsOptions): void {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!userId) return

    const echo = getEcho()
    const channel = echo.private(`notifications.${userId}`)
    channel.listen('.notification.created', (payload: BroadcastPayload) => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      onNotification?.(payload)
    })

    return () => {
      channel.stopListening('.notification.created')
      echo.leave(`notifications.${userId}`)
    }
  }, [userId, queryClient, onNotification])
}
