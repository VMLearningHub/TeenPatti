<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_id')->constrained('hands')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', [
                'boot', 'blind_bet', 'chaal', 'pack', 'show',
                'sideshow_request', 'sideshow_accept', 'sideshow_decline', 'see_cards',
            ]);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('was_blind')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
