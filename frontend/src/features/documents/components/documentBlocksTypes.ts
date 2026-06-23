import type { DocumentDetail, DocumentDisplayBlock } from '../../../types/documents';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';
import type { BlockViewTab } from './documentWizardUtils';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface BlocksComments {
  reviewComments: BlockComment[];
  showPanel: boolean;
  setShowPanel: (updater: boolean | ((v: boolean) => boolean)) => void;
  loading: boolean;
  error: string | null;
  onSend: (parentId: string | null, body: string) => Promise<void>;
  onEdit: (commentId: string, newBody: string) => Promise<void>;
  onDelete: (commentId: string) => Promise<void>;
  onMarkAsRead: (commentId: string) => Promise<void>;
  onMarkAllBlockAsRead: () => Promise<void>;
  canAdd: boolean;
  canDeleteAny: boolean;
  currentUserId: string | null;
}

export interface BlocksEditor {
  isDark: boolean;
  isEditorFullscreen: boolean;
  setIsEditorFullscreen: (v: boolean) => void;
  onFullscreenChange: (v: boolean) => void;
  onContentChange: (content: unknown) => void;
  onFlush: (payload?: unknown) => Promise<void> | void;
  editorFlushRef: { current: (() => void | Promise<void>) | null };
  saveStatus: SaveStatus;
  blockSaveError: string | null;
  isSaving: boolean;
}

export interface BlocksViewMode {
  mode: 'per-block' | 'continuous';
  setMode: (updater: (prev: 'per-block' | 'continuous') => 'per-block' | 'continuous') => void;
  isContinuousFullscreen: boolean;
  setIsContinuousFullscreen: (updater: boolean | ((v: boolean) => boolean)) => void;
}

export interface DocumentBlocksSharedProps {
  documentId?: string | null;
  detail: DocumentDetail | null;
  sortedBlocks: DocumentDisplayBlock[];
  activeBlock: DocumentDisplayBlock | null;
  activeBlockKey: string | null;
  isDraft: boolean;
  canEditBlocks: boolean;
  canDeleteOptionalBlock: boolean;
  isSidebarCollapsed: boolean;
  setIsSidebarCollapsed: (v: boolean) => void;
  blockViewTab: BlockViewTab;
  setBlockViewTab: (t: BlockViewTab) => void;
  completedBlocks: { isCompleted: (id: string) => boolean; toggle: (id: string) => void };
  descriptionBlockKey: string | null;
  setDescriptionBlockKey: (updater: (prev: string | null) => string | null) => void;
  onBlockClick: (key: string) => void;
  onContinue: () => void;
  onShowDeleteBlock: () => void;
  onPersistBlockContent: (blockId: string | null | undefined, payload: unknown) => Promise<void>;
  editor: BlocksEditor;
  viewMode: BlocksViewMode;
  comments: BlocksComments;
}
