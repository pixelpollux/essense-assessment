<?php

namespace App\Models;

use App\Enums\ActionType;
use App\Enums\RefundReason;
use Carbon\CarbonImmutable;
use Database\Factories\DailyActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $local_date "YYYY-MM-DD" in the user's timezone
 * @property ActionType $type
 * @property int|null $game_id
 * @property string $game_name
 * @property CarbonImmutable|null $refunded_at
 * @property RefundReason|null $refund_reason
 */
class DailyAction extends Model
{
    /** @use HasFactory<DailyActionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'local_date', 'type', 'game_id', 'game_name', 'refunded_at', 'refund_reason'];

    protected function casts(): array
    {
        // local_date is deliberately left uncast: it's a plain "YYYY-MM-DD" calendar date in the
        // user's timezone. A date cast would store "YYYY-MM-DD 00:00:00", and lookups by date would miss.
        return [
            'type' => ActionType::class,
            'game_id' => 'integer',
            'refunded_at' => 'immutable_datetime',
            'refund_reason' => RefundReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
