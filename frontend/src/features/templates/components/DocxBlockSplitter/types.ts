export interface TargetBlock {
  id: string;
  name: string;
}

export type Status = 'idle' | 'parsing' | 'ready' | 'creating' | 'error';
