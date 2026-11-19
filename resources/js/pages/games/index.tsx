/*
 * THESIS: Where you stand today sits beside the ranking, never buried above it. Refuses the
 * stacked "form card on top, list below" starter page.
 * OWN-WORLD: shadcn components on a neutral ground. Burgundy #420217 and lime #D0FF15 swap roles
 * by theme; lime fills the Today panel while an action is available; your vote is a tinted,
 * ringed row with a "Your vote" badge.
 * STORY: See the ranking, know if you can act today, vote or suggest once, and take a vote back.
 * FIRST VIEWPORT: Desktop: sticky Today panel (status, reset time, suggest form) left, ranking
 * right. Mobile: status strip with a Suggest button above the ranking.
 * FORM: Two zones, candidate 3 of 5; seed key d0a29466.
 * FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
 */
import { FlashMessage } from '@/components/games/flash-message';
import { GameList } from '@/components/games/game-list';
import { LibraryHeader } from '@/components/games/library-header';
import { SuggestGameForm } from '@/components/games/suggest-game-form';
import { TodayStatus } from '@/components/games/today-status';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useMediaQuery } from '@/hooks/use-media-query';
import { type SharedData } from '@/types';
import { type GamesPageProps } from '@/types/games';
import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlert, Plus, RotateCw } from 'lucide-react';
import { useState } from 'react';

export default function GamesIndex({ games, status, loadError }: GamesPageProps) {
    const { auth, flash } = usePage<SharedData>().props;
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    const [sheetOpen, setSheetOpen] = useState(false);
    const [prefill, setPrefill] = useState({ name: '', version: 0 });

    // "Suggest 'X'" from an empty filter result: carry the text into the form.
    const suggest = (name: string) => {
        setPrefill((p) => ({ name, version: p.version + 1 }));
        if (!isDesktop) setSheetOpen(true);
    };

    // "You voted for 'X'" in the status jumps to that row (it can be far down a long list).
    const [reveal, setReveal] = useState<{ id: number; n: number } | null>(null);
    const votedGame = games?.find((g) => g.votedByMe && g.name === status.gameName);
    const showVote = votedGame ? () => setReveal((r) => ({ id: votedGame.id, n: (r?.n ?? 0) + 1 })) : undefined;

    const form = (
        <SuggestGameForm
            key={prefill.version}
            canAct={status.canAct}
            initialName={prefill.name}
            autoFocus={prefill.version > 0 || !isDesktop}
            onAdded={() => setSheetOpen(false)}
        />
    );

    return (
        <>
            <Head title="Ranking" />
            <LibraryHeader />

            <main className="mx-auto max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:pt-10">
                <h1 className="sr-only">Office Game Library</h1>

                {/* The action message lives in the sticky region with the status, so an error is on screen
                    wherever the user has scrolled, and the ranking never jumps when a message appears. */}
                <div className="lg:grid lg:grid-cols-[21rem_minmax(0,1fr)] lg:items-start lg:gap-12">
                    {isDesktop ? (
                        <aside data-sticky-status className="sticky top-6 space-y-8">
                            <div>
                                <FlashMessage flash={flash} />
                                <TodayStatus status={status} timezone={auth.user.timezone} onShowVote={showVote} />
                            </div>
                            {form}
                        </aside>
                    ) : (
                        <div data-sticky-status className="bg-background sticky top-0 z-10 -mx-4 mb-4 px-4 pt-2 pb-2 sm:-mx-6 sm:px-6">
                            <FlashMessage flash={flash} />
                            <TodayStatus status={status} timezone={auth.user.timezone} compact onShowVote={showVote} className="shadow-sm">
                                {status.canAct && (
                                    <Button className="h-10 shrink-0" onClick={() => setSheetOpen(true)}>
                                        <Plus aria-hidden />
                                        Suggest
                                    </Button>
                                )}
                            </TodayStatus>
                        </div>
                    )}

                    {loadError ? (
                        <section aria-labelledby="ranking-heading" className="space-y-4">
                            <h2 id="ranking-heading" className="text-2xl font-semibold tracking-tight">
                                Ranking
                            </h2>
                            <div role="alert" className="rounded-xl border px-6 py-8 text-center">
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

            {!isDesktop && (
                <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                    <SheetContent side="bottom" className="rounded-t-2xl px-4 pt-6 pb-8">
                        <SheetHeader className="mb-4 p-0 text-left">
                            <SheetTitle className="sr-only">Suggest a game</SheetTitle>
                            <SheetDescription className="sr-only">Add a game title to the library. It uses today's action.</SheetDescription>
                        </SheetHeader>
                        {form}
                    </SheetContent>
                </Sheet>
            )}
        </>
    );
}
