import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import './i18n'
import App from './App.tsx'
import { MayaProviders } from '@ceedcv-maya/shared-layout-react'
import { oidcAuthService } from './auth/oidcAdapter'
import { fetchMe } from './api/users'
import type { MeProfile } from './types/users'

/** Adapta el `fetchMe()` local (envuelto en `{ data }`) al provider compartido. */
async function fetchProfile(): Promise<MeProfile> {
  const res = await fetchMe()
  return res.data
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <MayaProviders
      authService={oidcAuthService}
      serviceSlug="dms"
      fetchProfile={fetchProfile}
      withToasts
    >
      <App />
    </MayaProviders>
  </StrictMode>,
)
