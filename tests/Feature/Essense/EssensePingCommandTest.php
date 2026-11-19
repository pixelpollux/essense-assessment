<?php

namespace Tests\Feature\Essense;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class EssensePingCommandTest extends TestCase
{
    public function test_it_reports_the_game_count_when_the_key_works(): void
    {
        Http::fake(['essense.test/games/list' => Http::response(['games' => [
            ['id' => 1, 'name' => 'Zelda', 'votes' => 0],
            ['id' => 2, 'name' => 'Mario', 'votes' => 1],
        ]])]);

        $this->artisan('essense:ping')
            ->expectsOutputToContain('the library has 2 games')
            ->assertSuccessful();
    }

    public function test_it_fails_with_a_clear_message_when_the_key_is_rejected(): void
    {
        Http::fake(['essense.test/*' => Http::response(['error' => 'INVALID_KEY'], 403)]);

        $this->artisan('essense:ping')
            ->expectsOutputToContain('rejected ESSENSE_API_KEY')
            ->assertFailed();
    }

    public function test_it_fails_when_the_api_is_unreachable(): void
    {
        Sleep::fake();
        Http::fake(['essense.test/*' => Http::failedConnection()]);

        $this->artisan('essense:ping')
            ->expectsOutputToContain('Could not reach the Essense API')
            ->assertFailed();
    }
}
