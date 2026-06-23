import { useAutoSave, useFlushOnPageLeave } from '@ceedcv-maya/shared-hooks-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { BlockType, TemplateBlock, UpdateBlockPayload } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import type { Theme } from '../../../types/themes';
import { BLOCK_UI_STATE_CONFIG, type BlockUiState, blockToUiState } from '../blockUiState';
import {
  hasMeaningfulTiptapNode,
  safeJsonParse,
  type TiptapNode,
  tiptapContentHasMeaning,
  validateBlockName,
} from '../lib/blockValidation';
import type { PanelMode } from './WizardStep2Blocks.types';

interface UseWizardBlockFormArgs {
  template: Template;
  publishedThemes: Theme[];
  activeSingleId: string | null;
  panelMode: PanelMode;
  updateBlock: (id: string, data: UpdateBlockPayload) => Promise<TemplateBlock>;
}

/**
 * Edit-form state for the currently selected template block: the eight form
 * fields, name validation, dirty tracking, and the debounced autosave wired to
 * `updateBlock`. `loadFormFromBlock` hydrates the form from a block; `doSave`
 * pushes it back. Owns the autosave trigger and page-leave flush so the parent
 * only orchestrates selection/panel state.
 */
export function useWizardBlockForm({
  template,
  publishedThemes,
  activeSingleId,
  panelMode,
  updateBlock,
}: UseWizardBlockFormArgs) {
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formContent, setFormContent] = useState('');
  // DMS-F02: derivado PURO del contenido del formulario. Antes era estado React
  // mutado con side-effects dentro del validador recursivo del autosave (valor
  // según el último nodo visitado y siempre false hasta el primer guardado).
  const meaningfulContent = useMemo(
    () => tiptapContentHasMeaning(safeJsonParse(formContent)),
    [formContent],
  );
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [formBlockType, setFormBlockType] = useState<BlockType>('content');
  const [formPageBreakAfter, setFormPageBreakAfter] = useState(false);
  const [formThemeId, setFormThemeId] = useState<string | null>(null);
  const [formApplyTheme, setFormApplyTheme] = useState(true);
  // Tamaño de página físico para el editor de portada: del tema asignado al
  // bloque (override) o, en su defecto, del tema de la plantilla. El render PDF
  // usa exactamente este tamaño (pageSizes.ts es la única fuente de verdad).
  const coverPageSize = useMemo(() => {
    const themeId = formThemeId ?? template.theme_id ?? null;
    const th = themeId ? publishedThemes.find((t) => t.id === themeId) : null;
    return th?.layout?.page?.size ?? 'A4';
  }, [formThemeId, template.theme_id, publishedThemes]);
  const [nameError, setNameError] = useState('');
  const [tabIsDirty, setTabIsDirty] = useState(false);

  // Ref to always have latest activeSingleId in the autosave closure
  const activeSingleIdRef = useRef<string | null>(null);
  activeSingleIdRef.current = activeSingleId;

  const loadFormFromBlock = useCallback((block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setNameError('');
    setFormDesc(
      block.description
        ? typeof block.description === 'string'
          ? block.description
          : JSON.stringify(block.description)
        : '',
    );
    // Hoja en blanco: sin contenido y siempre bloqueada. Índice: siempre
    // modificable (el redactor elige qué secciones entran).
    const loadedType = block.block_type ?? 'content';
    setFormContent(
      loadedType === 'blank'
        ? ''
        : block.default_content
          ? typeof block.default_content === 'string'
            ? block.default_content
            : JSON.stringify(block.default_content)
          : '',
    );
    setFormUiState(
      loadedType === 'blank'
        ? 'locked'
        : loadedType === 'index'
          ? 'modifiable'
          : blockToUiState(block),
    );
    setFormBlockType(loadedType);
    setFormPageBreakAfter(Boolean(block.page_break_after));
    setFormThemeId(block.theme_id ?? null);
    setFormApplyTheme(block.apply_theme ?? true);
    setTabIsDirty(false);
  }, []);

  // ── useAutoSave (debounce 1500ms) — compartido en edit y multi ───────────────
  // validateBlockName importado de ../lib/blockValidation (antes copia inline idéntica).

  const doSave = useCallback(async () => {
    const blockId = activeSingleIdRef.current;
    if (!blockId) return;
    const nameErr = validateBlockName(formName);
    if (nameErr) {
      setNameError(nameErr);
      return;
    }
    setNameError('');
    const { block_state, mandatory } = BLOCK_UI_STATE_CONFIG[formUiState].payload;
    let parsedContent: unknown = safeJsonParse(formContent);
    const parsedDesc: unknown = safeJsonParse(formDesc);

    // DMS-F02: validador puro (lib/blockValidation). Igual que antes, un array
    // pelado sin contenido con sustancia se persiste como null.
    if (
      Array.isArray(parsedContent) &&
      !parsedContent.some((node) => hasMeaningfulTiptapNode(node as TiptapNode))
    ) {
      parsedContent = null;
    }
    await updateBlock(blockId, {
      title: formName.trim(),
      description: parsedDesc,
      default_content: parsedContent,
      block_state,
      mandatory,
      block_type: formBlockType,
      page_break_after: formPageBreakAfter,
      // apply_theme=false ⇒ el bloque no lleva tema; limpiamos el override.
      apply_theme: formApplyTheme,
      theme_id: formApplyTheme ? formThemeId : null,
    });
    setTabIsDirty(false);
  }, [
    formName,
    formDesc,
    formContent,
    formUiState,
    formBlockType,
    formPageBreakAfter,
    formThemeId,
    formApplyTheme,
    updateBlock,
  ]);

  const { saveStatus, triggerSave, forceSave } = useAutoSave(doSave, 1500);

  // Trigger autosave whenever form changes and there are dirty changes in edit or multi.
  // The form-field deps are intentional: every edit must re-arm the debounce even while
  // the block is already dirty. triggerSave is stable from useAutoSave. Biome's heuristic
  // flags these as "unnecessary" because the body doesn't read them directly — it's wrong here.
  // biome-ignore lint/correctness/useExhaustiveDependencies: form-field deps intentionally re-arm the autosave debounce on each edit
  useEffect(() => {
    if ((panelMode !== 'edit' && panelMode !== 'multi') || !activeSingleId || !tabIsDirty) return;
    triggerSave();
  }, [
    formName,
    formDesc,
    formContent,
    formUiState,
    formBlockType,
    formPageBreakAfter,
    formThemeId,
    formApplyTheme,
    tabIsDirty,
    panelMode,
    activeSingleId,
  ]); // eslint-disable-line react-hooks/exhaustive-deps

  // Convenience wrapper used by saveIfPending and manual saves
  const saveCurrentTab = useCallback(async () => {
    if (!tabIsDirty || !activeSingleId) return;
    try {
      await forceSave();
      return true;
    } catch {
      return false;
    }
  }, [tabIsDirty, activeSingleId, forceSave]);

  const flushTemplateBlockSave = useCallback(() => {
    if (tabIsDirty && activeSingleId) {
      void forceSave();
    }
  }, [tabIsDirty, activeSingleId, forceSave]);

  useFlushOnPageLeave(
    flushTemplateBlockSave,
    (panelMode === 'edit' || panelMode === 'multi') && !!activeSingleId,
  );

  return {
    formName,
    setFormName,
    formDesc,
    setFormDesc,
    formContent,
    setFormContent,
    formUiState,
    setFormUiState,
    formBlockType,
    setFormBlockType,
    formPageBreakAfter,
    setFormPageBreakAfter,
    formThemeId,
    setFormThemeId,
    formApplyTheme,
    setFormApplyTheme,
    meaningfulContent,
    coverPageSize,
    nameError,
    setNameError,
    tabIsDirty,
    setTabIsDirty,
    loadFormFromBlock,
    saveStatus,
    forceSave,
    saveCurrentTab,
    flushTemplateBlockSave,
  };
}

export type WizardBlockForm = ReturnType<typeof useWizardBlockForm>;
