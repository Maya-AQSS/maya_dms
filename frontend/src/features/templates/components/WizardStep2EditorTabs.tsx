import { Spinner } from '@ceedcv-maya/shared-ui-react';
import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import { uploadMedia } from '../../../api/media';
import { ErrorBoundaryWrapper } from '../../../components/ErrorBoundaryWrapper';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import { CoverDesignEditor } from '../cover/CoverDesignEditor';
import { parseCoverContent } from '../cover/coverModel';
import { safeJsonParse } from '../lib/blockValidation';
import { IndexBlockEditor, parseIndexConfig } from './IndexBlockEditor';
import type { WizardBlockForm } from './useWizardBlockForm';
import type { TabId } from './WizardStep2Blocks.types';

const BlockNoteEditorPanel = lazy(() =>
  import('./BlockNoteEditorPanel').then((m) => ({
    default: m.BlockNoteEditorPanel,
  })),
);

interface WizardStep2EditorTabsProps {
  form: WizardBlockForm;
  activeTab: TabId;
  template: Template;
  blocks: TemplateBlock[];
  activeSingleId: string | null;
  isSaving: boolean;
  effectiveIsDark: boolean;
  onEditorFullscreenChange: (v: boolean) => void;
  onCreateComment: (range: { from: number; to: number; text: string }) => Promise<string | null>;
  commentsById: Record<string, { author?: string; createdAt?: string; body: string }>;
}

/** Content (cover / index / blank / rich editor) and description tab bodies. */
export function WizardStep2EditorTabs({
  form,
  activeTab,
  template,
  blocks,
  activeSingleId,
  isSaving,
  effectiveIsDark,
  onEditorFullscreenChange,
  onCreateComment,
  commentsById,
}: WizardStep2EditorTabsProps) {
  const { t } = useTranslation(['documents', 'common']);
  const {
    formName,
    formBlockType,
    formContent,
    setFormContent,
    formDesc,
    setFormDesc,
    formUiState,
    meaningfulContent,
    coverPageSize,
    setTabIsDirty,
    flushTemplateBlockSave,
  } = form;

  return (
    <>
      {activeTab === 'content' && formBlockType === 'cover' && (
        <ErrorBoundaryWrapper>
          <div className="flex-1 min-h-0 flex flex-col">
            {!formName.trim() ? (
              <div className="flex-1 flex flex-col items-center justify-center p-12 text-center opacity-60">
                <div className="text-4xl mb-4">🖼</div>
                <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                  Asigna un nombre al bloque en "Propiedades" para diseñar la portada.
                </p>
              </div>
            ) : (
              <CoverDesignEditor
                value={parseCoverContent(safeJsonParse(formContent), coverPageSize)}
                pageSize={coverPageSize}
                templateId={template.id}
                onChange={(next) => {
                  setFormContent(JSON.stringify(next));
                  setTabIsDirty(true);
                }}
              />
            )}
          </div>
        </ErrorBoundaryWrapper>
      )}
      {activeTab === 'content' && formBlockType === 'index' && (
        <ErrorBoundaryWrapper>
          <div className="flex-1 min-h-0 flex flex-col">
            {!formName.trim() ? (
              <div className="flex-1 flex flex-col items-center justify-center p-12 text-center opacity-60">
                <div className="text-4xl mb-4">📑</div>
                <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                  Asigna un nombre al bloque en "Propiedades" para configurar el índice.
                </p>
              </div>
            ) : (
              <IndexBlockEditor
                blocks={blocks}
                currentBlockId={activeSingleId}
                value={parseIndexConfig(safeJsonParse(formContent))}
                onChange={(next) => {
                  setFormContent(JSON.stringify(next));
                  setTabIsDirty(true);
                }}
              />
            )}
          </div>
        </ErrorBoundaryWrapper>
      )}
      {activeTab === 'content' && formBlockType === 'blank' && (
        <div className="flex-1 min-h-0 flex flex-col items-center justify-center p-12 text-center opacity-70">
          <div className="text-4xl mb-4">📄</div>
          <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
            Hoja en blanco
          </p>
          <p className="mt-2 text-xs text-text-muted max-w-sm">
            Este bloque inserta una página vacía en el documento. No tiene contenido editable y
            permanece bloqueado.
          </p>
        </div>
      )}
      {activeTab === 'content' &&
        formBlockType !== 'cover' &&
        formBlockType !== 'index' &&
        formBlockType !== 'blank' && (
          <ErrorBoundaryWrapper>
            <div className="flex-1 min-h-0 p-6 flex flex-col">
              {!formName.trim() ? (
                <div className="flex-1 flex flex-col items-center justify-center p-12 text-center bg-white dark:bg-ui-dark-card rounded-xl border border-dashed border-ui-border dark:border-ui-dark-border opacity-60">
                  <div className="text-4xl mb-4">📝</div>
                  <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                    Asigna un nombre al bloque en "Propiedades" para habilitar el editor de
                    contenido.
                  </p>
                </div>
              ) : (
                <div className="flex-1 min-h-0 flex flex-col gap-2">
                  {/* DMS-F02: eliminado el `<p>{boolean}</p>` que renderizaba
                    un párrafo vacío cuando había contenido válido. */}
                  {formUiState === 'modifiable' && !meaningfulContent && (
                    <p className="bg-warning/10 text-warning-dark rounded px-3 py-1.5 dark:bg-warning-dark/30 dark:text-warning-light">
                      Los bloques tipo modificable deben tener contenido predeterminado
                      (obligatorio).
                    </p>
                  )}
                  {formUiState === 'locked' && !meaningfulContent && (
                    <p className="bg-warning/10 text-warning-dark rounded px-3 py-1.5 dark:bg-warning-dark/30 dark:text-warning-light">
                      Los bloques tipo bloqueado deben tener contenido predeterminado (obligatorio).
                    </p>
                  )}
                  {formUiState === 'editable' && !meaningfulContent && (
                    <p className="bg-info/10 text-info-dark rounded px-3 py-1.5 dark:bg-info-dark/30 dark:text-info-light">
                      Se recomienda añadir contenido predeterminado para los bloques editables.
                    </p>
                  )}
                  <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                    {isSaving && (
                      <div className="p-4 flex items-center justify-center min-h-[100px]">
                        <div className="flex items-center gap-2">
                          <Spinner size="md" />
                          <span>{t('common:savingChanges')}</span>
                        </div>
                      </div>
                    )}
                    {!isSaving && (
                      <Suspense
                        fallback={
                          <div className="p-4 flex justify-center">
                            <Spinner />
                          </div>
                        }
                      >
                        <BlockNoteEditorPanel
                          key={`content-${activeSingleId ?? 'none'}`}
                          initialContent={safeJsonParse(formContent) ?? undefined}
                          onChange={(json) => {
                            setFormContent(JSON.stringify(json));
                            setTabIsDirty(true);
                          }}
                          onFlush={flushTemplateBlockSave}
                          editable={true}
                          isDark={effectiveIsDark}
                          onFullscreenChange={onEditorFullscreenChange}
                          uploadFile={(file: File) =>
                            uploadMedia(
                              file,
                              activeSingleId ? { type: 'block', id: activeSingleId } : undefined,
                            )
                          }
                          onCreateComment={onCreateComment}
                          commentsById={commentsById}
                        />
                      </Suspense>
                    )}
                  </div>
                </div>
              )}
            </div>
          </ErrorBoundaryWrapper>
        )}
      {activeTab === 'description' && (
        <ErrorBoundaryWrapper>
          <div className="flex-1 min-h-0 p-6 flex flex-col">
            {!formName.trim() ? (
              <div className="flex-1 flex flex-col items-center justify-center p-12 text-center bg-white dark:bg-ui-dark-card rounded-xl border border-dashed border-ui-border dark:border-ui-dark-border opacity-60">
                <div className="text-4xl mb-4">📝</div>
                <p className="text-sm font-bold uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
                  Asigna un nombre al bloque en "Propiedades" para habilitar el editor de
                  descripción.
                </p>
              </div>
            ) : (
              <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                <Suspense
                  fallback={
                    <div className="p-4 flex justify-center">
                      <Spinner />
                    </div>
                  }
                >
                  <BlockNoteEditorPanel
                    key={`description-${activeSingleId ?? 'none'}`}
                    initialContent={safeJsonParse(formDesc) ?? undefined}
                    onChange={(json) => {
                      setFormDesc(JSON.stringify(json));
                      setTabIsDirty(true);
                    }}
                    onFlush={flushTemplateBlockSave}
                    editable={true}
                    isDark={effectiveIsDark}
                    onFullscreenChange={onEditorFullscreenChange}
                    uploadFile={(file: File) =>
                      uploadMedia(
                        file,
                        activeSingleId ? { type: 'block', id: activeSingleId } : undefined,
                      )
                    }
                    onCreateComment={onCreateComment}
                    commentsById={commentsById}
                  />
                </Suspense>
              </div>
            )}
          </div>
        </ErrorBoundaryWrapper>
      )}
    </>
  );
}
