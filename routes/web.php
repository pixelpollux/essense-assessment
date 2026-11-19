<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('games.index');
    Route::post('games', [GameController::class, 'store'])->name('games.store');

    // {game} is the Essense API's game ID.
    Route::whereNumber('game')->group(function () {
        Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
        Route::post('games/{game}/vote', [VoteController::class, 'store'])->name('votes.store');
        Route::delete('games/{game}/vote', [VoteController::class, 'destroy'])->name('votes.destroy');
    });

    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/auth.php';
