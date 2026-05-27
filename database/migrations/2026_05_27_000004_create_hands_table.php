<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poker_table_id')->constrained('poker_tables')->cascadeOnDelete();
            $table->unsignedTinyInteger('dealer_seat');
            $table->unsignedTinyInteger('current_turn_seat')->nullable();
            $table->decimal('pot_amount', 12, 2)->default(0);
            $table->decimal('current_stake', 12, 2)->default(0);
            $table->enum('status', ['dealing', 'betting', 'showdown', 'completed'])->default('dealing');
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('sideshow_requester_id')->nullable();
            $table->unsignedBigInteger('sideshow_target_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hands');
    }
};
