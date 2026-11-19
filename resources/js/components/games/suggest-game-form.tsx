import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

const MAX_LENGTH = 100; // Matches StoreGameRequest (CLAUDE.md, decision 8).

type Props = {
    canAct: boolean;
    initialName?: string;
    autoFocus?: boolean;
    onAdded?: () => void;
};

export function SuggestGameForm({ canAct, initialName = '', autoFocus = false, onAdded }: Props) {
    const form = useForm({ name: initialName });
    const length = form.data.name.length;
    const nearLimit = length > MAX_LENGTH - 10;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('games.store'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.reset();
                onAdded?.();
            },
        });
    };

    // Once today's action is used, a disabled form is just noise: say when suggesting opens again.
    if (!canAct) {
        return (
            <div className="space-y-1">
                <h2 className="text-base font-semibold">Suggest a game</h2>
                <p className="text-muted-foreground text-sm">You can suggest a game after midnight, when your next action is available.</p>
            </div>
        );
    }

    const describedBy = ['name-count', form.errors.name && 'name-error'].filter(Boolean).join(' ');

    return (
        <form onSubmit={submit} noValidate className="space-y-2">
            <Label htmlFor="name" className="text-base font-semibold">
                Suggest a game
            </Label>
            <Input
                id="name"
                name="name"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
                placeholder="e.g. Ticket to Ride"
                maxLength={MAX_LENGTH}
                autoComplete="off"
                autoFocus={autoFocus}
                aria-invalid={form.errors.name ? true : undefined}
                aria-describedby={describedBy}
                className="h-11"
            />
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <InputError id="name-error" message={form.errors.name} />
                </div>
                <p id="name-count" className={cn('text-sm tabular-nums', nearLimit ? 'text-foreground font-medium' : 'text-muted-foreground')}>
                    <span className="sr-only">Characters used: </span>
                    {length}/{MAX_LENGTH}
                </p>
            </div>
            <Button type="submit" className="h-11 w-full" disabled={form.processing}>
                {form.processing && <LoaderCircle aria-hidden className="animate-spin" />}
                Suggest game
            </Button>
            <p className="text-muted-foreground text-sm">Suggesting uses today's action, just like a vote.</p>
        </form>
    );
}
