<?php

namespace App\Services\Essense;

use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;

/**
 * The Essense API's game operations. EssenseClient is the real implementation;
 * tests swap in an in-memory fake through the service container.
 *
 * Every method can also throw EssenseUnavailable or EssenseMisconfigured.
 *
 * @see EssenseUnavailable
 * @see EssenseMisconfigured
 */
interface EssenseApi
{
    /**
     * @return list<Game> In the API's order (by ID), not by votes.
     */
    public function listGames(): array;

    /**
     * Returns null when the game doesn't exist.
     */
    public function findGame(int $id): ?Game;

    /**
     * @return int The new game's ID.
     *
     * @throws GameRejected
     */
    public function addGame(string $name): int;

    /**
     * @throws GameNotFound
     */
    public function vote(int $id): void;

    /**
     * Succeeds without changing anything on a game with 0 votes.
     *
     * @throws GameNotFound
     */
    public function removeVote(int $id): void;

    /**
     * @throws GameNotFound
     */
    public function removeGame(int $id): void;

    /**
     * Delete every game. The API then restarts game IDs at 1 (docs/api-notes.md, finding 10).
     */
    public function flushGames(): void;
}
