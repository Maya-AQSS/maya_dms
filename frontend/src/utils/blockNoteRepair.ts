// Block types whose `content` is structural (rows/cells), not flat inline content.
// Passing these through the inline-content normalizer would corrupt them.
const STRUCTURAL_BLOCK_TYPES = new Set(['table', 'tableRow']);

/**
 * Repairs a BlockNote block array by ensuring each block has the required properties
 * (id, type, props, content, children) and filtering out invalid entries.
 */
export const repairBlockNoteBlocks = (blocks: unknown): any[] => {
  if (!Array.isArray(blocks)) return [];

  return blocks
    .filter((block) => block && typeof block === 'object' && !Array.isArray(block) && typeof block.type === 'string')
    .map((block: any) => {
      // Table/tableRow blocks use row+cell content structure, not flat inline content.
      // Pass content as-is to avoid producing an invalid empty array that crashes BlockNote.
      if (STRUCTURAL_BLOCK_TYPES.has(block.type)) {
        return {
          ...block,
          id: typeof block.id === 'string' ? block.id : Math.random().toString(36).substring(7),
          type: block.type,
          props: (typeof block.props === 'object' && block.props !== null) ? { ...block.props } : {},
          content: block.content ?? [],
          children: [],
        };
      }

      const repairedBlock = {
        ...block,
        id: typeof block.id === 'string' ? block.id : Math.random().toString(36).substring(7),
        type: block.type,
        props: (typeof block.props === 'object' && block.props !== null) ? { ...block.props } : {},
        content: Array.isArray(block.content)
          ? block.content.map((c: any) => {
              if (typeof c === 'string') return { type: 'text', text: c, styles: {} };
              if (c && typeof c === 'object' && !Array.isArray(c)) {
                return {
                  type: c.type || 'text',
                  text: typeof c.text === 'string' ? c.text : '',
                  styles: (c.styles && typeof c.styles === 'object') ? { ...c.styles } : {},
                  ...c,
                };
              }
              return { type: 'text', text: '', styles: {} };
            })
          : [],
        children: Array.isArray(block.children) ? repairBlockNoteBlocks(block.children) : [],
      };
      return repairedBlock;
    });
};
