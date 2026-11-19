import { cn } from '@/lib/utils';

/**
 * Today's action as a physical game token: the page's one bold element.
 *
 * - available: in the status, ready to be placed.
 * - spent: the empty spot in the status once today's action is used.
 * - placed: on the row of the game your vote went to.
 *
 * Decorative (aria-hidden): the sentence or label beside it always says the same thing in words.
 * The disc's edge uses brand-line (claret in light, Volt in dark) because Volt on white is 1.2:1.
 */
type Props = {
    state: 'available' | 'spent' | 'placed';
    size?: 'lg' | 'sm';
    className?: string;
    /** React 19 passes ref as a prop; the row uses it to fly the token in. */
    ref?: React.Ref<HTMLSpanElement>;
};

export function ActionToken({ state, size = 'lg', className, ref }: Props) {
    return (
        <span
            ref={ref}
            aria-hidden
            data-token={state}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full font-bold tabular-nums',
                size === 'lg' ? 'size-12 text-xl' : 'size-6 text-xs',
                state === 'spent' ? 'border-input border-2 border-dashed' : 'bg-token text-token-foreground border-brand-line border-2',
                className,
            )}
        >
            {state !== 'spent' && '1'}
        </span>
    );
}
