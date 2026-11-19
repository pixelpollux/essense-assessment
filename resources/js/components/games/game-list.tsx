import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRankAnimation } from '@/hooks/use-rank-animation';
import { type Game } from '@/types/games';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { GameRow } from './game-row';
import { RemoveGameDialog } from './remove-game-dialog';

const FILTER_FROM = 9; // Show the filter once the list is longer than a glance.

type Props = {
    games: Game[];
    canAct: boolean;
    onSuggest: (name: string) => void;
    /** "Jump to my vote": bump `n` to clear the filter and focus that game's Remove vote button. */
    reveal?: { id: number; n: number } | null;
};

/** Competition ranking: tied vote counts share a rank (1, 2, 2, 4). The server already sorts. */
function ranks(games: Game[]): Map<number, number> {
    const result = new Map<number, number>();
    games.forEach((game, index) => {
        const tiedWithPrevious = index > 0 && games[index - 1].votes === game.votes;
        result.set(game.id, tiedWithPrevious ? result.get(games[index - 1].id)! : index + 1);
    });
    return result;
}

export function GameList({ games, canAct, onSuggest, reveal = null }: Props) {
    const listRef = useRef<HTMLOListElement>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const [filter, setFilter] = useState('');
    const [pendingId, setPendingId] = useState<number | null>(null);
    const [focusRequest, setFocusRequest] = useState<{ id: number; n: number } | null>(null);
    const requestFocus = (id: number) => setFocusRequest((r) => ({ id, n: (r?.n ?? 0) + 1 }));

    useEffect(() => {
        if (!reveal) return;
        setFilter('');
        setFocusRequest((r) => ({ id: reveal.id, n: (r?.n ?? 0) + 1 }));
    }, [reveal]);
    const [toRemove, setToRemove] = useState<Game | null>(null);
    const [removing, setRemoving] = useState(false);

    const rankById = useMemo(() => ranks(games), [games]);
    const query = filter.trim().toLowerCase().replace(/\s+/g, ' ');
    const shown = query ? games.filter((g) => g.name.toLowerCase().replace(/\s+/g, ' ').includes(query)) : games;

    useRankAnimation(listRef, shown.map((g) => g.id).join(','));

    const changeVote = (game: Game, method: 'post' | 'delete') => {
        setPendingId(game.id);
        router.visit(route(method === 'post' ? 'votes.store' : 'votes.destroy', game.id), {
            method,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => requestFocus(game.id),
            onFinish: () => setPendingId(null),
        });
    };

    const removeGame = (game: Game) => {
        setRemoving(true);
        router.delete(route('games.destroy', game.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => headingRef.current?.focus(),
            onFinish: () => {
                setRemoving(false);
                setToRemove(null);
            },
        });
    };

    return (
        <section aria-labelledby="ranking-heading" className="space-y-4">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id="ranking-heading" ref={headingRef} tabIndex={-1} className="text-2xl font-semibold tracking-tight outline-none">
                    Ranking
                </h2>
                <p className="text-muted-foreground text-sm tabular-nums">{games.length === 1 ? '1 game' : `${games.length} games`}</p>
            </div>

            {games.length >= FILTER_FROM && (
                <div className="relative">
                    <Label htmlFor="filter" className="sr-only">
                        Filter games
                    </Label>
                    <Search aria-hidden className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                    <Input
                        id="filter"
                        type="search"
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                        placeholder="Filter games"
                        autoComplete="off"
                        className="h-11 pl-9"
                    />
                </div>
            )}

            {games.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-10 text-center">
                    <p className="text-lg font-semibold">No games yet</p>
                    <p className="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                        Suggest the first one. The game with the most votes rises to the top.
                    </p>
                </div>
            ) : shown.length === 0 ? (
                <div className="rounded-xl border border-dashed px-6 py-8 text-center">
                    <p className="font-medium">No games match “{filter.trim()}”.</p>
                    <Button variant="link" className="mt-1" onClick={() => onSuggest(filter.trim())}>
                        Suggest “{filter.trim()}”
                    </Button>
                </div>
            ) : (
                <ol ref={listRef} className="space-y-1">
                    {shown.map((game) => (
                        <GameRow
                            key={game.id}
                            game={game}
                            rank={rankById.get(game.id)!}
                            canAct={canAct}
                            pending={pendingId === game.id}
                            focusToken={focusRequest?.id === game.id ? focusRequest.n : 0}
                            onVote={(g) => changeVote(g, 'post')}
                            onRemoveVote={(g) => changeVote(g, 'delete')}
                            onRemove={setToRemove}
                        />
                    ))}
                </ol>
            )}

            {query && shown.length > 0 && (
                <p className="text-muted-foreground text-sm" aria-live="polite">
                    Showing {shown.length} of {games.length} games.
                </p>
            )}

            <RemoveGameDialog game={toRemove} removing={removing} onConfirm={removeGame} onOpenChange={(open) => !open && setToRemove(null)} />
        </section>
    );
}
