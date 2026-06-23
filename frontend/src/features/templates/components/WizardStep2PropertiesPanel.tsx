import { FieldLabel, Select, TextInput } from '@ceedcv-maya/shared-ui-react';
import type React from 'react';
import { useTranslation } from 'react-i18next';
import type { BlockType } from '../../../types/blocks';
import { BLOCK_TYPE_LABELS } from '../../../types/blocks';
import type { Theme } from '../../../types/themes';
import { validateBlockName } from '../lib/blockValidation';
import type { WizardBlockForm } from './useWizardBlockForm';
import { BlockUiStateToggle } from './WizardStep2BlockUiStateToggle';

interface WizardStep2PropertiesPanelProps {
  form: WizardBlockForm;
  publishedThemes: Theme[];
}

/** Properties tab: name, UI-state, block type, page-break, theme override. */
export function WizardStep2PropertiesPanel({
  form,
  publishedThemes,
}: WizardStep2PropertiesPanelProps) {
  const { t } = useTranslation(['documents', 'common', 'templates']);
  const {
    formName,
    setFormName,
    nameError,
    setNameError,
    setTabIsDirty,
    formUiState,
    setFormUiState,
    formBlockType,
    setFormBlockType,
    formPageBreakAfter,
    setFormPageBreakAfter,
    formApplyTheme,
    setFormApplyTheme,
    formThemeId,
    setFormThemeId,
    setFormContent,
  } = form;

  return (
    <div className="flex-1 overflow-y-auto p-6">
      <div className="w-full bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
        <div className="p-6 space-y-4">
          <div>
            <FieldLabel required>{t('templates:wizard.blockName')}</FieldLabel>
            <TextInput
              value={formName}
              placeholder={t('documents:blocks.newBlockPlaceholder')}
              error={!!nameError}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
                setFormName(e.target.value);
                setNameError(validateBlockName(e.target.value));
                setTabIsDirty(true);
              }}
              onBlur={() => setNameError(validateBlockName(formName))}
            />
            {nameError && <p className="mt-1 text-xs text-danger">{nameError}</p>}
          </div>
          <div>
            <FieldLabel>{t('common:fields.status')}</FieldLabel>
            <BlockUiStateToggle
              value={formUiState}
              disabled={formBlockType === 'blank' || formBlockType === 'index'}
              onChange={(s) => {
                setFormUiState(s);
                setTabIsDirty(true);
              }}
            />
            {formBlockType === 'blank' && (
              <p className="mt-1 text-xs text-text-muted">
                Una hoja en blanco siempre está bloqueada y no admite contenido.
              </p>
            )}
            {formBlockType === 'index' && (
              <p className="mt-1 text-xs text-text-muted">
                El índice siempre es modificable: el redactor elige qué secciones incluir.
              </p>
            )}
          </div>
          <div>
            <FieldLabel>{t('templates:wizard.blockType')}</FieldLabel>
            <Select
              value={formBlockType}
              onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                const next = e.target.value as BlockType;
                setFormBlockType(next);
                // Hoja en blanco: sin contenido y bloqueada por definición.
                if (next === 'blank') {
                  setFormUiState('locked');
                  setFormContent('');
                }
                // Índice: siempre modificable (el redactor elige secciones).
                if (next === 'index') {
                  setFormUiState('modifiable');
                }
                setTabIsDirty(true);
              }}
            >
              {(Object.keys(BLOCK_TYPE_LABELS) as BlockType[]).map((bt) => (
                <option key={bt} value={bt}>
                  {BLOCK_TYPE_LABELS[bt]}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <label className="flex items-center gap-2 text-sm text-text-primary dark:text-text-dark-primary cursor-pointer">
              <input
                type="checkbox"
                checked={formPageBreakAfter}
                onChange={(e) => {
                  setFormPageBreakAfter(e.target.checked);
                  setTabIsDirty(true);
                }}
                className="h-4 w-4 rounded border-ui-border"
              />
              Salto de página tras este bloque
            </label>
            <p className="mt-1 text-xs text-text-muted">
              El siguiente bloque empezará en una página nueva al exportar a PDF.
            </p>
          </div>
          <div className="pt-2 border-t border-ui-border dark:border-ui-dark-border">
            <label className="flex items-center gap-2 text-sm text-text-primary dark:text-text-dark-primary cursor-pointer">
              <input
                type="checkbox"
                checked={formApplyTheme}
                onChange={(e) => {
                  setFormApplyTheme(e.target.checked);
                  setTabIsDirty(true);
                }}
                className="h-4 w-4 rounded border-ui-border"
              />
              Aplicar tema a este bloque
            </label>
            <p className="mt-1 text-xs text-text-muted">
              Si lo desactivas, el bloque no llevará ningún tema (ni estilo ni cabecera/pie) y
              ocupará su propia página.
            </p>
          </div>
          <div>
            <FieldLabel>{t('templates:wizard.blockTheme')}</FieldLabel>
            <Select
              value={formThemeId ?? ''}
              disabled={!formApplyTheme}
              onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                setFormThemeId(e.target.value === '' ? null : e.target.value);
                setTabIsDirty(true);
              }}
            >
              <option value="">{t('templates:wizard.defaultTheme')}</option>
              {publishedThemes.map((th) => (
                <option key={th.id} value={th.id}>
                  {th.name}
                </option>
              ))}
            </Select>
            <p className="mt-1 text-xs text-text-muted">
              Por defecto hereda el tema de la plantilla. Puedes asignar un tema distinto solo a
              este bloque.
            </p>
          </div>
          <p className="text-xs text-text-muted italic">
            Se guarda automáticamente tras 1500 ms de inactividad o al cambiar de pestaña.
          </p>
        </div>
      </div>
    </div>
  );
}
