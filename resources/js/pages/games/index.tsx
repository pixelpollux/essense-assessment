// TEMPORARY: a plain, working page so the full flow can be used and tested end to end.
// The designed version replaces this in the UI branch.
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { type DailyStatus, type Game, type GamesPageProps } from '@/types/games';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

function statusText(status: DailyStatus): string {
    if (!status.canAct) {
        return status.usedFor === 'add'
            ? `Today's action used: you added “${status.gameName}”.`
            : `Today's action used: you voted for “${status.gameName}”.`;
    }
    if (status.refundReason === 'game_removed') {
        return `“${status.gameName}” was removed, so today's action was given back. You have 1 action left today.`;
    }
    return 'You have 1 action left today: vote for a game or add a new one.';
}

export default function GamesIndex({ games, status, loadError }: GamesPageProps) {
    const { flash } = usePage<SharedData>().props;
    const form = useForm({ name: '' });

    const addGame: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('games.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const removeGame = (game: Game) => {
        const votes = game.votes === 1 ? '1 vote' : `${game.votes} votes`;
        if (window.confirm(`Remove “${game.name}” (${votes}) from the library for everyone?`)) {
            router.delete(route('games.destroy', game.id), { preserveScroll: true });
        }
    };

    return (
        <main className="mx-auto max-w-2xl space-y-6 p-4">
            <Head title="Game library" />

            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Game library</h1>
                <Link href={route('logout')} method="post" as="button" className="text-sm underline">
                    Log out
                </Link>
            </div>

            <div aria-live="polite" className="space-y-2">
                {flash.success && <p className="text-green-700 dark:text-green-400">{flash.success}</p>}
                {flash.error && <p className="text-red-700 dark:text-red-400">{flash.error}</p>}
            </div>

            <p aria-live="polite">{statusText(status)}</p>

            <form onSubmit={addGame} className="space-y-2">
                <Label htmlFor="name">Suggest a game</Label>
                <div className="flex gap-2">
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        maxLength={100}
                        aria-invalid={form.errors.name ? true : undefined}
                        aria-describedby={form.errors.name ? 'name-error' : undefined}
                    />
                    <Button type="submit" disabled={form.processing || !status.canAct}>
                        Add
                    </Button>
                </div>
                <InputError id="name-error" message={form.errors.name} />
            </form>

            {loadError ? (
                <div role="alert" className="space-y-2">
                    <p>{loadError}</p>
                    <Button variant="outline" onClick={() => router.reload()}>
                        Try again
                    </Button>
                </div>
            ) : games && games.length === 0 ? (
                <p>No games yet. Suggest the first one!</p>
            ) : (
                <ol className="divide-y">
                    {games?.map((game) => (
                        <li key={game.id} className="flex items-center justify-between gap-2 py-2">
                            <span>
                                {game.name} · {game.votes === 1 ? '1 vote' : `${game.votes} votes`}
                                {game.votedByMe && ' · You voted'}
                            </span>
                            <span className="flex gap-2">
                                {game.votedByMe ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-label={`Remove your vote for ${game.name}`}
                                        onClick={() => router.delete(route('votes.destroy', game.id), { preserveScroll: true })}
                                    >
                                        Remove vote
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        disabled={!status.canAct}
                                        aria-label={`Vote for ${game.name}`}
                                        onClick={() => router.post(route('votes.store', game.id), {}, { preserveScroll: true })}
                                    >
                                        Vote
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    aria-label={`Remove ${game.name} from the library`}
                                    onClick={() => removeGame(game)}
                                >
                                    Remove
                                </Button>
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </main>
    );
}
