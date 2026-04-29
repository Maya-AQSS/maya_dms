import { useEffect, useState } from'react';
import { useParams, useNavigate } from'react-router-dom';
import { Button } from'@maya/shared-ui-react';
import { fetchTemplate, type Template } from'../api/templates';
import { TemplateReviewView } from'../features/templates';
import { useUserProfile } from'../features/user-profile';

export function TemplateReviewPage() {
 const { id } = useParams<{ id: string }>();
 const navigate = useNavigate();
 const { profile } = useUserProfile();
 const [template, setTemplate] = useState<Template | null>(null);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState<string | null>(null);

 useEffect(() => {
 if (!id || !profile) return;

 async function loadTemplate() {
 try {
 const res = await fetchTemplate(id!);
 const t = res.data;

 // Check if user is an assigned reviewer
 const isReviewer = t.reviewers?.some((r) => r.user_id === profile?.id);
 if (!isReviewer) {
 setError('No tienes permisos de validación sobre esta plantilla.');
 return;
 }

 setTemplate(t);
 } catch (e) {
 setError(e instanceof Error ? e.message :'Error al cargar la plantilla');
 } finally {
 setLoading(false);
 }
 }

 void loadTemplate();
 }, [id, profile]);

 if (loading) {
 return (<div className="flex items-center justify-center h-full text-sm text-on-surface-muted">
 Cargando plantilla para revisión…
 </div>
 );
 }

 if (error || !template) {
 return (<div className="flex flex-col items-center justify-center h-full p-6 text-center space-y-4">
 <p role="alert" aria-live="assertive" className="text-sm text-danger-dark font-bold">⚠️ {error ||'No se pudo encontrar la plantilla'}</p>
 <Button variant="ghost" size="sm" onClick={() => navigate('/templates')}>
 Volver al listado
 </Button>
 </div>
 );
 }

 return <TemplateReviewView template={template} />;
}
