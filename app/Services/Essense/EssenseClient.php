<?php

namespace App\Services\Essense;

use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin adapter over the Essense Code Challenge API.
 *
 * It sends requests the way the API requires and turns its inconsistent responses into return
 * values and exceptions. It holds no business rules: those live in the layer that uses it.
 * Observed API behavior is documented in docs/api-notes.md.
 */
final class EssenseClient implements EssenseApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 5,
    ) {}

    /**
     * @return list<Game> In the API's order (by ID), not by votes.
     */
    public function listGames(): array
    {
        $data = $this->send('/games/list', retry: true);

        if (! isset($data['games']) || ! is_array($data['games'])) {
            throw $this->unexpected('/games/list');
        }

        return array_values(array_map(fn (mixed $game) => $this->toGame($game, '/games/list'), $data['games']));
    }

    /**
     * Returns null when the game doesn't exist (the API answers {}).
     */
    public function findGame(int $id): ?Game
    {
        $data = $this->send('/games/search', ['id' => $id], retry: true);

        return $data === [] ? null : $this->toGame($data, '/games/search');
    }

    /**
     * @return int The new game's ID.
     *
     * @throws GameRejected When the API refuses the name ({"id": -1}).
     */
    public function addGame(string $name): int
    {
        $data = $this->send('/games/add', ['name' => $name]);

        if (! isset($data['id']) || ! is_numeric($data['id'])) {
            throw $this->unexpected('/games/add');
        }

        $id = (int) $data['id'];
        if ($id <= 0) {
            throw new GameRejected;
        }

        return $id;
    }

    /**
     * @throws GameNotFound
     */
    public function vote(int $id): void
    {
        $this->mutate('/games/vote', $id);
    }

    /**
     * Note: on a game with 0 votes this succeeds without changing anything (docs/api-notes.md,
     * finding 7), so callers must check their own records before calling it.
     *
     * @throws GameNotFound
     */
    public function removeVote(int $id): void
    {
        $this->mutate('/games/removeVote', $id);
    }

    /**
     * @throws GameNotFound
     */
    public function removeGame(int $id): void
    {
        $this->mutate('/games/remove', $id);
    }

    /**
     * vote / removeVote / remove all answer {"success": true|false} with HTTP 200.
     */
    private function mutate(string $path, int $id): void
    {
        $data = $this->send($path, ['id' => $id]);

        match ($data['success'] ?? null) {
            true => null,
            false => throw new GameNotFound($id),
            default => throw $this->unexpected($path),
        };
    }

    /**
     * Every request goes through here so the API's requirements are applied in one place.
     *
     * @param  array<string, scalar>  $params
     * @param  bool  $retry  Only for reads. Mutations are never retried: if a vote times out
     *                       after the API counted it, a retry would count it twice.
     * @return array<mixed> The decoded JSON body.
     */
    private function send(string $path, array $params = [], bool $retry = false): array
    {
        if ($this->apiKey === '') {
            throw $this->misconfigured(EssenseMisconfigured::missingKey(), $path);
        }

        $request = Http::baseUrl($this->baseUrl)
            ->asForm()        // The API ignores JSON bodies (finding 3).
            ->acceptJson()    // Without it, routing errors come back as HTML (finding 8).
            ->connectTimeout(3)
            ->timeout($this->timeout);

        if ($retry) {
            // 3 attempts in total, only after connection failures, 200 ms apart.
            $request = $request->retry(3, 200, fn (Throwable $e) => $e instanceof ConnectionException, throw: false);
        }

        try {
            // The key goes in the form body, never the URL, so it can't end up in logged URLs.
            $response = $request->post($path, ['api_key' => $this->apiKey, ...$params]);
        } catch (ConnectionException $e) {
            Log::warning('Essense API unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            throw EssenseUnavailable::connectionFailed($path, $e);
        }

        $status = $response->status();
        $json = $response->json();

        if ($response->serverError()) {
            Log::warning('Essense API server error', ['path' => $path, 'status' => $status]);

            throw EssenseUnavailable::serverError($path, $status);
        }

        if ($response->clientError()) {
            // 400 MISSING_KEY / 403 INVALID_KEY ({"error": ...}), or a routing 404/405 ({"detail": ...}).
            $reason = is_array($json) ? ($json['error'] ?? null) : null;

            throw $this->misconfigured(
                EssenseMisconfigured::rejected($path, $status, is_string($reason) ? $reason : "HTTP_{$status}"),
                $path,
            );
        }

        if (! is_array($json)) {
            throw $this->unexpected($path);
        }

        return $json;
    }

    private function toGame(mixed $data, string $path): Game
    {
        if (! is_array($data) || ! isset($data['id'], $data['name'], $data['votes'])) {
            throw $this->unexpected($path);
        }

        return Game::fromApi($data);
    }

    private function unexpected(string $path): EssenseUnavailable
    {
        Log::warning('Essense API returned an unexpected response', ['path' => $path]);

        return EssenseUnavailable::unexpectedResponse($path);
    }

    private function misconfigured(EssenseMisconfigured $e, string $path): EssenseMisconfigured
    {
        // Critical: nothing works until someone fixes the config. The key itself is never logged.
        Log::critical('Essense API misconfigured', ['path' => $path, 'reason' => $e->reason]);

        return $e;
    }
}
