import { useState, useEffect } from 'react';
import type { Template } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import { Button } from '../../../ui';
import { visibilityLabel } from '../constants';
import { BlockContentHtml } from './BlockContentHtml';

interface Props {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
}

export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);

  useEffect(() => {
    if (blocks.length > 0 && !selectedBlockId) {
      setSelectedBlockId(blocks[0].id);
    }
  }, [blocks, selectedBlockId]);

  const selectedBlock = blocks.find((b) => b.id === selectedBlockId);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
      <div className="bg-ui-card dark:bg-ui-dark-card w-full max-w-6xl h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
        
        {/* Header */}
        <div className="px-6 py-4 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
          <div className="flex flex-col">
            <h2 className="text-lg font-bold text-text-primary dark:text-text-dark-primary leading-tight">
              {template.name}
            </h2>
            <p className="text-xs text-text-muted mt-0.5">
              {visibilityLabel(template.visibility_level)} · {blocks.length} bloque{blocks.length !== 1 ? 's' : ''}
            </p>
          </div>
          <Button variant="ghost" size="sm" onClick={onClose} className="rounded-full w-9 h-9 !p-0 flex items-center justify-center hover:bg-ui-body dark:hover:bg-ui-dark-bg">
            ✕
          </Button>
        </div>

        {/* Body */}
        <div className="flex-1 flex min-h-0">
          
          {/* Sidebar - Block list */}
          <div className="w-72 border-r border-ui-border dark:border-ui-dark-border flex flex-col shrink-0 bg-ui-body/30 dark:bg-ui-dark-bg/20">
            <div className="px-4 py-3">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">Estructura</p>
            </div>
            <div className="flex-1 overflow-y-auto px-2 pb-4 space-y-1">
              {blocks.map((b, i) => {
                const isSelected = selectedBlockId === b.id;
                return (
                  <button
                    key={b.id}
                    onClick={() => setSelectedBlockId(b.id)}
                    className={[
                      'w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all',
                      isSelected
                        ? 'bg-odoo-purple text-text-inverse shadow-md shadow-odoo-purple/20'
                        : 'text-text-secondary hover:bg-white dark:hover:bg-ui-dark-card hover:text-text-primary'
                    ].join(' ')}
                  >
                    <span className={`shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold ${isSelected ? 'bg-white/20' : 'bg-ui-border dark:bg-ui-dark-border'}`}>
                      {i + 1}
                    </span>
                    <span className="flex-1 text-xs font-medium truncate">
                      {b.title || 'Bloque sin título'}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Content area */}
          <div className="flex-1 flex flex-col min-w-0 bg-white dark:bg-ui-dark-card">
            {selectedBlock ? (
              <div className="flex-1 flex flex-col min-h-0">
                <div className="px-8 py-4 border-b border-ui-border/50 dark:border-ui-dark-border/50">
                  <h3 className="text-sm font-bold text-odoo-purple dark:text-odoo-dark-purple uppercase tracking-wider">
                    {selectedBlock.title || 'Bloque sin título'}
                  </h3>
                  {!!selectedBlock.description && (
                    <div className="mt-2 text-xs text-text-secondary dark:text-text-dark-secondary italic opacity-80 leading-relaxed max-w-3xl">
                      {typeof selectedBlock.description === 'string' ? (selectedBlock.description as string) : JSON.stringify(selectedBlock.description)}
                    </div>
                  )}
                </div>
                
                <div className="flex-1 overflow-y-auto p-8 pt-6">
                  <div className="max-w-4xl">
                    <BlockContentHtml content={(() => {
                      const content = selectedBlock.default_content;
                      if (!content) return [];
                      if (Array.isArray(content)) return content;
                      try {
                        const p = JSON.parse(content as string);
                        return Array.isArray(p) ? p : [];
                      } catch {
                        return [];
                      }
                    })()} />
                    {!selectedBlock.default_content && (
                      <div className="flex flex-col items-center justify-center py-20 text-center opacity-30 grayscale">
                        <p className="text-sm font-medium">Este bloque no tiene contenido predefinido.</p>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ) : (
              <div className="flex-1 flex flex-col items-center justify-center opacity-30">
                <p className="text-sm font-bold uppercase tracking-widest">Selecciona un bloque para previsualizar</p>
              </div>
            )}
          </div>

        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-ui-border dark:border-ui-dark-border flex justify-end gap-3 shrink-0 bg-ui-body/30 dark:bg-ui-dark-bg/20">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cerrar previsualización
          </Button>
        </div>

      </div>
    </div>
  );
}
