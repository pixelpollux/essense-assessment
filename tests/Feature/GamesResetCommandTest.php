<?php

namespace Tests\Feature;

use App\Models\DailyAction;
use App\Models\User;
use App\Models\Vote;
use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Library\GameLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeEssenseApi;
use Tests\TestCase;

class GamesResetCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRM = 'Are you sure you want to run this command?';

    private FakeEssenseApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new FakeEssenseApi;
        $this->app->instance(EssenseApi::class, $this->api);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));
    }

    /** Two users with a vote each, one of them also with yesterday's add. */
    private function seedLibrary(): void
    {
        $library = $this->app->make(GameLibrary::class);
        [$ada, $grace] = [User::factory()->create(), User::factory()->create()];
        $zelda = $this->api->withGame('Zelda');

        $this->travelTo(now()->subDay());
        $library->addGame($ada, 'Mario');
        $this->travelTo(CarbonImmutable::parse('2026-11-20 03:00:00', 'UTC'));

        $library->vote($ada, $zelda);
        $library->vote($grace, $zelda);
    }

    public function test_it_clears_the_api_and_both_tables_and_keeps_accounts(): void
    {
        $this->seedLibrary();

        $this->artisan('games:reset')
            ->expectsOutputToContain('2 votes and 3 daily actions')
            ->expectsOutputToContain('User accounts were kept')
            ->assertSuccessful();

        $this->assertSame(0, $this->api->gameCount());
        $this->assertSame(0, Vote::count());
        $this->assertSame(0, DailyAction::count());
        $this->assertSame(2, User::count());
    }

    public function test_after_a_reset_users_can_act_again_and_game_ids_restart_at_one(): void
    {
        $this->seedLibrary();
        $ada = User::first();
        $library = $this->app->make(GameLibrary::class);
        $this->assertFalse($library->todayStatus($ada)->canAct);

        $this->artisan('games:reset')->assertSuccessful();

        $this->assertTrue($library->todayStatus($ada)->canAct);
        $this->assertSame(1, $library->addGame($ada, 'Wingspan')->id);
    }

    public function test_in_production_it_asks_first_and_answering_no_changes_nothing(): void
    {
        $this->seedLibrary();
        $this->app['env'] = 'production';

        $this->artisan('games:reset')
            ->expectsConfirmation(self::CONFIRM, 'no')
            ->assertFailed();

        $this->assertSame(2, $this->api->gameCount());
        $this->assertSame(2, Vote::count());
        $this->assertNotContains('flushGames', $this->api->mutations);
    }

    public function test_in_production_force_skips_the_question(): void
    {
        $this->seedLibrary();
        $this->app['env'] = 'production';

        $this->artisan('games:reset', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, $this->api->gameCount());
        $this->assertSame(0, Vote::count());
    }

    public function test_if_the_api_is_unreachable_nothing_local_changes(): void
    {
        $this->seedLibrary();
        $this->api->failNext('flushGames', EssenseUnavailable::serverError('/cache/flush', 503));

        $this->artisan('games:reset')
            ->expectsOutputToContain('Could not reach the Essense API, so nothing was changed')
            ->assertFailed();

        $this->assertSame(2, Vote::count());
        $this->assertSame(3, DailyAction::count());
    }

    public function test_if_the_api_rejects_the_key_it_says_so_and_nothing_local_changes(): void
    {
        $this->seedLibrary();
        $this->api->failNext('flushGames', EssenseMisconfigured::rejected('/cache/flush', 403, 'INVALID_KEY'));

        $this->artisan('games:reset')
            ->expectsOutputToContain('rejected the request (INVALID_KEY)')
            ->assertFailed();

        $this->assertSame(2, Vote::count());
    }
}
