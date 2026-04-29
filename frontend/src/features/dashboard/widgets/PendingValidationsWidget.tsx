import { useEffect, useState } from'react';
import { useNavigate } from'react-router-dom';
import { fetchDashboard } from'../../../api/dashboard';

/** Widget StatCard: nº de documentos pendientes de validación del usuario. */
export default function PendingValidationsWidget() {
 const navigate = useNavigate();
 const [count, setCount] = useState<number | null>(null);
 const [loading, setLoading] = useState(true);
 const [error, setError] = useState(false);

 useEffect(() => {
 let mounted = true;
 fetchDashboard()
 .then((data) => {
 if (!mounted) return;
 setCount(data.document_review_inbox?.length ?? 0);
 })
 .catch(() => {
 if (!mounted) return;
 setError(true);
 })
 .finally(() => {
 if (!mounted) return;
 setLoading(false);
 });
 return () => {
 mounted = false;
 };
 }, []);

 const handleClick = () => navigate('/documents');

 return (<button
 type="button"
 onClick={handleClick}
 className="w-full h-full flex flex-col items-start justify-center text-left rounded-xl bg-gradient-to-br from-primary/15 via-primary/5 to-transparent dark:to-transparent p-4 hover:from-primary/25 transition-colors"
 aria-label="Ver documentos pendientes de validación"
 >
 <span className="text-xs uppercase tracking-wide font-medium text-on-surface-variant">
 Pendientes de validar
 </span>
 <span className="mt-1 text-4xl font-bold tabular-nums bg-gradient-to-br from-primary to-primary-hover bg-clip-text text-transparent">
 {loading ?'…' : error ?'—' : (count ?? 0)}
 </span>
 <span className="mt-1 text-xs text-on-surface-muted">
 {error ?'Datos no disponibles' :'Documentos en tu bandeja'}
 </span>
 </button>
 );
}
