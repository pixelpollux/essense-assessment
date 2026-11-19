<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameRejected;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeEssenseApi;
use Tests\TestCase;

/**
 * The HTTP layer: routes, validation, and how each outcome reaches the user (a flash message or
 * an error on the form field). The rules themselves are covered in GameLibraryTest.
 */
class GameRoutesTest extends TestCase
{
    use RefreshDatabase;

    private FakeEssenseApi $api;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeEssenseApi;
        $this->app->instance(EssenseApi::class, $this->api);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
        $this->user = User::factory()->create();
    }

    // =====================================================================================
    // Access
    // =====================================================================================

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/'],
            'add' => ['post', '/games'],
            'remove game' => ['delete', '/games/1'],
            'vote' => ['post', '/games/1/vote'],
            'remove vote' => ['delete', '/games/1/vote'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_are_sent_to_login(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertRedirect('/login');
        $this->assertSame([], $this->api->mutations);
    }

    public function test_a_non_numeric_game_id_is_not_found(): void
    {
        $this->actingAs($this->user)->post('/games/abc/vote')->assertNotFound();
    }

    // =====================================================================================
    // The page
    // =====================================================================================

    public function test_the_page_lists_games_sorted_with_the_users_daily_status(): void
    {
        $this->api->withGame('Zelda', votes: 1);
        $this->api->withGame('Mario', votes: 3);

        $this->actingAs($this->user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('games/index')
            ->has('games', 2)
            ->where('games.0', ['id' => 2, 'name' => 'Mario', 'votes' => 3, 'votedByMe' => false])
            ->where('games.1.name', 'Zelda')
            ->where('status.canAct', true)
            ->where('status.usedFor', null)
            ->where('status.resetsAt', fn (string $iso) => CarbonImmutable::parse($iso)->equalTo(CarbonImmutable::parse('2026-11-21 00:00', 'UTC')))
            ->where('loadError', null));
    }

    public function test_the_page_still_renders_with_a_retry_message_when_the_api_is_down(): void
    {
        $this->api->failNext('listGames', EssenseUnavailable::serverError('/games/list', 503));

        $this->actingAs($this->user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('games/index')
            ->where('games', null)
            ->where('loadError', "We couldn't load the game library. Please try again in a moment.")
            ->has('status'));
    }

    // =====================================================================================
    // Adding a game: validation
    // =====================================================================================

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidNames(): array
    {
        return [
            'missing' => [null, 'Enter a game title.'],
            'blank' => ['   ', 'Enter a game title.'],
            'not a string' => [['Zelda'], 'Enter a game title.'],
            '101 characters' => [str_repeat('a', 101), 'Keep the title to 100 characters or fewer.'],
            'line break' => ["Line one\nLine two", "Titles can't contain line breaks or special control characters."],
            'tab' => ["Tab\there", "Titles can't contain line breaks or special control characters."],
            'invalid UTF-8' => ["Bad \xC3\x28 bytes", "Titles can't contain line breaks or special control characters."],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_titles_are_rejected_with_a_friendly_message(mixed $name, string $message): void
    {
        $this->actingAs($this->user)->from('/')->post('/games', ['name' => $name])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['name' => $message]);

        $this->assertSame(0, $this->api->gameCount());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validNames(): array
    {
        return [
            'exactly 100 characters' => [str_repeat('a', 100), str_repeat('a', 100)],
            'extra spaces collapsed before the length check' => [str_repeat('a', 50).'     '.str_repeat('b', 49), str_repeat('a', 50).' '.str_repeat('b', 49)],
            'emoji with zero-width joiners' => ['Family 👨‍👩‍👧 Game', 'Family 👨‍👩‍👧 Game'],
            'accents and apostrophes' => ["Pokémon: Let's Go", "Pokémon: Let's Go"],
        ];
    }

    #[DataProvider('validNames')]
    public function test_valid_titles_are_added_as_cleaned_up(string $name, string $saved): void
    {
        $this->actingAs($this->user)->from('/')->post('/games', ['name' => $name])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', "Added “{$saved}”.");

        $this->assertSame($saved, $this->api->nameOf(1));
    }

    // =====================================================================================
    // How each outcome reaches the user
    // =====================================================================================

    public function test_a_duplicate_title_is_an_error_on_the_name_field(): void
    {
        $this->api->withGame('Zelda');

        $this->actingAs($this->user)->from('/')->post('/games', ['name' => 'ZELDA'])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['name' => '“Zelda” is already in the library.']);
    }

    public function test_a_title_the_api_rejects_is_an_error_on_the_name_field(): void
    {
        $this->api->failNext('addGame', new GameRejected);

        $this->actingAs($this->user)->from('/')->post('/games', ['name' => 'Zelda'])
            ->assertSessionHasErrors(['name' => "That title couldn't be saved. Try a different one."]);
    }

    public function test_a_second_action_today_shows_the_daily_limit_message(): void
    {
        $this->api->withGame('Zelda');
        $this->api->withGame('Mario');
        $this->actingAs($this->user)->post('/games/1/vote');

        $this->from('/')->post('/games/2/vote')
            ->assertRedirect('/')
            ->assertSessionHas('error', "You've already used today's action. You can vote or add again after midnight.");
    }

    public function test_voting_twice_for_the_same_game_shows_already_voted(): void
    {
        $this->api->withGame('Zelda');
        $this->travelTo(now()->subDay());
        $this->actingAs($this->user)->post('/games/1/vote');
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));

        $this->from('/')->post('/games/1/vote')
            ->assertSessionHas('error', "You've already voted for this game.");
    }

    public function test_removing_a_vote_you_have_not_cast_shows_not_voted(): void
    {
        $this->api->withGame('Zelda', votes: 1);

        $this->actingAs($this->user)->from('/')->delete('/games/1/vote')
            ->assertSessionHas('error', "You haven't voted for this game, so there's no vote to remove.");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function goneGameRequests(): array
    {
        return [
            'vote' => ['post', '/games/1/vote'],
            'remove vote' => ['delete', '/games/1/vote'],
            'remove game' => ['delete', '/games/1'],
        ];
    }

    #[DataProvider('goneGameRequests')]
    public function test_acting_on_a_game_that_was_removed_says_so_and_refreshes(string $method, string $uri): void
    {
        $this->actingAs($this->user)->from('/')->{$method}($uri)
            ->assertRedirect('/')
            ->assertSessionHas('error', "That game was already removed, so we've refreshed the list.");
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function unavailableRequests(): array
    {
        return [
            'vote (ambiguous)' => ['vote', 'post', '/games/1/vote', "We couldn't confirm your vote. Check the vote count before trying again."],
            'add (ambiguous)' => ['addGame', 'post', '/games', "We couldn't confirm the game was added. Check the list before trying again."],
            'remove vote' => ['removeVote', 'delete', '/games/1/vote', "Couldn't reach the game library. Please try again in a moment."],
            'remove game' => ['removeGame', 'delete', '/games/1', "Couldn't reach the game library. Please try again in a moment."],
        ];
    }

    #[DataProvider('unavailableRequests')]
    public function test_when_the_api_is_unavailable_the_message_fits_the_action(string $apiMethod, string $method, string $uri, string $message): void
    {
        $this->api->withGame('Zelda');
        if ($apiMethod === 'removeVote') {
            $this->actingAs($this->user)->post('/games/1/vote');
        }
        $this->api->failNext($apiMethod, EssenseUnavailable::serverError('/games/x', 504));

        $this->actingAs($this->user)->from('/')->{$method}($uri, ['name' => 'Mario'])
            ->assertRedirect('/')
            ->assertSessionHas('error', $message);
    }

    public function test_a_misconfigured_api_shows_a_generic_message_without_details(): void
    {
        $this->api->withGame('Zelda');
        $this->api->failNext('vote', EssenseMisconfigured::rejected('/games/vote', 403, 'INVALID_KEY'));

        $response = $this->actingAs($this->user)->from('/')->post('/games/1/vote')
            ->assertSessionHas('error', "The game library isn't available right now.");

        $this->assertStringNotContainsString('INVALID_KEY', (string) session('error'));
        $this->assertStringNotContainsString('INVALID_KEY', $response->getContent());
    }

    public function test_an_add_blocked_by_another_add_in_progress_asks_to_try_again(): void
    {
        $this->app->make('config')->set('cache.default', 'array');
        $lock = Cache::lock('games:add', 10);
        $lock->get();
        $this->travelBack(); // Lock waits check the real clock; with time frozen the wait would never end.

        try {
            $this->actingAs($this->user)->from('/')->post('/games', ['name' => 'Zelda'])
                ->assertSessionHas('error', 'Someone else is adding a game right now. Try again in a moment.');
        } finally {
            $lock->release();
        }
    }

    // =====================================================================================
    // Success messages name the game
    // =====================================================================================

    public function test_success_messages_name_the_game(): void
    {
        $this->api->withGame('Zelda');
        $this->actingAs($this->user)->from('/');

        $this->post('/games/1/vote')->assertSessionHas('success', 'Voted for “Zelda”.');
        $this->delete('/games/1/vote')->assertSessionHas('success', 'Removed your vote for “Zelda”.');
        $this->delete('/games/1')->assertSessionHas('success', 'Removed “Zelda”.');
    }
}
