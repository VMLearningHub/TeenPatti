<?php

namespace Database\Seeders;

use App\Models\PokerTable;
use App\Models\TablePlayer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            ['name' => 'Aarav', 'email' => 'aarav@example.com', 'role' => 'admin'],
            ['name' => 'Priya', 'email' => 'priya@example.com', 'role' => 'user'],
            ['name' => 'Vikram', 'email' => 'vikram@example.com', 'role' => 'user'],
            ['name' => 'Meera', 'email' => 'meera@example.com', 'role' => 'user'],
        ])->map(fn (array $u) => User::updateOrCreate(
            ['email' => $u['email']],
            [
                'name' => $u['name'],
                'password' => Hash::make('password'),
                'role' => $u['role'],
                'is_active' => true,
                'wallet_balance' => 10000,
                'email_verified_at' => now(),
            ],
        ));

        if (! PokerTable::where('name', 'Demo Table')->exists()) {
            $table = PokerTable::create([
                'name' => 'Demo Table',
                'code' => 'DEMO01',
                'boot_amount' => 10,
                'min_bet' => 10,
                'max_bet' => 1000,
                'max_players' => 6,
                'betting_type' => 'fixed_limit',
                'status' => 'waiting',
                'dealer_seat' => 0,
            ]);

            $seat = 1;
            foreach ($users as $u) {
                TablePlayer::create([
                    'poker_table_id' => $table->id,
                    'user_id' => $u->id,
                    'seat_position' => $seat++,
                    'chips_on_table' => 1000,
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
                $u->decrement('wallet_balance', 1000);
            }
        }
    }
}
