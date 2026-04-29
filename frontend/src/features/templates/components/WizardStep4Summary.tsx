import { useState, useEffect } from'react';
import type { TemplateBlock } from'../../../types/blocks';
import type { Template } from'../../../types/templates';
import { visibilityLabel } from'../constants';
import type { ValidatorEntry } from'./WizardStep3Users';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from'../blockUiState';
import { useTemplateBlocks } from'../hooks/useTemplateBlocks';
import { TemplatePreviewModal } from'./TemplatePreviewModal';
import { BlockContentHtml } from'./BlockContentHtml';

// ── Types ────────────────────────────────────────────────────────────────────

type Props = {
 template: Template;
 validators: ValidatorEntry[];
 validationType:'libre' |'ordenada';
 documentValidators?: ValidatorEntry[];
 documentValidationType?:'libre' |'ordenada';
};

type PreviewTab ='Contenido' |'Descripción';

// ── SummaryRow helper ─────────────────────────────────────────────────────────

function SummaryRow({ label, value }: { label: string; value: React.ReactNode }) {
 return (<div className="flex flex-col py-1.5 border-b border-outline last:border-0">
 <dt className="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
 {label}
 </dt>
 <dd className="mt-0.5 text-xs font-medium text-on-surface">
 {value || <span className="text-on-surface-muted italic">—</span>}
 </dd>
 </div>
 );
}

// ── Main component ────────────────────────────────────────────────────────────

export function WizardStep4Summary({ template, validators, validationType, documentValidators = [], documentValidationType ='libre' }: Props) {
 const { blocks } = useTemplateBlocks(template.id);

 const [selectedBlock, setSelectedBlock] = useState<TemplateBlock | null>(null);
 const [activeTab, setActiveTab] = useState<PreviewTab>('Contenido');
 const [showPreview, setShowPreview] = useState(false);

 useEffect(() => {
 if (blocks.length > 0 && !selectedBlock) {
 setSelectedBlock(blocks[0]);
 }
 }, [blocks, selectedBlock]);

 const hierarchyFields = [
 { label:'Tipo de Estudio', value: template.study_type_id },
 { label:'Estudio', value: template.study_id },
 { label:'Módulo', value: template.module_id },
 { label:'Equipo', value: template.team_id },
 ].filter((f) => f.value);

 return (<div className="flex-1 overflow-y-auto px-6 py-5 bg-surface/30 space-y-4">

 <p className="text-xs text-on-surface-muted text-center">
 Revisa todos los datos antes de publicar. Usa el stepper para volver a cualquier paso.
 </p>

 {/* ── Fila superior: Propiedades + Usuarios ──────────────────────────── */}
 <div className="bg-white rounded-xl border border-outline shadow-sm overflow-hidden grid grid-cols-2 animate-in fade-in slide-in-from-top-1">

 {/* Columna izquierda — Propiedades */}
 <div className="px-5 py-4 border-r border-outline">
 <p className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">
 Propiedades
 </p>
 <dl className="space-y-0">
 <SummaryRow label="Nombre" value={template.name} />
 <SummaryRow label="Visibilidad" value={visibilityLabel(template.visibility_level)} />
 {hierarchyFields.map((f) => (<SummaryRow key={f.label} label={f.label} value={f.value} />
 ))}
 <SummaryRow label="Descripción" value={template.description} />
 <SummaryRow
 label="Plazo de entrega"
 value={template.delivery_deadline ? new Date(template.delivery_deadline).toLocaleDateString() : null}
 />
 </dl>
 </div>

 {/* Columna derecha — Usuarios y validación */}
 <div className="px-5 py-4 space-y-4">
 {/* Validadores de la plantilla */}
 <div>
 <div className="flex items-center gap-2 mb-2">
 <p className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
 Validadores de la plantilla
 </p>
 <span className="text-[10px] font-bold text-primary capitalize">({validationType})</span>
 </div>
 {validators.length === 0 ? (<p className="text-xs text-on-surface-muted italic">Sin validadores asignados.</p>
 ) : (<div className="space-y-2 overflow-y-auto max-h-36">
 {validators.map((v, i) => {
 const initials = v.name.split('').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ??'').join('');
 return (<div key={v.userId} className="flex items-center gap-2.5">
 {validationType ==='ordenada' && (<span className="shrink-0 w-5 h-5 rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center">{i + 1}</span>
 )}
 <span className="shrink-0 w-7 h-7 rounded-full bg-primary/10 text-primary text-[10px] font-black border border-primary/20 flex items-center justify-center">{initials}</span>
 <div className="min-w-0">
 <p className="text-xs font-bold text-on-surface truncate">{v.name}</p>
 {v.role && <p className="text-[10px] text-on-surface-variant uppercase tracking-tight">{v.role}</p>}
 </div>
 </div>
 );
 })}
 </div>
 )}
 </div>

 {/* Validadores del documento */}
 <div className="pt-3 border-t border-outline">
 <div className="flex items-center gap-2 mb-2">
 <p className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
 Validadores del documento
 </p>
 <span className="text-[10px] font-bold text-secondary capitalize">({documentValidationType})</span>
 </div>
 {documentValidators.length === 0 ? (<p className="text-xs text-on-surface-muted italic">Sin validadores asignados.</p>
 ) : (<div className="space-y-2 overflow-y-auto max-h-36">
 {documentValidators.map((v, i) => {
 const initials = v.name.split('').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ??'').join('');
 return (<div key={v.userId} className="flex items-center gap-2.5">
 {documentValidationType ==='ordenada' && (<span className="shrink-0 w-5 h-5 rounded-full bg-secondary text-on-primary text-[10px] font-bold flex items-center justify-center">{i + 1}</span>
 )}
 <span className="shrink-0 w-7 h-7 rounded-full bg-secondary/10 text-secondary text-[10px] font-black border border-secondary/20 flex items-center justify-center">{initials}</span>
 <div className="min-w-0">
 <p className="text-xs font-bold text-on-surface truncate">{v.name}</p>
 {v.role && <p className="text-[10px] text-on-surface-variant uppercase tracking-tight">{v.role}</p>}
 </div>
 </div>
 );
 })}
 </div>
 )}
 </div>
 </div>
 </div>

 {/* ── Fila inferior: Plantilla (bloques + preview) ──────────────────── */}
 <div className="bg-white rounded-xl border border-outline shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1">

 {/* Cabecera */}
 <div className="px-5 py-3 border-b border-outline flex items-center justify-between">
 <span className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
 Plantilla — {blocks.length} bloque{blocks.length !== 1 ?'s' :''}
 </span>
 <button
 type="button"
 onClick={() => setShowPreview(true)}
 className="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded border border-outline text-on-surface-variant hover:border-primary/50 hover:text-primary transition-colors"
 >
 Previsualizar
 </button>
 </div>

 {blocks.length === 0 ? (<div className="p-5">
 <p className="text-xs text-warning-dark italic">Aún no se han añadido bloques.</p>
 </div>
 ) : (<div className="grid" style={{ gridTemplateColumns:'200px 1fr', minHeight:'200px' }}>

 {/* Lista de bloques */}
 <div className="border-r border-outline p-3 overflow-y-auto max-h-64">
 <p className="text-[10px] font-bold uppercase tracking-widest text-on-surface-muted mb-2">
 Bloques ({blocks.length})
 </p>
 <div className="space-y-1">
 {blocks.map((block, i) => {
 const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
 const isSelected = selectedBlock?.id === block.id;
 return (<button
 key={block.id}
 type="button"
 onClick={() => setSelectedBlock(block)}
 className={[
'w-full text-left flex items-center gap-2 px-2.5 py-2 rounded-lg border transition-all',
 isSelected
 ?'bg-primary/10 border-primary/30'
 :'bg-transparent border-outline hover:bg-surface hover:border-outline',
 ].join('')}
 >
 <span className="shrink-0 text-[10px] font-bold text-on-surface-muted w-4 text-right">{i + 1}</span>
 <span className="flex-1 min-w-0 text-xs font-medium text-on-surface truncate">
 {block.title ||'Sin nombre'}
 </span>
 <span className={`shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}>
 {cfg.label}
 </span>
 </button>
 );
 })}
 </div>
 </div>

 {/* Panel de preview con tabs */}
 <div className="flex flex-col min-w-0">
 {/* Tabs */}
 <div className="flex border-b border-outline shrink-0">
 {(['Contenido','Descripción'] as PreviewTab[]).map((tab) => (<button
 key={tab}
 type="button"
 onClick={() => setActiveTab(tab)}
 className={[
'px-4 py-2.5 text-xs border-b-2 -mb-px transition-all',
 activeTab === tab
 ?'border-primary text-primary font-medium cursor-default'
 :'border-transparent text-on-surface-muted hover:text-on-surface cursor-pointer',
 ].join('')}
 >
 {tab}
 </button>
 ))}
 </div>

 {/* Contenido del tab */}
 <div className="flex-1 p-4 overflow-y-auto">
 {(() => {
 const content = activeTab ==='Descripción' ? selectedBlock?.description : selectedBlock?.default_content;
 if (!content) return <span className="text-xs text-on-surface-muted italic">{activeTab ==='Descripción' ?'Sin descripción.' :'Este bloque no tiene contenido.'}</span>;

 let parsed: unknown[] | null = null;
 if (Array.isArray(content)) {
 if (content.length > 0) parsed = content;
 } else if (typeof content ==='string') {
 try {
 const p = JSON.parse(content);
 if (Array.isArray(p) && p.length > 0) parsed = p;
 } catch { /* fallback to plain text */ }
 }

 if (parsed) return <BlockContentHtml content={parsed} />;
 if (typeof content ==='string') return <p className="text-xs text-on-surface-variant leading-relaxed">{content}</p>;

 return <span className="text-xs text-on-surface-muted italic">{activeTab ==='Descripción' ?'Sin descripción.' :'Este bloque no tiene contenido.'}</span>;
 })()}
 </div>
 </div>

 </div>
 )}
 </div>

 {showPreview && (<TemplatePreviewModal
 template={template}
 blocks={blocks}
 onClose={() => setShowPreview(false)}
 />
 )}

 </div>
 );
}
