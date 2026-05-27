<?php

namespace App\Services\Game;

use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TableService
{
    public function create(User $owner, array $attributes): PokerTable
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $table = PokerTable::create([
                'name' => $attributes['name'] ?? "{$owner->name}'s Table",
                'code' => $this->uniqueCode(),
                'boot_amount' => $attributes['boot_amount'] ?? 10,
                'min_bet' => $attributes['min_bet'] ?? 10,
                'max_bet' => $attributes['max_bet'] ?? 1000,
                'max_players' => min(10, max(2, (int) ($attributes['max_players'] ?? 6))),
                'betting_type' => $attributes['betting_type'] ?? 'fixed_limit',
                'status' => 'waiting',
                'dealer_seat' => 0,
            ]);

            $this->join($table, $owner, (float) ($attributes['buy_in'] ?? 500));

            return $table->fresh();
        });
    }

    public function join(PokerTable $table, User $user, float $buyIn = 500): TablePlayer
    {
        return DB::transaction(function () use ($table, $user, $buyIn) {
            $existing = TablePlayer::where('poker_table_id', $table->id)
                ->where('user_id', $user->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $taken = TablePlayer::where('poker_table_id', $table->id)
                ->pluck('seat_position')->all();
            if (count($taken) >= $table->max_players) {
                throw new RuntimeException('Table is full.');
            }

            $seat = $this->firstFreeSeat($taken, $table->max_players);

            if ((float) $user->wallet_balance < $buyIn) {
                throw new RuntimeException('Insufficient wallet balance for buy-in.');
            }

            $user->decrement('wallet_balance', $buyIn);
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'buy_in',
                'amount' => -$buyIn,
                'balance_after' => $user->fresh()->wallet_balance,
                'reference_type' => 'poker_table',
                'reference_id' => $table->id,
            ]);

            return TablePlayer::create([
                'poker_table_id' => $table->id,
                'user_id' => $user->id,
                'seat_position' => $seat,
                'chips_on_table' => $buyIn,
                'is_active' => true,
                'joined_at' => now(),
            ]);
        });
    }

    public function leave(PokerTable $table, User $user): void
    {
        DB::transaction(function () use ($table, $user) {
            $tp = TablePlayer::where('poker_table_id', $table->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (! $tp) {
                return;
            }

            $cashOut = (float) $tp->chips_on_table;
            if ($cashOut > 0) {
                $user->increment('wallet_balance', $cashOut);
                Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'cash_out',
                    'amount' => $cashOut,
                    'balance_after' => $user->fresh()->wallet_balance,
                    'reference_type' => 'poker_table',
                    'reference_id' => $table->id,
                ]);
            }
            $tp->delete();
        });
    }

    protected function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (PokerTable::where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<int, int>  $taken
     */
    protected function firstFreeSeat(array $taken, int $max): int
    {
        for ($s = 1; $s <= $max; $s++) {
            if (! in_array($s, $taken, true)) {
                return $s;
            }
        }
        throw new RuntimeException('No free seat.');
    }
}
