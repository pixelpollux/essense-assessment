<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who has an active vote on which game. Vote totals live in the Essense API;
     * this table only knows who voted for what.
     */
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The Essense API's game ID. Not a foreign key: games live in the API.
            $table->unsignedBigInteger('game_id');
            // Decoded name when the vote was cast. IDs restart after /cache/flush, so a row
            // only counts while ID *and* name still match the API (CLAUDE.md, decision 5).
            // text: the API has no length limit for games added outside this app.
            $table->text('game_name');
            $table->timestamps();

            // One active vote per user per game.
            $table->unique(['user_id', 'game_id']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
