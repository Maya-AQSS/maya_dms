import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.tsx'
import { HierarchyProvider } from './features/hierarchy'
import { bootstrapSessionToken } from './lib/sessionToken'

bootstrapSessionToken()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <HierarchyProvider>
        <App />
      </HierarchyProvider>
    </BrowserRouter>
  </StrictMode>,
)
