<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poker_table_id')->constrained('poker_tables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seat_position');
            $table->decimal('chips_on_table', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['poker_table_id', 'seat_position']);
            $table->unique(['poker_table_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_players');
    }
};
