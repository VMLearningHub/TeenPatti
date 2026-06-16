<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hands', function (Blueprint $table) {
            // The dealer seat from the previous hand, so the client can animate
            // the dealer button sliding from the old seat to the new one.
            $table->unsignedTinyInteger('previous_dealer_seat')->nullable()->after('dealer_seat');

            // Turn clock: drives the countdown ring on the client and the
            // server-side auto-fold enforcement.
            $table->timestamp('turn_started_at')->nullable()->after('current_turn_seat');
            $table->timestamp('turn_deadline')->nullable()->after('turn_started_at');

            // The order cards were dealt ([{seat_position,user_id}, ...]) so the
            // deal animation can be reproduced on poll/refresh, not just live events.
            $table->json('deal_order')->nullable()->after('status');

            // Monotonic per-hand action counter. Every broadcast event carries it
            // so the client can dedupe/order events and detect missed ones.
            $table->unsignedInteger('action_seq')->default(0)->after('deal_order');
        });
    }

    public function down(): void
    {
        Schema::table('hands', function (Blueprint $table) {
            $table->dropColumn([
                'previous_dealer_seat',
                'turn_started_at',
                'turn_deadline',
                'deal_order',
                'action_seq',
            ]);
        });
    }
};
