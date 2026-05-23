import { useEffect, useRef, useState } from 'react';

const PAGE_HEIGHT_PX = 1122; // A4 at 96dpi: 29.7cm * 96 / 2.54cm ≈ 1122px

interface UseOverflowResult {
  isOverflowing: boolean;
  pageCount: number;
}

export function useCoverOverflow(containerRef: React.RefObject<HTMLElement>): UseOverflowResult {
  const [isOverflowing, setIsOverflowing] = useState(false);
  const [pageCount, setPageCount] = useState(1);
  const resizeObserverRef = useRef<ResizeObserver | null>(null);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const measureOverflow = () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }

      debounceTimerRef.current = setTimeout(() => {
        const scrollHeight = container.scrollHeight;
        const overflowing = scrollHeight > PAGE_HEIGHT_PX;
        const count = Math.ceil(scrollHeight / PAGE_HEIGHT_PX);

        setIsOverflowing(overflowing);
        setPageCount(count);
      }, 200);
    };

    // Initial measurement
    measureOverflow();

    // Set up ResizeObserver for content changes
    if ('ResizeObserver' in window) {
      resizeObserverRef.current = new ResizeObserver(() => {
        measureOverflow();
      });
      resizeObserverRef.current.observe(container);
    }

    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
      if (resizeObserverRef.current) {
        resizeObserverRef.current.disconnect();
      }
    };
  }, [containerRef]);

  return { isOverflowing, pageCount };
}
