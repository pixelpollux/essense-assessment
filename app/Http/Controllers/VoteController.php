<?php

namespace App\Http\Controllers;

use App\Services\Library\GameLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The current user's vote on a game. Failures become user-facing messages in
 * App\Exceptions\RenderUserFacingErrors.
 */
class VoteController extends Controller
{
    public function __construct(private readonly GameLibrary $library) {}

    public function store(Request $request, int $game): RedirectResponse
    {
        $voted = $this->library->vote($request->user(), $game);

        return back()->with('success', "Voted for “{$voted->name}”.");
    }

    public function destroy(Request $request, int $game): RedirectResponse
    {
        $unvoted = $this->library->removeVote($request->user(), $game);

        return back()->with('success', "Removed your vote for “{$unvoted->name}”.");
    }
}
