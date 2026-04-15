import { useEffect, useState } from 'react'

export function useDarkMode() {
  const [isDark, setIsDark] = useState(() => {
    const stored = localStorage.getItem('maya-theme')
    if (stored) return stored === 'dark'
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  })

  useEffect(() => {
    const root = document.documentElement
    if (isDark) {
      root.classList.add('dark')
      localStorage.setItem('maya-theme', 'dark')
    } else {
      root.classList.remove('dark')
      localStorage.setItem('maya-theme', 'light')
    }
  }, [isDark])

  const toggle = () => setIsDark((d) => !d)

  return { isDark, setIsDark, toggle } as const
}
