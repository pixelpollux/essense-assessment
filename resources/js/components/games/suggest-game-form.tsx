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

    // Once today's action is used the form disappears: the status sentence already says why.
    if (!canAct) return null;

    const describedBy = ['name-count', form.errors.name && 'name-error'].filter(Boolean).join(' ');

    return (
        <form onSubmit={submit} noValidate>
            <Label htmlFor="name" className="text-sm font-medium">
                Suggest a game
            </Label>
            <div className="mt-2 flex gap-2">
                <Input
                    id="name"
                    name="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="e.g. Ticket to Ride"
                    autoComplete="off"
                    autoFocus={autoFocus}
                    aria-invalid={form.errors.name ? true : undefined}
                    aria-describedby={describedBy}
                    className="h-11 min-w-0 flex-1"
                />
                <Button type="submit" className="h-11 shrink-0 px-5" disabled={form.processing}>
                    {form.processing && <LoaderCircle aria-hidden className="animate-spin" />}
                    Suggest
                </Button>
            </div>
            <div className="mt-2 flex items-start justify-between gap-3">
                <div className="min-w-0" aria-live="polite" aria-atomic="true">
                    <InputError id="name-error" message={form.errors.name} />
                </div>
                <p id="name-count" className={cn('text-sm tabular-nums', nearLimit ? 'text-foreground font-medium' : 'text-muted-foreground')}>
                    <span className="sr-only">Characters used: </span>
                    {length}/{MAX_LENGTH}
                </p>
            </div>
        </form>
    );
}
