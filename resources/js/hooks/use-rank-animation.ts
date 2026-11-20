import { type RefObject, useLayoutEffect, useRef } from 'react';

/**
 * When the ranking reorders (after a vote), slide each moved row from its old position to its new
 * one, so you can follow the game you just voted for. The FLIP technique: measure, then animate
 * the difference. Rows need a `data-rank-key` attribute. Skipped for prefers-reduced-motion.
 */
export function useRankAnimation(listRef: RefObject<HTMLElement | null>, order: string) {
    const previous = useRef(new Map<string, number>());

    useLayoutEffect(() => {
        const list = listRef.current;
        if (!list) return;

        const rows = Array.from(list.querySelectorAll<HTMLElement>('[data-rank-key]'));
        const positions = new Map(rows.map((row) => [row.dataset.rankKey!, row.getBoundingClientRect().top]));
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!reduceMotion && previous.current.size > 0) {
            for (const row of rows) {
                const before = previous.current.get(row.dataset.rankKey!);
                const delta = before === undefined ? 0 : before - positions.get(row.dataset.rankKey!)!;

                if (Math.abs(delta) > 1) {
                    row.animate([{ transform: `translateY(${delta}px)` }, { transform: 'translateY(0)' }], {
                        duration: 420,
                        easing: 'cubic-bezier(0.16, 1, 0.3, 1)', // exponential ease-out
                    });
                }
            }
        }

        previous.current = positions;
    }, [listRef, order]);
}
