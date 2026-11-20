import { Button } from '@/components/ui/button';
import { type Flash } from '@/types';
import { CircleAlert, CircleCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * The message from the last action. Both live regions are always in the DOM and only their text
 * changes: screen readers often skip a live region that's inserted together with its content.
 * Success is announced politely; an error interrupts, because the action didn't happen.
 *
 * Nothing disappears on its own: the user dismisses it (it sits in the sticky area, so it must not
 * crowd a phone screen), or the next action replaces it. After dismissing, focus moves to the Today
 * heading so keyboard users aren't left on the page body.
 */
export function FlashMessage({ flash }: { flash: Flash }) {
    const [dismissed, setDismissed] = useState(false);

    // Every response carries a new flash object, so a new action shows its message again.
    useEffect(() => setDismissed(false), [flash]);

    const dismiss = () => {
        setDismissed(true);
        document.getElementById('today-heading')?.focus({ preventScroll: true });
    };

    const error = !dismissed && flash.error;
    const success = !dismissed && !flash.error && flash.success;

    // Pinned to the bottom of the screen, so a message is visible at any scroll depth.
    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4">
            <div className="pointer-events-auto mx-auto max-w-2xl space-y-2">
                <div role="alert">
                    {error && (
                        <Message tone="error" onDismiss={dismiss}>
                            {error}
                        </Message>
                    )}
                </div>
                <div role="status">
                    {success && (
                        <Message tone="success" onDismiss={dismiss}>
                            {success}
                        </Message>
                    )}
                </div>
            </div>
        </div>
    );
}

function Message({ tone, onDismiss, children }: { tone: 'error' | 'success'; onDismiss: () => void; children: React.ReactNode }) {
    const Icon = tone === 'error' ? CircleAlert : CircleCheck;

    return (
        <div
            className={
                tone === 'error'
                    ? 'border-destructive/50 text-destructive bg-card flex items-start gap-2 rounded-lg border py-1 pr-1 pl-4 text-sm font-medium shadow-lg'
                    : 'bg-card flex items-start gap-2 rounded-lg border py-1 pr-1 pl-4 text-sm font-medium shadow-lg'
            }
        >
            <Icon aria-hidden className={tone === 'success' ? 'text-secondary-ink mt-2.5 size-4 shrink-0' : 'mt-2.5 size-4 shrink-0'} />
            <p className="min-w-0 flex-1 py-2">{children}</p>
            <Button variant="ghost" size="icon" className="size-9 shrink-0" aria-label="Dismiss message" onClick={onDismiss}>
                <X aria-hidden />
            </Button>
        </div>
    );
}
