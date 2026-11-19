<?php

namespace Tests\Fakes;

use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;
use App\Services\Essense\Game;
use Throwable;

/**
 * In-memory stand-in for the Essense API, with the real API's behavior (docs/api-notes.md):
 * unknown IDs throw GameNotFound, blank names are rejected, removeVote at 0 is a silent
 * no-op, and flush() restarts IDs at 1.
 */
final class FakeEssenseApi implements EssenseApi
{
    /** @var array<int, array{name: string, votes: int}> */
    private array $games = [];

    private int $nextId = 1;

    /** @var array<string, array{exception: Throwable, afterApplying: bool}> */
    private array $failures = [];

    /** @var list<string> Names of the mutating methods called, in order. */
    public array $mutations = [];

    // --- Test setup and inspection ------------------------------------------------------

    /** Add a game directly, as if someone created it outside the app. Returns its ID. */
    public function withGame(string $name, int $votes = 0): int
    {
        $id = $this->nextId++;
        $this->games[$id] = ['name' => $name, 'votes' => $votes];

        return $id;
    }

    /** Simulate /cache/flush: all games gone, IDs restart at 1. */
    public function flush(): void
    {
        $this->games = [];
        $this->nextId = 1;
    }

    /** Remove a game directly, as if someone removed it outside the app. */
    public function removeDirectly(int $id): void
    {
        unset($this->games[$id]);
    }

    /**
     * Make the next call to $method throw. With $afterApplying, the change is applied first,
     * like a timeout that happens after the real API already counted the request.
     */
    public function failNext(string $method, Throwable $exception, bool $afterApplying = false): void
    {
        $this->failures[$method] = ['exception' => $exception, 'afterApplying' => $afterApplying];
    }

    public function votesFor(int $id): int
    {
        return $this->games[$id]['votes'] ?? throw new \LogicException("No game {$id} in the fake API.");
    }

    public function has(int $id): bool
    {
        return isset($this->games[$id]);
    }

    public function nameOf(int $id): string
    {
        return $this->games[$id]['name'] ?? throw new \LogicException("No game {$id} in the fake API.");
    }

    public function gameCount(): int
    {
        return count($this->games);
    }

    // --- EssenseApi -----------------------------------------------------------------------

    public function listGames(): array
    {
        return $this->run(__FUNCTION__, fn () => array_values(array_map(
            fn (int $id) => new Game($id, $this->games[$id]['name'], $this->games[$id]['votes']),
            array_keys($this->games),
        )));
    }

    public function findGame(int $id): ?Game
    {
        return $this->run(__FUNCTION__, fn () => isset($this->games[$id])
            ? new Game($id, $this->games[$id]['name'], $this->games[$id]['votes'])
            : null);
    }

    public function addGame(string $name): int
    {
        return $this->run(__FUNCTION__, function () use ($name) {
            if (trim($name) === '') {
                throw new GameRejected;
            }

            return $this->withGame($name);
        });
    }

    public function vote(int $id): void
    {
        $this->run(__FUNCTION__, function () use ($id) {
            $this->existing($id);
            $this->games[$id]['votes']++;
        });
    }

    public function removeVote(int $id): void
    {
        $this->run(__FUNCTION__, function () use ($id) {
            $this->existing($id);
            $this->games[$id]['votes'] = max(0, $this->games[$id]['votes'] - 1);
        });
    }

    public function removeGame(int $id): void
    {
        $this->run(__FUNCTION__, function () use ($id) {
            $this->existing($id);
            unset($this->games[$id]);
        });
    }

    public function flushGames(): void
    {
        $this->run(__FUNCTION__, fn () => $this->flush());
    }

    // --- Internals ------------------------------------------------------------------------

    private function existing(int $id): void
    {
        if (! isset($this->games[$id])) {
            throw new GameNotFound($id);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $apply
     * @return T
     */
    private function run(string $method, callable $apply): mixed
    {
        if (! in_array($method, ['listGames', 'findGame'], true)) {
            $this->mutations[] = $method;
        }

        $failure = $this->failures[$method] ?? null;
        unset($this->failures[$method]);

        if ($failure && ! $failure['afterApplying']) {
            throw $failure['exception'];
        }

        $result = $apply();

        if ($failure) {
            throw $failure['exception'];
        }

        return $result;
    }
}
