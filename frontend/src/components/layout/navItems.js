import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { GridIcon, HomeIcon } from '@maya/shared-layout-react';
export function useNavItems() {
    const { t } = useTranslation('nav');
    return useMemo(() => [
        { id: 'dashboard', label: t('dashboard'), icon: HomeIcon, path: '/dashboard' },
        { id: 'procesos', label: t('procesos'), icon: GridIcon, path: '/procesos' },
    ], [t]);
}
