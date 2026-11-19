import { cn } from '@/lib/utils';
import { type DailyStatus } from '@/types/games';
import { router } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useEffect, useState } from 'react';

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

function headline(status: DailyStatus): string {
    if (status.canAct) return '1 action left today';
    return status.usedFor === 'add' ? 'You suggested a game today' : "You've voted today";
}

type Props = {
    status: DailyStatus;
    timezone: string;
    compact?: boolean;
    /** Jump to the game you voted for today (clears the filter, scrolls, focuses "Remove vote"). */
    onShowVote?: () => void;
    className?: string;
    children?: React.ReactNode;
};

/**
 * Where the user stands today. Filled with the secondary brand color while an action is
 * available; neutral (with an edge, so it stays visible in dark mode) once it's used.
 * Only the headline and detail are a live region: the countdown ticks every minute and
 * must not be re-announced.
 */
export function TodayStatus({ status, timezone, compact = false, onShowVote, className, children }: Props) {
    const now = useResetClock(status.resetsAt);
    const remaining = timeUntil(status.resetsAt, now);
    const votedToday = !status.canAct && status.usedFor === 'vote';

    return (
        <section
            aria-labelledby="today-heading"
            className={cn(
                'rounded-xl',
                status.canAct ? 'bg-secondary text-secondary-foreground' : 'bg-muted text-foreground border',
                compact ? 'flex items-center justify-between gap-3 px-4 py-3' : 'p-5',
                className,
            )}
        >
            <div className="min-w-0">
                <div aria-live="polite">
                    <h2
                        id="today-heading"
                        tabIndex={-1}
                        className={cn('font-semibold tracking-tight text-balance outline-none', compact ? 'text-base' : 'text-2xl')}
                    >
                        {headline(status)}
                    </h2>
                    <p className={cn('text-sm text-pretty', compact ? 'opacity-85' : 'mt-1 opacity-90')}>
                        {status.canAct ? (
                            status.refundReason === 'game_removed' ? (
                                `“${status.gameName}” was removed, so today's action was given back.`
                            ) : compact ? (
                                'Vote or suggest a game.'
                            ) : (
                                'Vote for a game or suggest a new one.'
                            )
                        ) : status.usedFor === 'add' ? (
                            `You added “${status.gameName}”.`
                        ) : (
                            <>
                                You voted for{' '}
                                {onShowVote ? (
                                    <button type="button" onClick={onShowVote} className="font-medium underline underline-offset-4">
                                        “{status.gameName}”
                                    </button>
                                ) : (
                                    `“${status.gameName}”`
                                )}
                                .
                            </>
                        )}
                    </p>
                </div>
                {compact ? (
                    <p className="mt-0.5 text-xs opacity-80">Resets in {remaining}</p>
                ) : (
                    <p className="mt-4 flex items-center gap-1.5 text-sm opacity-85">
                        <Clock aria-hidden className="size-4 shrink-0" />
                        <span>
                            Resets at midnight ({placeName(timezone)} time), <span className="whitespace-nowrap">in {remaining}</span>
                        </span>
                    </p>
                )}
                {!compact && votedToday && (
                    <p className="mt-2 text-sm opacity-85">Changed your mind? Remove your vote today to get the action back.</p>
                )}
            </div>
            {children}
        </section>
    );
}
