import { useCallback, useEffect, useRef, useState } from 'react';
import { BLOCK_UI_STATE_CONFIG, type BlockUiState, blockToUiState } from '../blockUiState';
import { useAutoSave } from '@ceedcv-maya/shared-hooks-react';
import type { TemplateBlock, UpdateBlockPayload } from '../../../types/blocks';

type TabId = 'properties' | 'content' | 'description' | 'comments';

interface UseBlockEditingParams {
  activeSingleId: string | null;
  updateBlock: (id: string, data: UpdateBlockPayload) => Promise<void>;
}

export interface BlockEditingState {
  formName: string;
  formDesc: string;
  formContent: string;
  formUiState: BlockUiState;
  nameError: string;
  activeTab: TabId;
  tabIsDirty: boolean;
  isSaving: boolean;
  saveStatus: 'idle' | 'pending' | 'success' | 'error';
}

export interface BlockEditingActions {
  setFormName: (value: string) => void;
  setFormDesc: (value: string) => void;
  setFormContent: (value: string) => void;
  setFormUiState: (value: BlockUiState) => void;
  setNameError: (value: string) => void;
  setActiveTab: (value: TabId) => void;
  setTabIsDirty: (value: boolean) => void;
  setIsSaving: (value: boolean) => void;
  loadFormFromBlock: (block: TemplateBlock) => void;
  validateBlockName: (name: string) => string;
  doSave: () => Promise<void>;
  forceSave: () => Promise<void>;
  saveCurrentTab: () => Promise<boolean>;
}

export function useBlockEditing({ activeSingleId, updateBlock }: UseBlockEditingParams): [BlockEditingState, BlockEditingActions] {
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formUiState, setFormUiState] = useState<BlockUiState>('editable');
  const [nameError, setNameError] = useState('');
  const [activeTab, setActiveTab] = useState<TabId>('properties');
  const [tabIsDirty, setTabIsDirty] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  const activeSingleIdRef = useRef<string | null>(null);
  activeSingleIdRef.current = activeSingleId;

  const validateBlockName = (name: string): string => {
    if (!name.trim()) return 'El nombre del bloque es obligatorio';
    if (name.trim().toLowerCase() === 'bloque sin nombre') return '"Bloque sin nombre" no es un nombre válido';
    return '';
  };

  const loadFormFromBlock = (block: TemplateBlock) => {
    setFormName(block.title ?? '');
    setNameError('');
    setFormDesc(block.description ? (typeof block.description === 'string' ? block.description : JSON.stringify(block.description)) : '');
    setFormContent(block.default_content ? (typeof block.default_content === 'string' ? block.default_content : JSON.stringify(block.default_content)) : '');
    setFormUiState(blockToUiState(block));
    setTabIsDirty(false);
  };

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
    let parsedContent: unknown = null;
    let parsedDesc: unknown = null;
    try { parsedContent = formContent ? JSON.parse(formContent) : null; } catch { parsedContent = null; }
    try { parsedDesc = formDesc ? JSON.parse(formDesc) : null; } catch { parsedDesc = null; }
    if (Array.isArray(parsedContent) && parsedContent.length > 0) {
      type BlockNoteNode = { type?: string; content?: Array<{ text?: unknown }> };
      const isBlank = (parsedContent as BlockNoteNode[]).every((b) =>
        b.type !== 'image' &&
        b.type !== 'table' && (
          !Array.isArray(b.content) ||
          b.content.length === 0 ||
          b.content.every((c) => typeof c.text !== 'string' || !c.text.trim())
        ),
      );
      if (isBlank) parsedContent = null;
    }
    await updateBlock(blockId, {
      title: formName.trim(),
      description: parsedDesc,
      default_content: parsedContent,
      block_state,
      mandatory,
    });
    setTabIsDirty(false);
  }, [formName, formDesc, formContent, formUiState, updateBlock]);

  const { saveStatus, triggerSave, forceSave } = useAutoSave(doSave, 1500);

  useEffect(() => {
    // Only trigger autosave in edit/multi modes with active block and dirty state
    if (!activeSingleId || !tabIsDirty) return;
    triggerSave();
  }, [formName, formDesc, formContent, formUiState, tabIsDirty, activeSingleId]); // eslint-disable-line react-hooks/exhaustive-deps

  const saveCurrentTab = useCallback(async () => {
    if (!tabIsDirty || !activeSingleId) return true;
    try {
      await forceSave();
      return true;
    } catch {
      return false;
    }
  }, [tabIsDirty, activeSingleId, forceSave]);

  const state: BlockEditingState = {
    formName,
    formDesc,
    formContent,
    formUiState,
    nameError,
    activeTab,
    tabIsDirty,
    isSaving,
    saveStatus: saveStatus as 'idle' | 'pending' | 'success' | 'error',
  };

  const actions: BlockEditingActions = {
    setFormName,
    setFormDesc,
    setFormContent,
    setFormUiState,
    setNameError,
    setActiveTab,
    setTabIsDirty,
    setIsSaving,
    loadFormFromBlock,
    validateBlockName,
    doSave,
    forceSave,
    saveCurrentTab,
  };

  return [state, actions];
}
