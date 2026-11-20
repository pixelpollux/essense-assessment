import { type Flash } from '@/types';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

/** Same "couldn't confirm" stance as a server-side timeout: a dropped request is just as ambiguous. */
export const NETWORK_FAILURE_MESSAGE = "Couldn't reach the server, so we couldn't confirm that went through. Check the list before trying again.";

/**
 * Inertia fires `exception` when a visit gets no response at all (offline, connection dropped, server
 * unreachable). Unhandled, that failure is silent: the button's spinner just stops. This turns it into
 * an error in the page's message area, for every visit (vote, remove vote, remove game, suggest).
 *
 * Returns the flash to show: the network error while one is outstanding, otherwise the server's flash.
 * A counter (not a boolean) makes a repeated failure show again even after the last one was dismissed.
 * The next successful response clears it.
 */
export function useFlashWithNetworkFailures(flash: Flash): Flash {
    const [failures, setFailures] = useState(0);

    useEffect(() => {
        const offException = router.on('exception', (event) => {
            event.preventDefault(); // handled here, so Inertia doesn't rethrow it
            setFailures((n) => n + 1);
        });
        const offSuccess = router.on('success', () => setFailures(0));

        return () => {
            offException();
            offSuccess();
        };
    }, []);

    return useMemo(() => (failures > 0 ? { success: null, error: NETWORK_FAILURE_MESSAGE } : flash), [failures, flash]);
}
