<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Services\Essense\Exceptions\EssenseException;
use App\Services\Library\GameLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin HTTP layer over GameLibrary. Failures from changes are turned into user-facing messages
 * in one place: App\Exceptions\RenderUserFacingErrors.
 */
class GameController extends Controller
{
    public function __construct(private readonly GameLibrary $library) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // If the API is down, still render the page (with a retry message) rather than an error page.
        try {
            $games = $this->library->games($user);
            $loadError = null;
        } catch (EssenseException) {
            $games = null;
            $loadError = "We couldn't load the game library. Please try again in a moment.";
        }

        return Inertia::render('games/index', [
            'games' => $games,
            'status' => $this->library->todayStatus($user),
            'loadError' => $loadError,
        ]);
    }

    public function store(StoreGameRequest $request): RedirectResponse
    {
        $game = $this->library->addGame($request->user(), $request->validated('name'));

        return back()->with('success', "Added “{$game->name}”.");
    }

    public function destroy(Request $request, int $game): RedirectResponse
    {
        $removed = $this->library->removeGame($request->user(), $game);

        return back()->with('success', "Removed “{$removed->name}”.");
    }
}
