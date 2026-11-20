<?php

namespace App\Console\Commands;

use App\Models\DailyAction;
use App\Models\Vote;
use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\EssenseException;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Empty the game library on both sides together (CLAUDE.md, decision 5).
 *
 * /cache/flush deletes every game in the API and restarts game IDs at 1, so our votes and daily
 * actions are cleared in the same run. User accounts are kept, so people can keep using them.
 */
class GamesReset extends Command
{
    use ConfirmableTrait;

    /**
     * @var string
     */
    protected $signature = 'games:reset {--force : Skip the confirmation prompt in production}';

    /**
     * @var string
     */
    protected $description = 'Delete every game and vote (in the Essense API and locally), keeping user accounts';

    public function handle(EssenseApi $api): int
    {
        // Laravel's standard guard for destructive commands: asks only in production, --force skips it.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // API first, like removeGame: the flush is safe to repeat, and if it fails nothing local has changed.
        try {
            $api->flushGames();
        } catch (EssenseException $e) {
            $this->components->error(match (true) {
                $e instanceof EssenseMisconfigured => "The Essense API rejected the request ({$e->reason}). Nothing was changed. Run `php artisan essense:ping` to check the key.",
                default => 'Could not confirm the reset. Local votes and daily actions were kept, but the API may already be empty. Check the library before retrying.',
            });

            return self::FAILURE;
        }

        [$votes, $actions] = DB::transaction(fn () => [Vote::query()->delete(), DailyAction::query()->delete()]);

        $this->components->info(sprintf(
            'Cleared the game library: every game, %d %s and %d daily %s.',
            $votes,
            str('vote')->plural($votes),
            $actions,
            str('action')->plural($actions),
        ));
        $this->line('  User accounts were kept.');

        return self::SUCCESS;
    }
}
