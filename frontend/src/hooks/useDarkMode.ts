import { useEffect, useState } from'react';

/**
 * Reactively tracks whether the app is rendered in dark mode.
 *
 * Source of truth: the`dark` class on <html> (set by the consumer app based
 * on its theme logic). Falls back to`prefers-color-scheme: dark` when the
 * consumer hasn't opted-in to explicit theme classes yet.
 */
export function useDarkMode(): { isDark: boolean } {
 const [isDark, setIsDark] = useState<boolean>(() => detect());

 useEffect(() => {
 const root = document.documentElement;

 const observer = new MutationObserver(() => setIsDark(detect()));
 observer.observe(root, { attributes: true, attributeFilter: ['class'] });

 const media = window.matchMedia('(prefers-color-scheme: dark)');
 const onMediaChange = () => setIsDark(detect());
 media.addEventListener('change', onMediaChange);

 return () => {
 observer.disconnect();
 media.removeEventListener('change', onMediaChange);
 };
 }, []);

 return { isDark };
}

function detect(): boolean {
 if (typeof document ==='undefined') return false;
 if (document.documentElement.classList.contains('dark')) return true;
 if (typeof window !=='undefined' && window.matchMedia) {
 return window.matchMedia('(prefers-color-scheme: dark)').matches;
 }
 return false;
}
