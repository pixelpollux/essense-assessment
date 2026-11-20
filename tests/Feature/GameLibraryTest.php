<?php

namespace Tests\Feature;

use App\Enums\ActionType;
use App\Enums\RefundReason;
use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;
use App\Services\Library\Exceptions\AlreadyVoted;
use App\Services\Library\Exceptions\DailyLimitReached;
use App\Services\Library\Exceptions\DuplicateGame;
use App\Services\Library\Exceptions\NotVoted;
use App\Services\Library\GameLibrary;
use App\Services\Library\LibraryGame;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeEssenseApi;
use Tests\TestCase;
use Throwable;

/**
 * The business rules from CLAUDE.md ("Decisions"), tested against real SQLite tables and an
 * in-memory fake of the Essense API.
 *
 * Unless a test travels elsewhere, "now" is Fri 2026-11-20 03:00 UTC.
 */
class GameLibraryTest extends TestCase
{
    use RefreshDatabase;

    private FakeEssenseApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeEssenseApi;
        $this->app->instance(EssenseApi::class, $this->api);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
    }

    // =====================================================================================
    // Listing
    // =====================================================================================

    public function test_games_are_listed_by_votes_highest_first_then_by_name_a_to_z_ignoring_case(): void
    {
        $this->api->withGame('Zelda', votes: 1);
        $this->api->withGame('mario kart', votes: 3);
        $this->api->withGame('Asteroids', votes: 1);
        $this->api->withGame('banjo', votes: 1);

        $this->assertSame(['mario kart', 'Asteroids', 'banjo', 'Zelda'], $this->names($this->library()->games($this->user())));
    }

    public function test_each_game_shows_whether_the_current_user_voted_for_it(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');
        [$me, $someoneElse] = [$this->user(), $this->user()];
        $this->library()->vote($me, $zelda);
        $this->library()->vote($someoneElse, $mario);

        $votedByMe = collect($this->library()->games($me))->mapWithKeys(fn (LibraryGame $g) => [$g->name => $g->votedByMe]);

        $this->assertSame(['Mario' => false, 'Zelda' => true], $votedByMe->all());
    }

    public function test_a_vote_from_before_a_flush_does_not_count_for_a_new_game_that_reused_the_id(): void
    {
        $me = $this->user();
        $this->library()->vote($me, $this->api->withGame('Zelda')); // ID 1
        $this->api->flush();
        $this->api->withGame('Mario');                               // ID 1 again

        $games = $this->library()->games($me);

        $this->assertSame([1, 'Mario', false], [$games[0]->id, $games[0]->name, $games[0]->votedByMe]);
    }

    // =====================================================================================
    // One action per day (decision 3)
    // =====================================================================================

    public function test_a_user_can_vote_only_once_per_day(): void
    {
        $user = $this->user();
        $this->library()->vote($user, $this->api->withGame('Zelda'));
        $mario = $this->api->withGame('Mario');

        $this->assertThrows(fn () => $this->library()->vote($user, $mario), DailyLimitReached::class);
        $this->assertSame(0, $this->api->votesFor($mario));
    }

    public function test_adding_a_game_and_voting_share_the_same_daily_action(): void
    {
        $zelda = $this->api->withGame('Zelda');

        $adder = $this->user();
        $this->library()->addGame($adder, 'Mario');
        $this->assertThrows(fn () => $this->library()->vote($adder, $zelda), DailyLimitReached::class);

        $voter = $this->user();
        $this->library()->vote($voter, $zelda);
        $this->assertThrows(fn () => $this->library()->addGame($voter, 'Sonic'), DailyLimitReached::class);

        $this->assertSame(['addGame', 'vote'], $this->api->mutations, 'Blocked actions must never reach the API.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function timezones(): array
    {
        return [
            'Sydney (UTC+11)' => ['Australia/Sydney'],
            'Los Angeles (UTC-8)' => ['America/Los_Angeles'],
            'UTC' => ['UTC'],
        ];
    }

    #[DataProvider('timezones')]
    public function test_the_day_resets_at_midnight_in_the_users_own_timezone(string $timezone): void
    {
        $user = $this->user($timezone);
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');

        $this->travelToLocal('2026-11-20 23:59:00', $timezone);
        $this->library()->vote($user, $zelda);

        $this->travelToLocal('2026-11-20 23:59:59', $timezone);
        $this->assertThrows(fn () => $this->library()->vote($user, $mario), DailyLimitReached::class);

        $this->travelToLocal('2026-11-21 00:00:00', $timezone);
        $this->library()->vote($user, $mario);

        $this->assertSame(1, $this->api->votesFor($mario));
    }

    public function test_the_day_follows_the_users_date_not_the_servers_utc_date(): void
    {
        $user = $this->user('Australia/Sydney');
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');

        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:30', 'UTC')); // 23:30 on the 20th in Sydney
        $this->library()->vote($user, $zelda);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 13:30', 'UTC')); // still the 20th in UTC, but the 21st in Sydney
        $this->library()->vote($user, $mario);

        $this->assertSame(1, $this->api->votesFor($mario));
    }

    public function test_the_database_itself_rejects_a_second_action_for_the_same_user_and_day(): void
    {
        $user = $this->user();
        DailyAction::factory()->for($user)->create(['local_date' => '2026-11-20']);

        $this->expectException(UniqueConstraintViolationException::class);
        DailyAction::factory()->for($user)->create(['local_date' => '2026-11-20']);
    }

    // =====================================================================================
    // Voting
    // =====================================================================================

    public function test_voting_adds_a_vote_in_the_api_and_uses_todays_action(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');

        $this->library()->vote($user, $zelda);

        $this->assertSame(1, $this->api->votesFor($zelda));
        $this->assertDatabaseHas('votes', ['user_id' => $user->id, 'game_id' => $zelda, 'game_name' => 'Zelda']);
        $this->assertStatus($user, canAct: false, usedFor: ActionType::Vote, gameName: 'Zelda');
    }

    public function test_voting_again_for_a_game_you_already_voted_for_is_rejected_and_keeps_todays_action(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->travelTo(now()->subDay());
        $this->library()->vote($user, $zelda);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));

        $this->assertThrows(fn () => $this->library()->vote($user, $zelda), AlreadyVoted::class);

        $this->assertSame(1, $this->api->votesFor($zelda));
        $this->assertStatus($user, canAct: true);
    }

    public function test_voting_for_a_game_that_does_not_exist_keeps_todays_action(): void
    {
        $user = $this->user();

        $this->assertThrows(fn () => $this->library()->vote($user, 999), GameNotFound::class);

        $this->assertSame(0, Vote::count());
        $this->assertStatus($user, canAct: true);
    }

    public function test_voting_for_a_new_game_that_reused_a_flushed_id_replaces_the_leftover_vote(): void
    {
        $user = $this->user();
        $this->travelTo(now()->subDay());
        $this->library()->vote($user, $this->api->withGame('Zelda')); // ID 1
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->api->flush();
        $mario = $this->api->withGame('Mario');                         // ID 1 again

        $this->library()->vote($user, $mario);

        $this->assertSame(1, $this->api->votesFor($mario));
        $this->assertSame(['Mario'], Vote::where('user_id', $user->id)->pluck('game_name')->all());
    }

    /**
     * @return array<string, array{Throwable}>
     */
    public static function voteFailures(): array
    {
        return [
            'API unreachable' => [EssenseUnavailable::serverError('/games/vote', 503)],
            'game removed between lookup and vote' => [new GameNotFound(1)],
        ];
    }

    #[DataProvider('voteFailures')]
    public function test_if_the_api_vote_fails_nothing_is_kept_locally_and_todays_action_is_kept(Throwable $failure): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->api->failNext('vote', $failure);

        $this->assertThrows(fn () => $this->library()->vote($user, $zelda), $failure::class);

        $this->assertSame(0, Vote::count());
        $this->assertSame(0, DailyAction::count());
        $this->assertStatus($user, canAct: true);
    }

    /**
     * Accepted trade-off (decision): a timeout is ambiguous, so we undo locally and warn the user.
     * If the API had already counted the vote, its total stays one too high.
     */
    public function test_a_vote_that_times_out_after_the_api_counted_it_is_still_undone_locally(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->api->failNext('vote', EssenseUnavailable::serverError('/games/vote', 504), afterApplying: true);

        $this->assertThrows(fn () => $this->library()->vote($user, $zelda), EssenseUnavailable::class);

        $this->assertSame(0, Vote::count());
        $this->assertStatus($user, canAct: true);
        $this->assertSame(1, $this->api->votesFor($zelda), 'The API total stays inflated by one: the accepted trade-off.');
    }

    // =====================================================================================
    // Removing a vote
    // =====================================================================================

    public function test_removing_todays_vote_gives_todays_action_back_as_withdrawn(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->library()->vote($user, $zelda);

        $this->library()->removeVote($user, $zelda);

        $this->assertSame(0, $this->api->votesFor($zelda));
        $this->assertSame(0, Vote::count());
        $this->assertStatus($user, canAct: true, gameName: 'Zelda', refundReason: RefundReason::Withdrawn);
    }

    public function test_after_withdrawing_a_vote_the_user_can_act_again_the_same_day(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');
        $this->library()->vote($user, $zelda);
        $this->library()->removeVote($user, $zelda);

        $this->library()->vote($user, $mario);

        $this->assertStatus($user, canAct: false, usedFor: ActionType::Vote, gameName: 'Mario');
        $this->assertSame(1, DailyAction::count(), 'The refunded row is reused, keeping one row per user per day.');
    }

    public function test_removing_a_vote_from_an_earlier_day_is_free_and_does_not_touch_todays_action(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');
        [$actedToday, $idleToday] = [$this->user(), $this->user()];

        $this->travelTo(now()->subDay());
        $this->library()->vote($actedToday, $zelda);
        $this->library()->vote($idleToday, $zelda);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->library()->vote($actedToday, $mario);

        $this->library()->removeVote($actedToday, $zelda);
        $this->library()->removeVote($idleToday, $zelda);

        $this->assertSame(0, $this->api->votesFor($zelda));
        $this->assertStatus($actedToday, canAct: false, usedFor: ActionType::Vote, gameName: 'Mario');
        $this->assertStatus($idleToday, canAct: true);
    }

    public function test_removing_a_vote_you_have_not_cast_is_rejected_and_never_reaches_the_api(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $this->library()->vote($this->user(), $zelda);

        $this->assertThrows(fn () => $this->library()->removeVote($this->user(), $zelda), NotVoted::class);

        $this->assertSame(1, $this->api->votesFor($zelda), "Someone else's vote must not be removed.");
        $this->assertSame(['vote'], $this->api->mutations);
    }

    public function test_a_vote_from_before_a_flush_cannot_remove_a_vote_from_the_new_game_with_the_same_id(): void
    {
        $me = $this->user();
        $this->travelTo(now()->subDay());
        $this->library()->vote($me, $this->api->withGame('Zelda')); // ID 1
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->api->flush();
        $mario = $this->api->withGame('Mario', votes: 1);            // ID 1 again, voted by someone else

        $this->assertThrows(fn () => $this->library()->removeVote($me, $mario), NotVoted::class);

        $this->assertSame(1, $this->api->votesFor($mario));
    }

    public function test_if_the_api_fails_to_remove_a_vote_the_vote_and_todays_action_are_kept(): void
    {
        $user = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->library()->vote($user, $zelda);
        $this->api->failNext('removeVote', EssenseUnavailable::serverError('/games/removeVote', 503));

        $this->assertThrows(fn () => $this->library()->removeVote($user, $zelda), EssenseUnavailable::class);

        $this->assertSame(1, Vote::count());
        $this->assertSame(1, $this->api->votesFor($zelda));
        $this->assertStatus($user, canAct: false, usedFor: ActionType::Vote, gameName: 'Zelda');
    }

    // =====================================================================================
    // Adding a game
    // =====================================================================================

    public function test_adding_a_game_creates_it_in_the_api_and_uses_todays_action(): void
    {
        $user = $this->user();

        $game = $this->library()->addGame($user, 'Zelda');

        $this->assertSame('Zelda', $this->api->nameOf($game->id));
        $this->assertDatabaseHas('daily_actions', ['user_id' => $user->id, 'type' => 'add', 'game_id' => $game->id]);
        $this->assertStatus($user, canAct: false, usedFor: ActionType::Add, gameName: 'Zelda');
    }

    public function test_extra_spaces_inside_a_name_are_collapsed_before_saving(): void
    {
        $game = $this->library()->addGame($this->user(), 'Super   Mario  Bros');

        $this->assertSame('Super Mario Bros', $this->api->nameOf($game->id));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function duplicateNames(): array
    {
        return [
            'different case' => ['Zelda', 'zelda'],
            'all caps' => ['Zelda', 'ZELDA'],
            'extra spaces typed' => ['The Legend of Zelda', 'The  legend of   ZELDA'],
            'stored with spaces (added outside the app)' => ['  Zelda  ', 'zelda'],
            'apostrophe' => ["Baldur's Gate 3", "baldur's gate 3"],
            'accented capitals' => ['Pokémon', 'POKÉMON'],
        ];
    }

    #[DataProvider('duplicateNames')]
    public function test_duplicate_titles_are_rejected_ignoring_case_and_extra_spaces(string $existing, string $attempt): void
    {
        $user = $this->user();
        $this->api->withGame($existing);

        try {
            $this->library()->addGame($user, $attempt);
            $this->fail('Expected DuplicateGame');
        } catch (DuplicateGame $e) {
            $this->assertSame(Str::squish($existing), $e->existingName);
        }

        $this->assertSame(1, $this->api->gameCount());
        $this->assertStatus($user, canAct: true);
    }

    public function test_similar_but_different_titles_are_allowed(): void
    {
        $this->api->withGame('Zelda');

        $this->library()->addGame($this->user(), 'Zelda II');

        $this->assertSame(2, $this->api->gameCount());
    }

    /**
     * @return array<string, array{string, Throwable}>
     */
    public static function addFailures(): array
    {
        return [
            'API unreachable' => ['Zelda', EssenseUnavailable::serverError('/games/add', 503)],
            'API rejects the name' => ['Zelda', new GameRejected],
        ];
    }

    #[DataProvider('addFailures')]
    public function test_if_the_api_fails_to_add_the_game_todays_action_is_released(string $name, Throwable $failure): void
    {
        $user = $this->user();
        $this->api->failNext('addGame', $failure);

        $this->assertThrows(fn () => $this->library()->addGame($user, $name), $failure::class);

        $this->assertSame(0, DailyAction::count());
        $this->assertStatus($user, canAct: true);
    }

    // =====================================================================================
    // Removing a game (decision 6)
    // =====================================================================================

    public function test_removing_a_game_removes_it_from_the_api_and_deletes_everyones_votes_for_it(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $mario = $this->api->withGame('Mario');
        $this->travelTo(now()->subDay());
        $this->library()->vote($this->user(), $zelda);
        $this->library()->vote($marioFan = $this->user(), $mario);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->library()->vote($this->user(), $zelda);

        $this->library()->removeGame($this->user(), $zelda);

        $this->assertFalse($this->api->has($zelda));
        $this->assertSame(0, Vote::where('game_id', $zelda)->count());
        $this->assertSame(1, Vote::where('user_id', $marioFan->id)->count(), 'Votes for other games are untouched.');
    }

    public function test_removing_a_game_refunds_todays_voters_but_not_earlier_ones(): void
    {
        $zelda = $this->api->withGame('Zelda');
        [$votedYesterday, $votedToday] = [$this->user(), $this->user()];
        $this->travelTo(now()->subDay());
        $this->library()->vote($votedYesterday, $zelda);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->library()->vote($votedToday, $zelda);

        $this->library()->removeGame($this->user(), $zelda);

        $this->assertStatus($votedToday, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
        $this->assertNull(DailyAction::where('user_id', $votedYesterday->id)->sole()->refunded_at);
    }

    public function test_removing_a_game_you_added_today_gives_your_action_back_as_withdrawn(): void
    {
        $adder = $this->user();
        $game = $this->library()->addGame($adder, 'Zelda');

        $this->library()->removeGame($adder, $game->id);

        $this->assertStatus($adder, canAct: true, gameName: 'Zelda', refundReason: RefundReason::Withdrawn);
    }

    public function test_when_someone_else_removes_a_game_you_added_today_you_get_your_action_back(): void
    {
        $adder = $this->user();
        $game = $this->library()->addGame($adder, 'Zelda');

        $this->library()->removeGame($this->user(), $game->id);

        $this->assertStatus($adder, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
    }

    public function test_removing_a_game_does_not_use_the_removers_daily_action(): void
    {
        $remover = $this->user();

        $this->library()->removeGame($remover, $this->api->withGame('Zelda'));

        $this->assertStatus($remover, canAct: true);
    }

    public function test_refunds_on_removal_use_each_voters_own_local_date(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $sydneyYesterday = $this->user('Australia/Sydney');
        $losAngelesToday = $this->user('America/Los_Angeles');
        $sydneyToday = $this->user('Australia/Sydney');

        $this->travelToLocal('2026-11-20 10:00', 'Australia/Sydney');     // 2026-11-19 23:00 UTC
        $this->library()->vote($sydneyYesterday, $zelda);
        $this->travelToLocal('2026-11-20 08:00', 'America/Los_Angeles');  // 2026-11-20 16:00 UTC
        $this->library()->vote($losAngelesToday, $zelda);
        $this->travelToLocal('2026-11-21 06:00', 'Australia/Sydney');     // 2026-11-20 19:00 UTC
        $this->library()->vote($sydneyToday, $zelda);

        // 2026-11-20 20:00 UTC: the 21st in Sydney, still the 20th in Los Angeles.
        $this->travelTo(CarbonImmutable::parse('2026-11-20 20:00', 'UTC'));
        $this->library()->removeGame($this->user(), $zelda);

        $this->assertStatus($sydneyToday, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
        $this->assertStatus($losAngelesToday, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
        $this->assertNull(DailyAction::where('user_id', $sydneyYesterday->id)->sole()->refunded_at);
    }

    public function test_removing_a_game_that_is_already_gone_reports_game_not_found(): void
    {
        $zelda = $this->api->withGame('Zelda');
        $this->api->removeDirectly($zelda);

        $this->assertThrows(fn () => $this->library()->removeGame($this->user(), $zelda), GameNotFound::class);
    }

    public function test_if_the_api_fails_to_remove_a_game_nothing_changes_locally(): void
    {
        $voter = $this->user();
        $zelda = $this->api->withGame('Zelda');
        $this->library()->vote($voter, $zelda);
        $this->api->failNext('removeGame', EssenseUnavailable::serverError('/games/remove', 503));

        $this->assertThrows(fn () => $this->library()->removeGame($this->user(), $zelda), EssenseUnavailable::class);

        $this->assertSame(1, Vote::count());
        $this->assertStatus($voter, canAct: false, usedFor: ActionType::Vote, gameName: 'Zelda');
    }

    // =====================================================================================
    // Games that disappear outside the app (a flush, a new API key, the API's docs page)
    // =====================================================================================

    public function test_when_games_vanish_outside_the_app_todays_actions_are_refunded_and_votes_dropped(): void
    {
        [$adder, $voter] = [$this->user(), $this->user()];
        $zelda = $this->library()->addGame($adder, 'Zelda');
        $this->library()->vote($voter, $zelda->id);
        $this->api->flush(); // e.g. /cache/flush from the docs page, or a different API key

        $this->assertSame([], $this->library()->games($this->user()));

        $this->assertSame(0, Vote::count());
        $this->assertStatus($adder, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
        $this->assertStatus($voter, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
    }

    public function test_after_that_refund_the_user_can_act_again_the_same_day(): void
    {
        $voter = $this->user();
        $this->library()->vote($voter, $this->api->withGame('Zelda'));
        $this->api->flush();
        $this->library()->games($voter);

        $this->library()->vote($voter, $this->api->withGame('Mario'));

        $this->assertStatus($voter, canAct: false, usedFor: ActionType::Vote, gameName: 'Mario');
    }

    public function test_actions_from_earlier_days_are_not_refunded_but_their_votes_are_dropped(): void
    {
        $user = $this->user();
        $this->travelTo(now()->subDay());
        $this->library()->vote($user, $this->api->withGame('Zelda'));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->api->flush();

        $this->library()->games($user);

        $this->assertNull(DailyAction::where('user_id', $user->id)->sole()->refunded_at);
        $this->assertSame(0, Vote::count());
    }

    public function test_a_different_game_that_reused_the_id_still_counts_as_the_old_one_being_gone(): void
    {
        $voter = $this->user();
        $this->library()->vote($voter, $this->api->withGame('Zelda')); // ID 1
        $this->api->flush();
        $this->api->withGame('Mario');                                // ID 1 again

        $games = $this->library()->games($voter);

        $this->assertFalse($games[0]->votedByMe);
        $this->assertSame(0, Vote::count());
        $this->assertStatus($voter, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);
    }

    public function test_an_add_still_in_progress_is_not_refunded(): void
    {
        $adder = $this->user();
        // An add claims today's action before the API returns the new ID, so game_id is still null.
        DailyAction::factory()->for($adder)->create(['local_date' => '2026-11-20', 'type' => ActionType::Add, 'game_id' => null, 'game_name' => 'Zelda']);

        $this->library()->games($this->user());

        $this->assertStatus($adder, canAct: false, usedFor: ActionType::Add, gameName: 'Zelda');
    }

    public function test_nothing_is_changed_when_the_game_list_cannot_be_loaded(): void
    {
        $voter = $this->user();
        $this->library()->vote($voter, $this->api->withGame('Zelda'));
        $this->api->flush();
        $this->api->failNext('listGames', EssenseUnavailable::serverError('/games/list', 503));

        $this->assertThrows(fn () => $this->library()->games($voter), EssenseUnavailable::class);

        $this->assertSame(1, Vote::count());
        $this->assertStatus($voter, canAct: false, usedFor: ActionType::Vote, gameName: 'Zelda');
    }
    // =====================================================================================
    // Daily status
    // =====================================================================================

    public function test_with_no_action_today_the_status_offers_one_action_until_the_next_local_midnight(): void
    {
        $user = $this->user('Australia/Sydney'); // now = 2026-11-20 14:00 in Sydney

        $status = $this->library()->todayStatus($user);

        $this->assertStatus($user, canAct: true);
        $this->assertTrue(
            $status->resetsAt->equalTo(CarbonImmutable::parse('2026-11-21 00:00', 'Australia/Sydney')),
            "resetsAt was {$status->resetsAt}",
        );
    }

    public function test_after_voting_the_status_shows_the_action_was_used_on_that_game_until_the_next_local_midnight(): void
    {
        $user = $this->user('America/Los_Angeles'); // now = 2026-11-19 19:00 in Los Angeles
        $this->library()->vote($user, $this->api->withGame('Zelda'));

        $status = $this->library()->todayStatus($user);

        $this->assertStatus($user, canAct: false, usedFor: ActionType::Vote, gameName: 'Zelda');
        $this->assertTrue(
            $status->resetsAt->equalTo(CarbonImmutable::parse('2026-11-20 00:00', 'America/Los_Angeles')),
            "resetsAt was {$status->resetsAt}",
        );
    }

    public function test_after_adding_a_game_the_status_shows_the_action_was_used_to_add_it(): void
    {
        $user = $this->user();

        $this->library()->addGame($user, 'Zelda');

        $this->assertStatus($user, canAct: false, usedFor: ActionType::Add, gameName: 'Zelda');
    }

    public function test_when_a_game_you_voted_for_today_is_removed_the_status_gives_the_action_back_and_names_the_game(): void
    {
        $voter = $this->user();
        $this->library()->vote($voter, $this->api->withGame('Zelda'));

        $this->library()->removeGame($this->user(), 1);

        $this->assertStatus($voter, canAct: true, gameName: 'Zelda', refundReason: RefundReason::GameRemoved);

        $this->library()->vote($voter, $this->api->withGame('Mario'));
        $this->assertStatus($voter, canAct: false, usedFor: ActionType::Vote, gameName: 'Mario');
    }

    public function test_yesterdays_action_does_not_appear_in_todays_status(): void
    {
        $user = $this->user();
        $this->travelTo(now()->subDay());
        $this->library()->vote($user, $this->api->withGame('Zelda'));
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));

        $this->assertStatus($user, canAct: true);
    }

    // =====================================================================================
    // Helpers
    // =====================================================================================

    private function library(): GameLibrary
    {
        return $this->app->make(GameLibrary::class);
    }

    private function user(string $timezone = 'UTC'): User
    {
        return User::factory()->create(['timezone' => $timezone]);
    }

    /** Travel to a wall-clock time as read in $timezone. */
    private function travelToLocal(string $localTime, string $timezone): void
    {
        $this->travelTo(CarbonImmutable::parse($localTime, $timezone));
    }

    /**
     * @param  list<LibraryGame>  $games
     * @return list<string>
     */
    private function names(array $games): array
    {
        return array_map(fn (LibraryGame $g) => $g->name, $games);
    }

    private function assertStatus(
        User $user,
        bool $canAct,
        ?ActionType $usedFor = null,
        ?string $gameName = null,
        ?RefundReason $refundReason = null,
    ): void {
        $status = $this->library()->todayStatus($user);

        $this->assertSame(
            ['canAct' => $canAct, 'usedFor' => $usedFor, 'gameName' => $gameName, 'refundReason' => $refundReason],
            ['canAct' => $status->canAct, 'usedFor' => $status->usedFor, 'gameName' => $status->gameName, 'refundReason' => $status->refundReason],
        );
    }
}
