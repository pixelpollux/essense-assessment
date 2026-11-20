<?php

namespace Tests\Feature\Essense;

use App\Services\Essense\EssenseClient;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use App\Services\Essense\Exceptions\GameNotFound;
use App\Services\Essense\Exceptions\GameRejected;
use App\Services\Essense\Game;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Each test pins down an API quirk recorded in docs/api-notes.md.
 * The URL and key come from phpunit.xml (https://essense.test, "test-key").
 */
class EssenseClientTest extends TestCase
{
    private function client(): EssenseClient
    {
        return $this->app->make(EssenseClient::class);
    }

    public function test_requests_are_form_encoded_accept_json_and_carry_the_key_in_the_body(): void
    {
        Http::fake(['essense.test/games/list' => Http::response(['games' => []])]);

        $this->client()->listGames();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://essense.test/games/list'
            && $request->isForm()
            && $request->hasHeader('Accept', 'application/json')
            && $request['api_key'] === 'test-key'
            && ! str_contains($request->url(), 'test-key'));
    }

    public function test_list_games_maps_games_and_decodes_html_escaped_names_exactly_once(): void
    {
        Http::fake(['essense.test/games/list' => Http::response(['games' => [
            ['id' => 1, 'name' => 'Baldur&#039;s Gate 3', 'votes' => 2],
            ['id' => 2, 'name' => 'Tom &amp;amp; Jerry', 'votes' => 0], // user literally typed "Tom &amp; Jerry"
            ['id' => 3, 'name' => '&lt;script&gt;', 'votes' => 1],
        ]])]);

        $games = $this->client()->listGames();

        $this->assertEquals([
            new Game(1, "Baldur's Gate 3", 2),
            new Game(2, 'Tom &amp; Jerry', 0),
            new Game(3, '<script>', 1),
        ], $games);
    }

    public function test_find_game_returns_the_game_or_null_when_the_api_answers_an_empty_object(): void
    {
        Http::fake(['essense.test/games/search' => Http::sequence()
            ->push(['id' => 7, 'name' => 'Zelda', 'votes' => 3])
            ->push([])]);

        $this->assertEquals(new Game(7, 'Zelda', 3), $this->client()->findGame(7));
        $this->assertNull($this->client()->findGame(999));
    }

    public function test_add_game_returns_the_new_id(): void
    {
        Http::fake(['essense.test/games/add' => Http::response(['id' => 42])]);

        $this->assertSame(42, $this->client()->addGame("Baldur's Gate 3"));

        // Names are sent raw. The API escapes them itself, so pre-escaping would double-escape.
        Http::assertSent(fn (Request $request) => $request['name'] === "Baldur's Gate 3");
    }

    public function test_add_game_throws_game_rejected_when_the_api_answers_minus_one(): void
    {
        Http::fake(['essense.test/games/add' => Http::response(['id' => -1])]);

        $this->expectException(GameRejected::class);
        $this->client()->addGame('   ');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mutations(): array
    {
        return [
            'vote' => ['vote', '/games/vote'],
            'removeVote (case-sensitive path)' => ['removeVote', '/games/removeVote'],
            'removeGame' => ['removeGame', '/games/remove'],
        ];
    }

    #[DataProvider('mutations')]
    public function test_mutations_hit_the_exact_path_and_succeed_on_success_true(string $method, string $path): void
    {
        Http::fake(["essense.test{$path}" => Http::response(['success' => true])]);

        $this->client()->{$method}(5);

        Http::assertSent(fn (Request $request) => $request->url() === "https://essense.test{$path}" && $request['id'] == 5);
    }

    #[DataProvider('mutations')]
    public function test_mutations_throw_game_not_found_when_the_api_answers_success_false_with_200(string $method, string $path): void
    {
        Http::fake(["essense.test{$path}" => Http::response(['success' => false])]);

        try {
            $this->client()->{$method}(999);
            $this->fail('Expected GameNotFound');
        } catch (GameNotFound $e) {
            $this->assertSame(999, $e->gameId);
        }
    }

    /**
     * @return array<string, array{int, array<string, mixed>, string}>
     */
    public static function rejectedRequests(): array
    {
        return [
            'invalid key' => [403, ['error' => 'INVALID_KEY'], 'INVALID_KEY'],
            'missing key' => [400, ['error' => 'MISSING_KEY'], 'MISSING_KEY'],
            'routing 404 (wrong path)' => [404, ['title' => 'An error occurred', 'status' => 404, 'detail' => 'Not Found'], 'HTTP_404'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('rejectedRequests')]
    public function test_client_errors_throw_misconfigured_and_log_critical_without_the_key(int $status, array $body, string $reason): void
    {
        Http::fake(['essense.test/*' => Http::response($body, $status)]);
        Log::spy();

        try {
            $this->client()->listGames();
            $this->fail('Expected EssenseMisconfigured');
        } catch (EssenseMisconfigured $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringNotContainsString('test-key', $e->getMessage());
        }

        Log::shouldHaveReceived('critical')->once()->withArgs(
            fn (string $message, array $context) => $context['reason'] === $reason
                && ! str_contains(json_encode([$message, $context]), 'test-key'),
        );
    }

    public function test_a_missing_key_fails_without_sending_anything(): void
    {
        config(['services.essense.key' => '']);
        $this->app->forgetInstance(EssenseClient::class);
        Http::fake();

        $this->expectException(EssenseMisconfigured::class);

        try {
            $this->client()->listGames();
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{\Closure}>
     */
    public static function unusableResponses(): array
    {
        return [
            'server error' => [fn () => Http::response('Bad gateway', 502)],
            'HTML instead of JSON' => [fn () => Http::response('<!DOCTYPE html><html></html>', 200)],
            'JSON without games' => [fn () => Http::response(['unexpected' => true])],
            'connection failure' => [fn () => Http::failedConnection()],
        ];
    }

    #[DataProvider('unusableResponses')]
    public function test_unusable_responses_throw_unavailable(\Closure $response): void
    {
        Sleep::fake();
        Http::fake(['essense.test/*' => $response()]);

        $this->expectException(EssenseUnavailable::class);
        $this->client()->listGames();
    }

    public function test_reads_are_retried_after_a_connection_failure(): void
    {
        Sleep::fake();
        Http::fake(['essense.test/games/list' => Http::sequence()
            ->pushFailedConnection()
            ->push(['games' => [['id' => 1, 'name' => 'Zelda', 'votes' => 0]]])]);

        $this->assertCount(1, $this->client()->listGames());
        Http::assertSentCount(2);
    }

    public function test_mutations_are_never_retried_so_a_vote_cannot_be_counted_twice(): void
    {
        Sleep::fake();
        Http::fake(['essense.test/games/vote' => Http::sequence()
            ->pushFailedConnection()
            ->push(['success' => true])]);

        try {
            $this->client()->vote(1);
            $this->fail('Expected EssenseUnavailable');
        } catch (EssenseUnavailable) {
            Http::assertSentCount(1);
        }
    }

    public function test_flush_games_posts_to_cache_flush_with_the_key_in_a_form_body(): void
    {
        Http::fake(['essense.test/cache/flush' => Http::response(['success' => true])]);

        $this->client()->flushGames();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://essense.test/cache/flush'
            && $request->isForm()
            && $request['api_key'] === 'test-key');
    }

    public function test_flush_games_is_never_retried(): void
    {
        Sleep::fake();
        Http::fake(['essense.test/cache/flush' => Http::sequence()->pushFailedConnection()->push(['success' => true])]);

        try {
            $this->client()->flushGames();
            $this->fail('Expected EssenseUnavailable');
        } catch (EssenseUnavailable) {
            Http::assertSentCount(1);
        }
    }

    public function test_flush_games_without_success_true_is_unavailable(): void
    {
        Http::fake(['essense.test/cache/flush' => Http::response(['success' => false])]);

        $this->expectException(EssenseUnavailable::class);
        $this->client()->flushGames();
    }
}
