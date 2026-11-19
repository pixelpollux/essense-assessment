<?php

namespace App\Services\Library;

use App\Enums\ActionType;
use App\Enums\RefundReason;
use Carbon\CarbonImmutable;

/**
 * A user's daily action for their current local day.
 *
 * - canAct, no gameName: nothing done yet today.
 * - !canAct: today's action was used ($usedFor) on $gameName.
 * - canAct with $refundReason: today's action on $gameName was given back.
 */
final readonly class DailyStatus
{
    public function __construct(
        public bool $canAct,
        public ?ActionType $usedFor,
        public ?string $gameName,
        public ?RefundReason $refundReason,
        /** The next midnight in the user's timezone. */
        public CarbonImmutable $resetsAt,
    ) {}
}
