<?php

namespace App\Services\Library;

use App\Enums\ActionType;
use App\Enums\RefundReason;
use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Game;
use App\Services\Library\Exceptions\AlreadyVoted;
use App\Services\Library\Exceptions\DailyLimitReached;
use App\Services\Library\Exceptions\DuplicateGame;
use App\Services\Library\Exceptions\NotVoted;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The office game library's business rules (CLAUDE.md, "Decisions"), on top of the Essense API
 * (which owns games and vote totals) and our votes / daily_actions tables (who did what, when).
 *
 * Ordering rule for every change: make the local change first, in a short transaction, because the
 * database decides who wins a race; then call the API; if the API call fails, undo the local change.
 * The exception is removeGame, where the API call goes first (see there).
 * Never hold a DB transaction open across an HTTP call: SQLite has one writer at a time.
 *
 * Accepted trade-off: an API timeout is ambiguous (the API may have applied the change before the
 * connection dropped). We undo locally anyway and the caller warns the user.
 */
final class GameLibrary
{
    /** Serializes adds (and games:reset, which must not interleave with one). */
    public const ADD_LOCK = 'games:add';

    public function __construct(private readonly EssenseApi $api) {}

    /**
     * All games, most votes first, ties by name A–Z (ignoring case).
     *
     * @return list<LibraryGame>
     */
    public function games(User $user): array
    {
        /** @var array<int, string> $myVotes game_id => game_name */
        $myVotes = $user->votes()->pluck('game_name', 'game_id')->all();

        $games = array_map(
            fn (Game $game) => new LibraryGame(
                id: $game->id,
                name: $game->name,
                votes: $game->votes,
                // ID *and* name must match: a vote from before a flush doesn't count for a new game with the same ID.
                votedByMe: ($myVotes[$game->id] ?? null) === $game->name,
            ),
            $this->api->listGames(),
        );

        usort($games, fn (LibraryGame $a, LibraryGame $b) => [$b->votes, mb_strtolower($a->name), $a->id]
            <=> [$a->votes, mb_strtolower($b->name), $b->id]);

        return $games;
    }

    public function todayStatus(User $user): DailyStatus
    {
        $resetsAt = $this->now($user)->startOfDay()->addDay();
        $action = $this->todaysAction($user)->first();

        return match (true) {
            $action === null => new DailyStatus(true, null, null, null, $resetsAt),
            $action->refunded_at !== null => new DailyStatus(true, null, $action->game_name, $action->refund_reason, $resetsAt),
            default => new DailyStatus(false, $action->type, $action->game_name, null, $resetsAt),
        };
    }

    /**
     * @throws DailyLimitReached
     * @throws DuplicateGame
     */
    public function addGame(User $user, string $name): Game
    {
        $name = Str::squish($name);
        $this->ensureCanActToday($user);

        // Adds run one at a time, so two people can't both pass the duplicate check for the same title.
        return Cache::lock(self::ADD_LOCK, 10)->block(5, function () use ($user, $name): Game {
            $this->ensureNotDuplicate($name);

            $undo = DB::transaction(fn () => $this->claimToday($user, ActionType::Add, null, $name));
            $id = $this->callApiOrUndo(fn () => $this->api->addGame($name), $undo);

            $this->todaysAction($user)->update(['game_id' => $id]);

            return new Game($id, $name, 0);
        });
    }

    /**
     * @return Game The game voted for (its vote count is from before the vote).
     *
     * @throws GameNotFound
     * @throws AlreadyVoted
     * @throws DailyLimitReached
     */
    public function vote(User $user, int $gameId): Game
    {
        $game = $this->api->findGame($gameId) ?? throw new GameNotFound($gameId);

        $existing = $user->votes()->where('game_id', $game->id)->first();
        if ($existing?->game_name === $game->name) {
            throw new AlreadyVoted($game->id);
        }

        $undo = DB::transaction(function () use ($user, $game, $existing): Closure {
            $undoClaim = $this->claimToday($user, ActionType::Vote, $game->id, $game->name);

            // A row with this ID but another name is left over from before a flush: replace it.
            $existing?->delete();

            try {
                $vote = $user->votes()->create(['game_id' => $game->id, 'game_name' => $game->name]);
            } catch (UniqueConstraintViolationException) {
                throw new AlreadyVoted($game->id); // A concurrent request voted first.
            }

            return function () use ($vote, $undoClaim): void {
                $vote->delete();
                $undoClaim();
            };
        });

        $this->callApiOrUndo(fn () => $this->api->vote($game->id), $undo);

        return $game;
    }

    /**
     * @return Game The game the vote was removed from.
     *
     * @throws GameNotFound
     * @throws NotVoted
     */
    public function removeVote(User $user, int $gameId): Game
    {
        $game = $this->api->findGame($gameId) ?? throw new GameNotFound($gameId);

        $vote = $user->votes()->where('game_id', $game->id)->where('game_name', $game->name)->first()
            ?? throw new NotVoted($game->id);

        // Deleting the row is the lock: of two concurrent removals only one deletes it, so the API
        // count can't be decremented twice. (The API itself would happily remove *anyone's* vote.)
        if (Vote::whereKey($vote->id)->delete() !== 1) {
            throw new NotVoted($game->id);
        }

        $this->callApiOrUndo(
            fn () => $this->api->removeVote($game->id),
            fn () => $this->restoreVote($vote),
        );

        // Same-day undo: if today's action was this vote, give it back.
        $this->todaysAction($user)
            ->whereNull('refunded_at')
            ->where('type', ActionType::Vote->value)
            ->where('game_id', $game->id)
            ->where('game_name', $game->name)
            ->update(['refunded_at' => now(), 'refund_reason' => RefundReason::Withdrawn->value]);

        return $game;
    }

    /**
     * Anyone can remove a game. Everyone's votes for it are deleted, and anyone who voted for it or
     * added it *today* gets today's action back. Removing doesn't use the remover's action.
     *
     * @throws GameNotFound When it's already gone.
     */
    public function removeGame(User $remover, int $gameId): Game
    {
        $game = $this->api->findGame($gameId) ?? throw new GameNotFound($gameId);

        // API first, unlike the other changes: removing is safe to repeat (a second remover gets
        // GameNotFound), and if the API fails, nothing local has changed yet.
        $this->api->removeGame($game->id);

        DB::transaction(function () use ($remover, $game): void {
            Vote::where('game_id', $game->id)->where('game_name', $game->name)->delete();

            DailyAction::with('user')
                ->where('game_id', $game->id)
                ->where('game_name', $game->name)
                ->whereNull('refunded_at')
                // Every user's "today" is within a day of the UTC date (offsets are at most ±14h).
                ->where('local_date', '>=', now()->subDay()->toDateString())
                ->get()
                // "Today" is each user's own local date.
                ->filter(fn (DailyAction $action) => $action->local_date === $this->localDate($action->user))
                ->each(fn (DailyAction $action) => $action->update([
                    'refunded_at' => now(),
                    'refund_reason' => $action->user_id === $remover->id ? RefundReason::Withdrawn : RefundReason::GameRemoved,
                ]));
        });

        return $game;
    }

    // --- Daily action ---------------------------------------------------------------------

    /**
     * Take today's action. Must run inside a transaction. Returns a closure that undoes the claim.
     *
     * @throws DailyLimitReached
     */
    private function claimToday(User $user, ActionType $type, ?int $gameId, string $gameName): Closure
    {
        $values = [
            'type' => $type->value,
            'game_id' => $gameId,
            'game_name' => $gameName,
            'refunded_at' => null,
            'refund_reason' => null,
        ];

        $existing = $this->todaysAction($user)->first();

        if ($existing === null) {
            try {
                $row = DailyAction::create(['user_id' => $user->id, 'local_date' => $this->localDate($user)] + $values);
            } catch (UniqueConstraintViolationException) {
                throw new DailyLimitReached; // A concurrent request claimed today first.
            }

            return fn () => $row->delete();
        }

        // Reuse a refunded row (decision 6). "WHERE refunded_at IS NOT NULL" makes this a
        // compare-and-set: of two concurrent claims, only one can update the row.
        $previous = Arr::only($existing->getAttributes(), array_keys($values));
        $claimed = DailyAction::whereKey($existing->id)->whereNotNull('refunded_at')->update($values);

        if ($claimed !== 1) {
            throw new DailyLimitReached;
        }

        return fn () => DailyAction::whereKey($existing->id)->update($previous);
    }

    /**
     * @throws DailyLimitReached
     */
    private function ensureCanActToday(User $user): void
    {
        $action = $this->todaysAction($user)->first();

        if ($action !== null && $action->refunded_at === null) {
            throw new DailyLimitReached;
        }
    }

    /**
     * @return Builder<DailyAction>
     */
    private function todaysAction(User $user): Builder
    {
        return DailyAction::where('user_id', $user->id)->where('local_date', $this->localDate($user));
    }

    private function now(User $user): CarbonImmutable
    {
        return now($user->timezone)->toImmutable();
    }

    /** Today's date in the user's timezone, as stored in daily_actions.local_date. */
    private function localDate(User $user): string
    {
        return $this->now($user)->toDateString();
    }

    // --- Helpers --------------------------------------------------------------------------

    /**
     * @throws DuplicateGame
     */
    private function ensureNotDuplicate(string $name): void
    {
        $key = $this->comparable($name);

        foreach ($this->api->listGames() as $game) {
            if ($this->comparable($game->name) === $key) {
                throw new DuplicateGame(Str::squish($game->name));
            }
        }
    }

    /** "  The  Legend of ZELDA " and "the legend of zelda" compare equal. */
    private function comparable(string $name): string
    {
        return mb_strtolower(Str::squish($name));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @param  Closure(): void  $undo
     * @return T
     */
    private function callApiOrUndo(Closure $call, Closure $undo): mixed
    {
        try {
            return $call();
        } catch (Throwable $e) {
            DB::transaction($undo);

            throw $e;
        }
    }

    private function restoreVote(Vote $vote): void
    {
        $restored = $vote->replicate();
        $restored->created_at = $vote->created_at;
        $restored->save();
    }
}
