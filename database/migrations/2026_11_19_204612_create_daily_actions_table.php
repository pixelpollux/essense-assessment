<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each user's one action per local day (a vote or an add), and whether it was refunded.
     */
    public function up(): void
    {
        Schema::create('daily_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The date in the user's own timezone when the action was taken.
            $table->date('local_date');
            $table->string('type'); // App\Enums\ActionType
            // Null while an add is in flight: filled in once the API returns the new ID.
            $table->unsignedBigInteger('game_id')->nullable();
            $table->text('game_name');
            // A refunded row frees the day's action; the next action reuses the row (decision 6).
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable(); // App\Enums\RefundReason
            $table->timestamps();

            // The daily limit, enforced by the database itself.
            $table->unique(['user_id', 'local_date']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_actions');
    }
};
