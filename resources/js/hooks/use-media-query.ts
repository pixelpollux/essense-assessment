import { useEffect, useState } from 'react';

/**
 * True while the media query matches. Reads the current value on first render, so a desktop
 * paints the desktop layout straight away instead of flashing the mobile one.
 */
export function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

    useEffect(() => {
        const mql = window.matchMedia(query);
        const update = () => setMatches(mql.matches);

        update();
        mql.addEventListener('change', update);

        return () => mql.removeEventListener('change', update);
    }, [query]);

    return matches;
}
