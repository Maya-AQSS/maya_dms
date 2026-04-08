import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { HierarchyProvider } from './features/hierarchy'
import { bootstrapSessionToken } from './lib/sessionToken'

bootstrapSessionToken()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <HierarchyProvider>
      <App />
    </HierarchyProvider>
  </StrictMode>,
)
