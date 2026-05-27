<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hand_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_id')->constrained('hands')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat_position');
            $table->json('cards');
            $table->boolean('is_blind')->default(true);
            $table->boolean('has_packed')->default(false);
            $table->boolean('is_winner')->default(false);
            $table->unsignedTinyInteger('hand_rank')->nullable();
            $table->decimal('total_contributed', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['hand_id', 'user_id']);
            $table->unique(['hand_id', 'seat_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hand_players');
    }
};
