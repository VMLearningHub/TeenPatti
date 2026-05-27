<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poker_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->decimal('boot_amount', 12, 2)->default(10);
            $table->decimal('min_bet', 12, 2)->default(10);
            $table->decimal('max_bet', 12, 2)->default(1000);
            $table->unsignedInteger('pot_limit_multiplier')->default(64);
            $table->unsignedTinyInteger('max_players')->default(6);
            $table->enum('betting_type', ['fixed_limit', 'spread_limit', 'pot_limit', 'no_limit'])
                ->default('fixed_limit');
            $table->enum('status', ['waiting', 'in_progress', 'ended'])->default('waiting');
            $table->unsignedBigInteger('current_hand_id')->nullable();
            $table->unsignedTinyInteger('dealer_seat')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poker_tables');
    }
};
