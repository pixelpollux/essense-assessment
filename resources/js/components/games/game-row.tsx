import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { flyTokenTo } from '@/lib/token-flight';
import { cn } from '@/lib/utils';
import { type Game } from '@/types/games';
import { Ellipsis, LoaderCircle, Trash2 } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { ActionToken } from './action-token';

export const votesLabel = (votes: number) => (votes === 1 ? '1 vote' : `${votes} votes`);

type Props = {
    game: Game;
    rank: number;
    canAct: boolean;
    pending: boolean;
    /**
     * A focus request for this row's main button (0 = none). A counter rather than a boolean, so the
     * same row can be asked again: after its vote changes (the button is replaced) or "jump to my vote".
     */
    focusToken: number;
    onVote: (game: Game) => void;
    onRemoveVote: (game: Game) => void;
    onRemove: (game: Game) => void;
};

export function GameRow({ game, rank, canAct, pending, focusToken, onVote, onRemoveVote, onRemove }: Props) {
    const rowRef = useRef<HTMLLIElement>(null);
    const actionRef = useRef<HTMLButtonElement>(null);
    const tokenRef = useRef<HTMLSpanElement>(null);
    const [arrived, setArrived] = useState(false);

    // When this row becomes your vote, today's token travels here from the status.
    useLayoutEffect(() => {
        if (game.votedByMe) flyTokenTo(tokenRef.current);
    }, [game.votedByMe]);

    // Focus arrives here after a vote change or "jump to my vote". Scroll only if the row isn't already
    // clear of the sticky bar and the bottom message area, then show a brief ring: after a tap the
    // browser draws no focus ring, so pointer users need their own cue. The ring is static.
    useEffect(() => {
        const row = rowRef.current;
        if (focusToken === 0 || !row) return;

        // The button can be gone (e.g. a vote refused because today's action was used in another tab:
        // the refreshed status hides Vote buttons). Send focus to the status, which explains why.
        if (!actionRef.current) {
            document.getElementById('today-heading')?.focus({ preventScroll: true });
            return;
        }

        actionRef.current.focus({ preventScroll: true });
        const stickyBottom = document.querySelector('[data-sticky-status]')?.getBoundingClientRect().bottom ?? 0;
        const rect = row.getBoundingClientRect();
        if (rect.top < stickyBottom + 8 || rect.bottom > window.innerHeight - 96) {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            row.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
        }

        setArrived(true);
        const timer = setTimeout(() => setArrived(false), 1600);
        return () => clearTimeout(timer);
    }, [focusToken, game.votedByMe]);

    return (
        <li
            ref={rowRef}
            data-rank-key={game.id}
            className={cn(
                // rank | name | action | count | menu. Count and menu are fixed at the right edge, so counts
                // line up whichever button a row shows (Vote, Remove vote, or none once today's action is used).
                'grid grid-cols-[2.25rem_minmax(0,1fr)_auto_auto] items-center gap-x-2 rounded-md py-4 sm:grid-cols-[3rem_minmax(0,1fr)_auto_5.5rem_2.5rem] sm:gap-x-3',
                arrived && 'outline-ring outline-2 outline-offset-4',
            )}
        >
            <span className={cn('text-2xl font-semibold tabular-nums', rank === 1 ? 'text-brand-ink' : 'text-muted-foreground')}>
                <span className="sr-only">Rank </span>
                {rank}
            </span>

            <div className="min-w-0">
                <p className="text-title font-medium break-words">{game.name}</p>
                <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span className="tabular-nums sm:hidden">{votesLabel(game.votes)}</span>
                    {game.votedByMe && (
                        <span className="text-foreground inline-flex items-center gap-1.5 font-medium">
                            <ActionToken ref={tokenRef} state="placed" size="sm" />
                            Your vote
                        </span>
                    )}
                </div>
            </div>

            <div className="flex justify-end">
                {
                    game.votedByMe ? (
                        <Button
                            ref={actionRef}
                            variant="outline"
                            className="h-10 bg-transparent"
                            disabled={pending}
                            aria-busy={pending || undefined}
                            aria-label={`Remove your vote for ${game.name}`}
                            onClick={() => onRemoveVote(game)}
                        >
                            {pending && <LoaderCircle aria-hidden className="animate-spin" />}
                            Remove vote
                        </Button>
                    ) : canAct ? (
                        <Button
                            ref={actionRef}
                            variant="outline"
                            className="hover:bg-primary hover:text-primary-foreground hover:border-primary h-10 min-w-20 bg-transparent"
                            disabled={pending}
                            aria-busy={pending || undefined}
                            aria-label={`Vote for ${game.name}`}
                            onClick={() => onVote(game)}
                        >
                            {pending && <LoaderCircle aria-hidden className="animate-spin" />}
                            Vote
                        </Button>
                    ) : null /* Today's action is used: the status says so, so no row of disabled buttons. */
                }
            </div>

            <span className="hidden text-right text-sm tabular-nums sm:block">{votesLabel(game.votes)}</span>

            {/* Non-modal: a modal menu that opens a dialog can leave pointer-events stuck on the page. */}
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="size-10" aria-label={`More actions for ${game.name}`}>
                        <Ellipsis aria-hidden />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem className="text-destructive focus:text-destructive" onSelect={() => onRemove(game)}>
                        <Trash2 aria-hidden className="text-destructive" />
                        Remove game…
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </li>
    );
}
