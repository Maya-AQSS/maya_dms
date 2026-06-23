import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';

export type PanelMode = 'empty' | 'create' | 'edit' | 'multi';
export type TabId = 'properties' | 'content' | 'description' | 'comments';

export interface WizardStep2BlocksProps {
  template: Template;
  isDark?: boolean;
  onBlocksCountChange?: (count: number) => void;
  onBlocksLoadingChange?: (loading: boolean) => void;
  onBlocksChange?: (blocks: TemplateBlock[]) => void;
  onContinue?: () => void;
  onInvalidBlocksChange?: (hasInvalid: boolean) => void;
}

export type WizardStep2BlocksHandle = {
  saveIfPending: () => Promise<void>;
  discardInvalidBlocks: () => Promise<TemplateBlock[]>;
};
