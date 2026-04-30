import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { fetchTemplate } from '../api/templates';
import { TemplateWizard } from '../features/templates/components/TemplateWizard';
export function TemplateEditPage() {
    const { id } = useParams();
    const [template, setTemplate] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    useEffect(() => {
        if (!id)
            return;
        setLoading(true);
        fetchTemplate(id)
            .then((res) => setTemplate(res.data))
            .catch((e) => setError(e instanceof Error ? e.message : 'Error al cargar la plantilla'))
            .finally(() => setLoading(false));
    }, [id]);
    if (loading) {
        return <div className="p-6 text-sm text-text-muted dark:text-text-dark-muted">Cargando plantilla…</div>;
    }
    if (error || !template) {
        return <div className="p-6 text-sm text-warning-dark dark:text-warning-light">{error ?? 'Plantilla no encontrada.'}</div>;
    }
    return <TemplateWizard template={template}/>;
}
