/*
 * THESIS: Today's action is a game token you place, once a day. The token is the only bold element;
 * everything else is a quiet single column of type and hairlines. Refuses cards and dashboard panels.
 * OWN-WORLD: Schibsted Grotesk on Paper (light) or claret Night (dark). Claret #420217 and Volt
 * #D0FF15 kept exactly: Volt only on the token and the dark primary; claret-tinted neutrals.
 * STORY: Read where you stand in one sentence, see the ranking, place your token (vote) or suggest
 * a game, and take it back the same day.
 * FIRST VIEWPORT: The status sentence beside the token, with the suggest field inline; the ranking
 * below. One left-aligned 42rem column on every screen; a compact status bar once it scrolls away.
 * FORM: frontend-design redesign of the "two zones" page (impeccable seed d0a29466 superseded).
 */
import { FlashMessage } from '@/components/games/flash-message';
import { GameList } from '@/components/games/game-list';
import { LibraryHeader } from '@/components/games/library-header';
import { SuggestGameForm } from '@/components/games/suggest-game-form';
import { StatusBar, TodayStatus } from '@/components/games/today-status';
import { Button } from '@/components/ui/button';
import { useFlashWithNetworkFailures } from '@/hooks/use-network-failures';
import { type SharedData } from '@/types';
import { type GamesPageProps } from '@/types/games';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlert, RotateCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** True once the element has scrolled up out of view (used to show the compact status bar). */
function useScrolledPast(ref: React.RefObject<HTMLElement | null>): boolean {
    const [past, setPast] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const observer = new IntersectionObserver(([entry]) => setPast(!entry.isIntersecting && entry.boundingClientRect.top < 0));
        observer.observe(el);
        return () => observer.disconnect();
    }, [ref]);

    return past;
}

export default function GamesIndex({ games, status, loadError }: GamesPageProps) {
    const { auth, flash } = usePage<SharedData>().props;
    // Server messages, plus network failures that never reached the server (no silent failures).
    const shownFlash = useFlashWithNetworkFailures(flash);
    const statusRef = useRef<HTMLDivElement>(null);
    const showBar = useScrolledPast(statusRef);
    const [prefill, setPrefill] = useState({ name: '', version: 0 });

    // "Suggest 'X'" from an empty filter result: carry the text into the form and focus it.
    const suggest = (name: string) => {
        setPrefill((p) => ({ name, version: p.version + 1 }));
        statusRef.current?.scrollIntoView({ block: 'start' });
    };

    // "Your action today went to 'X'" jumps to that row (it can be far down a long list).
    const [reveal, setReveal] = useState<{ id: number; n: number } | null>(null);
    const votedGame = games?.find((g) => g.votedByMe && g.name === status.gameName);
    const showVote = votedGame ? () => setReveal((r) => ({ id: votedGame.id, n: (r?.n ?? 0) + 1 })) : undefined;

    return (
        <>
            <Head title="Ranking" />
            <LibraryHeader />
            {showBar && <StatusBar status={status} onShowVote={showVote} />}

            <main className="mx-auto max-w-2xl px-4 pt-10 pb-32 sm:px-6 sm:pt-14">
                <h1 className="sr-only">Office Game Library</h1>

                <div ref={statusRef}>
                    <TodayStatus status={status} timezone={auth.user.timezone} onShowVote={showVote}>
                        {status.canAct && (
                            <SuggestGameForm
                                key={prefill.version}
                                canAct
                                initialName={prefill.name}
                                autoFocus={prefill.version > 0}
                                onAdded={() => document.getElementById('today-heading')?.focus({ preventScroll: true })}
                            />
                        )}
                    </TodayStatus>
                </div>

                <div className="mt-14">
                    {loadError ? (
                        <section aria-labelledby="ranking-heading">
                            <h2 id="ranking-heading" className="text-xl font-bold tracking-tight">
                                Ranking
                            </h2>
                            <div role="alert" className="mt-4 border-y py-10 text-center">
                                <CircleAlert aria-hidden className="text-destructive mx-auto mb-2 size-6" />
                                <p className="font-medium">{loadError}</p>
                                <Button variant="outline" className="mt-4 h-10" onClick={() => router.reload()}>
                                    <RotateCw aria-hidden />
                                    Try again
                                </Button>
                            </div>
                        </section>
                    ) : (
                        <GameList games={games ?? []} canAct={status.canAct} onSuggest={suggest} reveal={reveal} />
                    )}
                </div>
            </main>

            <FlashMessage flash={shownFlash} />
        </>
    );
}
