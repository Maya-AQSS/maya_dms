import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './index.css'
import './i18n'
import App from './App.tsx'
import { AuthProvider } from '@ceedcv-maya/shared-auth-react'
import { NotificationProvider } from '@ceedcv-maya/shared-sidebar-react'
import { ToastProvider } from '@ceedcv-maya/shared-ui-react'
import { ErrorBoundaryWrapper as ErrorBoundary } from './components/ErrorBoundaryWrapper'
import { oidcAuthService } from './auth/oidcAdapter'
import { UserProfileProvider } from './features/user-profile'
import { bootstrapRealtime } from './lib/realtimeBootstrap'

bootstrapRealtime()

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 60_000, retry: 1 },
  },
})

const router = createBrowserRouter([
  {
    path: '*',
    element: (
      <UserProfileProvider>
        <App />
      </UserProfileProvider>
    ),
  },
])

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider keycloak={oidcAuthService.keycloak} enableLogging={import.meta.env.DEV}>
        <ErrorBoundary>
          <NotificationProvider>
            <ToastProvider>
              <RouterProvider router={router} />
            </ToastProvider>
          </NotificationProvider>
        </ErrorBoundary>
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
)
