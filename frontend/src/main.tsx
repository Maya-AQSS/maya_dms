import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from './auth/OidcSessionProvider'
import { oidcAuthService } from './auth/oidcAdapter'
import { UserProfileProvider } from './features/user-profile'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AuthProvider keycloak={oidcAuthService.keycloak} enableLogging={import.meta.env.DEV}>
      <BrowserRouter>
        <UserProfileProvider>
          <App />
        </UserProfileProvider>
      </BrowserRouter>
    </AuthProvider>
  </StrictMode>,
)
