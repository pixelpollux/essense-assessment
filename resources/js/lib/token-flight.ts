/**
 * The page's one orchestrated motion: when you vote, today's token travels from the status onto
 * the row you voted for. Record where the visible token is just before the request, then animate
 * the placed token from there once the row renders. Skipped under prefers-reduced-motion.
 */
let origin: DOMRect | null = null;

export function recordTokenOrigin(): void {
    const visible = Array.from(document.querySelectorAll<HTMLElement>('[data-token="available"]'))
        .map((el) => el.getBoundingClientRect())
        .find((rect) => rect.bottom > 0 && rect.top < window.innerHeight && rect.width > 0);
    origin = visible ?? null;
}

export function flyTokenTo(target: HTMLElement | null): void {
    const from = origin;
    origin = null;
    if (!from || !target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const to = target.getBoundingClientRect();
    const dx = from.left + from.width / 2 - (to.left + to.width / 2);
    const dy = from.top + from.height / 2 - (to.top + to.height / 2);
    const scale = from.width / to.width;

    target.animate([{ transform: `translate(${dx}px, ${dy}px) scale(${scale})` }, { transform: 'none' }], {
        duration: 480,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)', // exponential ease-out
    });
}
