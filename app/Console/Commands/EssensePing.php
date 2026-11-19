<?php

namespace App\Console\Commands;

use App\Services\Essense\EssenseApi;
use App\Services\Essense\Exceptions\EssenseMisconfigured;
use App\Services\Essense\Exceptions\EssenseUnavailable;
use Illuminate\Console\Command;

class EssensePing extends Command
{
    /**
     * @var string
     */
    protected $signature = 'essense:ping';

    /**
     * @var string
     */
    protected $description = 'Check that the Essense API is reachable and ESSENSE_API_KEY is accepted';

    public function handle(EssenseApi $client): int
    {
        $url = config('services.essense.url');

        try {
            $count = count($client->listGames());
        } catch (EssenseMisconfigured $e) {
            $this->components->error(match ($e->reason) {
                'MISSING_KEY' => 'ESSENSE_API_KEY is not set. Add it to .env (see README).',
                'INVALID_KEY' => 'The Essense API rejected ESSENSE_API_KEY. Check the key in .env.',
                default => "The Essense API rejected the request ({$e->reason}).",
            });

            return self::FAILURE;
        } catch (EssenseUnavailable $e) {
            $this->components->error("Could not reach the Essense API at {$url}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Connected to {$url}. The key works; the library has {$count} ".str('game')->plural($count).'.');

        return self::SUCCESS;
    }
}
