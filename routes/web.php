<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('games.index');
    Route::post('games', [GameController::class, 'store'])->name('games.store');

    // {game} is the Essense API's game ID.
    Route::whereNumber('game')->group(function () {
        Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
        Route::post('games/{game}/vote', [VoteController::class, 'store'])->name('votes.store');
        Route::delete('games/{game}/vote', [VoteController::class, 'destroy'])->name('votes.destroy');
    });
});

require __DIR__.'/auth.php';
