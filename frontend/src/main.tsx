import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from '@maya/shared-auth-react'
import { authService } from './lib/auth'
import { UserProfileProvider } from './features/user-profile'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AuthProvider keycloak={authService.keycloak} enableLogging={import.meta.env.DEV}>
      <BrowserRouter>
        <UserProfileProvider>
          <App />
        </UserProfileProvider>
      </BrowserRouter>
    </AuthProvider>
  </StrictMode>,
)
