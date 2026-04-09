import { useState } from 'react'
import './index.css'
import { DocumentsContent } from './components/DocumentsContent'
import { GroupsContent } from './features/groups'
import { TemplatesContent } from './features/templates'

// ─── Íconos inline SVG ──────────────────────────────────────
const FolderIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
  </svg>
)
const HomeIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
  </svg>
)
const UploadIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
  </svg>
)
const SearchIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
  </svg>
)
const UsersIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
  </svg>
)
const TemplateIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm0 2h16v10H4V5zm2 2h8v2H6V7zm0 4h12v2H6v-2zm0 4h8v2H6v-2z" />
  </svg>
)
const MoonIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
  </svg>
)
const SunIcon = () => (
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="w-4 h-4">
    <path fillRule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clipRule="evenodd" />
  </svg>
)

// ─── Tipos de navegación ──────────────────────────────────────
type NavItem = {
  id: string
  label: string
  icon: React.FC
}

const navItems: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', icon: HomeIcon },
  { id: 'documents', label: 'Documentos', icon: FolderIcon },
  { id: 'templates', label: 'Plantillas', icon: TemplateIcon },
  { id: 'groups', label: 'Grupos', icon: UsersIcon },
  { id: 'upload', label: 'Subir archivo', icon: UploadIcon },
  { id: 'search', label: 'Búsqueda', icon: SearchIcon },
]

// ─── Sidebar ──────────────────────────────────────────────────
function Sidebar({ active, onNav }: { active: string; onNav: (id: string) => void }) {
  return (
    <aside className="fixed inset-y-0 left-0 w-64 bg-ui-sidebar flex flex-col z-[100]">
      {/* Logo */}
      <div className="h-14 flex items-center px-5 border-b border-white/10">
        <span className="text-lg font-bold text-white tracking-wide">Maya DMS</span>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto">
        {navItems.map((item) => {
          const isActive = active === item.id
          return (
            <button
              key={item.id}
              onClick={() => onNav(item.id)}
              className={`w-full flex items-center gap-3 px-3 py-2 rounded text-sm font-medium transition-colors text-left ${
                isActive
                  ? 'bg-ui-sidebar-active text-white'
                  : 'text-white/70 hover:bg-ui-sidebar-hover hover:text-white'
              }`}
            >
              <item.icon />
              {item.label}
            </button>
          )
        })}
      </nav>

      {/* Footer */}
      <div className="border-t border-white/10 px-4 py-3">
        <p className="text-xs text-white/40">Maya DMS v1.0</p>
      </div>
    </aside>
  )
}

// ─── Topbar ──────────────────────────────────────────────────
function Topbar({
  title,
  isDark,
  onToggleDark,
}: {
  title: string
  isDark: boolean
  onToggleDark: () => void
}) {
  return (
    <header className="h-14 bg-ui-topbar dark:bg-ui-dark-topbar shadow-topbar flex items-center justify-between px-6 z-[200]">
      <h1 className="text-md font-semibold text-text-primary dark:text-text-dark-primary">
        {title}
      </h1>

      <div className="flex items-center gap-3">
        <button
          onClick={onToggleDark}
          className="p-2 rounded-lg hover:bg-ui-body dark:hover:bg-ui-dark-card text-text-secondary dark:text-text-dark-secondary transition-colors"
          aria-label={isDark ? 'Modo claro' : 'Modo oscuro'}
        >
          {isDark ? <SunIcon /> : <MoonIcon />}
        </button>

        <div className="w-8 h-8 rounded-full bg-odoo-purple flex items-center justify-center">
          <span className="text-xs font-bold text-white">U</span>
        </div>
      </div>
    </header>
  )
}

// ─── Contenido: Dashboard ─────────────────────────────────────
function DashboardContent() {
  const stats = [
    { label: 'Total documentos', value: '—', color: 'bg-odoo-purple/10 text-odoo-purple border-odoo-purple/20' },
    { label: 'Subidos hoy', value: '—', color: 'bg-odoo-teal/10 text-odoo-teal border-odoo-teal/20' },
    { label: 'Pendientes revisión', value: '—', color: 'bg-warning-light text-warning-dark border-warning/20' },
    { label: 'Archivados', value: '—', color: 'bg-ui-body text-text-secondary border-ui-border' },
  ]

  return (
    <div className="p-6">
      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {stats.map((s) => (
          <div
            key={s.label}
            className={`rounded-lg border p-4 shadow-card ${s.color}`}
          >
            <p className="text-xs uppercase tracking-wide font-medium opacity-80">{s.label}</p>
            <p className="text-3xl font-bold mt-1">{s.value}</p>
          </div>
        ))}
      </div>

      {/* Tabla de documentos recientes */}
      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Documentos recientes
          </h2>
          <button className="text-xs text-text-link dark:text-text-dark-link hover:underline">
            Ver todos
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full">
            <thead className="bg-ui-body dark:bg-ui-dark-card">
              <tr>
                {['Nombre', 'Tipo', 'Tamaño', 'Fecha', 'Estado'].map((h) => (
                  <th
                    key={h}
                    className="px-4 py-2 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary font-medium"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l">
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-sm text-text-muted dark:text-text-dark-muted">
                  No hay documentos todavía.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}

// ─── App root ─────────────────────────────────────────────────
function App() {
  const [activeSection, setActiveSection] = useState('dashboard')
  const [isDark, setIsDark] = useState(() => {
    return localStorage.getItem('theme') === 'dark' ||
      (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
  })

  const handleToggleDark = () => {
    const next = !isDark
    setIsDark(next)
    document.documentElement.classList.toggle('dark', next)
    localStorage.setItem('theme', next ? 'dark' : 'light')
  }

  // Aplicar dark mode al montar
  if (isDark) document.documentElement.classList.add('dark')

  const currentNav = navItems.find((n) => n.id === activeSection)
  const pageTitle = currentNav?.label ?? 'Maya DMS'

  return (
    <div className="min-h-screen bg-ui-body dark:bg-ui-dark-bg">
      <Sidebar active={activeSection} onNav={setActiveSection} />

      {/* Contenido desplazado por el sidebar */}
      <div className="ml-64 flex flex-col min-h-screen">
        <Topbar title={pageTitle} isDark={isDark} onToggleDark={handleToggleDark} />

        <main className="flex-1 overflow-auto">
          {activeSection === 'dashboard' && <DashboardContent />}
          {activeSection === 'documents' && <DocumentsContent />}
          {activeSection === 'templates' && <TemplatesContent />}
          {activeSection === 'groups' && <GroupsContent />}
          {activeSection !== 'dashboard' && activeSection !== 'documents' && activeSection !== 'templates' && activeSection !== 'groups' && (
            <div className="p-6">
              <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-8 text-center">
                <p className="text-text-muted dark:text-text-dark-muted text-sm">
                  Selecciona una opción del menú lateral.
                </p>
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  )
}

export default App
