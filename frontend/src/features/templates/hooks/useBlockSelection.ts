import { useState, useCallback, useRef } from 'react';
import type { TemplateBlock } from '../../../types/blocks';

type PanelMode = 'empty' | 'create' | 'edit' | 'multi';

interface UseBlockSelectionParams {
  blocks: TemplateBlock[];
  onBlockClick?: (blockId: string) => void;
}

export interface BlockSelectionState {
  selectedBlockIds: string[];
  panelMode: PanelMode;
  activeSingleId: string | null;
  showCommentPanel: boolean;
  selectedBlock: TemplateBlock | null;
  selectedBlockIndex: number;
}

export interface BlockSelectionActions {
  setSelectedBlockIds: (ids: string[]) => void;
  setPanelMode: (mode: PanelMode) => void;
  setActiveSingleId: (id: string | null) => void;
  setShowCommentPanel: (show: boolean) => void;
  toggleSelectAll: () => void;
  selectBlock: (blockId: string) => void;
  clearSelection: () => void;
}

export function useBlockSelection({ blocks, onBlockClick }: UseBlockSelectionParams): [BlockSelectionState, BlockSelectionActions] {
  const [selectedBlockIds, setSelectedBlockIds] = useState<string[]>([]);
  const [panelMode, setPanelMode] = useState<PanelMode>('empty');
  const [activeSingleId, setActiveSingleId] = useState<string | null>(null);
  const [showCommentPanel, setShowCommentPanel] = useState(false);

  const selectedBlock = activeSingleId ? (blocks.find((b) => b.id === activeSingleId) ?? null) : null;
  const selectedBlockIndex = selectedBlock ? blocks.findIndex((b) => b.id === selectedBlock.id) : -1;

  const toggleSelectAll = useCallback(() => {
    if (selectedBlockIds.length === blocks.length && blocks.length > 0) {
      setSelectedBlockIds([]);
      setActiveSingleId(null);
      setPanelMode('empty');
    } else {
      setSelectedBlockIds(blocks.map((b) => b.id));
      setPanelMode('multi');
      if (blocks[0]) {
        setActiveSingleId(blocks[0].id);
      }
    }
  }, [selectedBlockIds.length, blocks]);

  const selectBlock = useCallback((blockId: string) => {
    setSelectedBlockIds([blockId]);
    setActiveSingleId(blockId);
    setPanelMode('edit');
    onBlockClick?.(blockId);
  }, [onBlockClick]);

  const clearSelection = useCallback(() => {
    setSelectedBlockIds([]);
    setActiveSingleId(null);
    setPanelMode('empty');
    setShowCommentPanel(false);
  }, []);

  const state: BlockSelectionState = {
    selectedBlockIds,
    panelMode,
    activeSingleId,
    showCommentPanel,
    selectedBlock,
    selectedBlockIndex,
  };

  const actions: BlockSelectionActions = {
    setSelectedBlockIds,
    setPanelMode,
    setActiveSingleId,
    setShowCommentPanel,
    toggleSelectAll,
    selectBlock,
    clearSelection,
  };

  return [state, actions];
}
