import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { HierarchyProvider } from './context/HierarchyContext'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <HierarchyProvider>
      <App />
    </HierarchyProvider>
  </StrictMode>,
)
