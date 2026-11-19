<?php

namespace App\Exceptions;

use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;
use App\Services\Library\Exceptions\AlreadyVoted;
use App\Services\Library\Exceptions\DailyLimitReached;
use App\Services\Library\Exceptions\DuplicateGame;
use App\Services\Library\Exceptions\NotVoted;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Turns failures from the game library into friendly messages: every user-facing error string for
 * changes lives here. Registered in bootstrap/app.php.
 *
 * Only for requests that change something (POST/DELETE). Inertia expects a redirect from those, so
 * each failure redirects back with either an error on the form field or a flash message. Pages
 * (GET) handle their own load errors. Returns null for anything else, so Laravel's normal error
 * handling applies.
 */
final class RenderUserFacingErrors
{
    public function __invoke(Throwable $e, Request $request): ?RedirectResponse
    {
        if ($request->isMethodSafe()) {
            return null;
        }

        return match (true) {
            $e instanceof DuplicateGame => $this->fieldError('name', "“{$e->existingName}” is already in the library."),
            $e instanceof GameRejected => $this->fieldError('name', "That title couldn't be saved. Try a different one."),
            $e instanceof DailyLimitReached => $this->flashError("You've already used today's action. You can vote or add again after midnight."),
            $e instanceof AlreadyVoted => $this->flashError("You've already voted for this game."),
            $e instanceof NotVoted => $this->flashError("You haven't voted for this game, so there's no vote to remove."),
            $e instanceof GameNotFound => $this->flashError("That game was already removed, so we've refreshed the list."),
            // A timeout on vote/add is ambiguous: the API may have applied it (CLAUDE.md, decision 7).
            $e instanceof EssenseUnavailable => $this->flashError(match ($request->route()?->getName()) {
                'votes.store' => "We couldn't confirm your vote. Check the vote count before trying again.",
                'games.store' => "We couldn't confirm the game was added. Check the list before trying again.",
                default => "Couldn't reach the game library. Please try again in a moment.",
            }),
            // Details (bad key, wrong path) are logged as critical by the client, never shown.
            $e instanceof EssenseMisconfigured => $this->flashError("The game library isn't available right now."),
            $e instanceof LockTimeoutException => $this->flashError('Someone else is adding a game right now. Try again in a moment.'),
            default => null,
        };
    }

    private function flashError(string $message): RedirectResponse
    {
        return back()->with('error', $message);
    }

    private function fieldError(string $field, string $message): RedirectResponse
    {
        return back()->withErrors([$field => $message])->withInput();
    }
}
