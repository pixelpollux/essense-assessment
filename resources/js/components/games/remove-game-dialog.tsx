import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type Game } from '@/types/games';
import { LoaderCircle } from 'lucide-react';

type Props = {
    game: Game | null;
    removing: boolean;
    onConfirm: (game: Game) => void;
    onOpenChange: (open: boolean) => void;
    /** Where focus goes on close. The dialog has no DialogTrigger, so Radix can't return focus by itself. */
    onCloseAutoFocus: (event: Event) => void;
};

function consequence(game: Game): string {
    if (game.votes === 0) return 'It has no votes yet. It will be removed from the library for everyone.';
    const votes = game.votes === 1 ? '1 vote' : `${game.votes} votes`;
    return `It has ${votes}, which will be removed for everyone. Anyone who voted for it today gets their action back.`;
}

export function RemoveGameDialog({ game, removing, onConfirm, onOpenChange, onCloseAutoFocus }: Props) {
    return (
        <Dialog open={game !== null} onOpenChange={onOpenChange}>
            <DialogContent onCloseAutoFocus={onCloseAutoFocus}>
                {game && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="text-balance">Remove “{game.name}”?</DialogTitle>
                            <DialogDescription>{consequence(game)}</DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Keep game</Button>
                            </DialogClose>
                            <Button variant="destructive" disabled={removing} onClick={() => onConfirm(game)}>
                                {removing && <LoaderCircle aria-hidden className="animate-spin" />}
                                Remove game
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
