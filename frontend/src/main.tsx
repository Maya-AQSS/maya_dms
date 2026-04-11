import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from '@maya/shared-auth-react'
import { authService } from './lib/auth'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AuthProvider keycloak={authService.keycloak} enableLogging={import.meta.env.DEV}>
      <App />
    </AuthProvider>
  </StrictMode>,
)
