import { type DailyStatus } from '@/types/games';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ActionToken } from './action-token';

/** "Australia/Sydney" → "Sydney"; "UTC" stays "UTC". */
function placeName(timezone: string): string {
    return (timezone.split('/').pop() ?? timezone).replaceAll('_', ' ');
}

function timeUntil(iso: string, now: number): string {
    const minutes = Math.max(0, Math.ceil((new Date(iso).getTime() - now) / 60_000));
    const hours = Math.floor(minutes / 60);
    if (hours === 0) return `${minutes} min`;
    return minutes % 60 === 0 ? `${hours} h` : `${hours} h ${minutes % 60} min`;
}

/** The current time, refreshed every minute. When the reset time passes, reload the page's data. */
function useResetClock(resetsAt: string): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const tick = setInterval(() => setNow(Date.now()), 60_000);
        const reload = setTimeout(() => router.reload({ only: ['status', 'games'] }), Math.max(0, new Date(resetsAt).getTime() - Date.now()) + 1_000);

        return () => {
            clearInterval(tick);
            clearTimeout(reload);
        };
    }, [resetsAt]);

    return now;
}

/** The game named in the status, as a button that jumps to its row when a jump is available. */
function GameName({ name, onShow }: { name: string; onShow?: () => void }) {
    if (!onShow) return <>“{name}”</>;
    return (
        <button type="button" onClick={onShow} className="decoration-brand-line underline decoration-2 underline-offset-4">
            “{name}”
        </button>
    );
}

function statusSentence(status: DailyStatus, onShowVote?: () => void): React.ReactNode {
    if (status.canAct) return "You have today's action.";
    if (status.usedFor === 'add') return <>Your action today added “{status.gameName}”.</>;
    return (
        <>
            Your action today went to <GameName name={status.gameName ?? ''} onShow={onShowVote} />.
        </>
    );
}

type Props = {
    status: DailyStatus;
    timezone: string;
    /** Jump to the game you voted for today (clears the filter, scrolls, focuses "Remove vote"). */
    onShowVote?: () => void;
    /** The suggest form, shown while an action is available. */
    children?: React.ReactNode;
};

/**
 * Where the user stands today, written as a sentence beside today's token. Only the sentence and
 * its detail are a live region: the countdown ticks every minute and must not be re-announced.
 */
export function TodayStatus({ status, timezone, onShowVote, children }: Props) {
    const now = useResetClock(status.resetsAt);
    const remaining = timeUntil(status.resetsAt, now);
    const refunded = status.canAct && status.refundReason === 'game_removed';

    return (
        <section aria-labelledby="today-heading" className="flex gap-4 sm:gap-6">
            <ActionToken state={status.canAct ? 'available' : 'spent'} className="mt-0.5" />
            <div className="min-w-0 flex-1">
                <div aria-live="polite">
                    <h2 id="today-heading" tabIndex={-1} className="text-status font-semibold tracking-tight text-balance outline-none">
                        {statusSentence(status, onShowVote)}
                    </h2>
                    <p className="text-muted-foreground mt-2 text-pretty">
                        {refunded
                            ? `“${status.gameName}” was removed, so your action came back. Vote for a game, or suggest one.`
                            : status.canAct
                              ? 'Vote for a game, or suggest one.'
                              : status.usedFor === 'vote'
                                ? 'Changed your mind? Remove your vote today to get the action back.'
                                : 'You can vote or suggest again after midnight.'}
                    </p>
                </div>
                <p className="text-muted-foreground mt-1 text-sm">
                    {status.canAct ? 'Resets' : 'Back'} at midnight ({placeName(timezone)} time),{' '}
                    <span className="whitespace-nowrap">in {remaining}</span>.
                </p>
                {children && <div className="mt-6">{children}</div>}
            </div>
        </section>
    );
}

/**
 * The compact status that stays at the top of the screen once the full status has scrolled away.
 * Not a live region: the full status already announces changes. Unmounted while hidden.
 */
export function StatusBar({ status, onShowVote }: { status: DailyStatus; onShowVote?: () => void }) {
    const now = useResetClock(status.resetsAt);

    return (
        <div data-sticky-status className="bg-background fixed inset-x-0 top-0 z-20 border-b">
            <div className="mx-auto flex max-w-2xl items-center gap-3 px-4 py-2.5 sm:px-6">
                <ActionToken state={status.canAct ? 'available' : 'spent'} size="sm" />
                <p className="min-w-0 flex-1 text-sm font-semibold">{statusSentence(status, onShowVote)}</p>
                <p className="text-muted-foreground shrink-0 text-xs tabular-nums">
                    <span className="sr-only">Resets in </span>
                    {timeUntil(status.resetsAt, now)}
                </p>
            </div>
        </div>
    );
}
