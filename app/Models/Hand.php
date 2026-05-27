<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hand extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pot_amount' => 'decimal:2',
        'current_stake' => 'decimal:2',
        'dealer_seat' => 'integer',
        'current_turn_seat' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(PokerTable::class, 'poker_table_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(HandPlayer::class);
    }

    public function activePlayers(): HasMany
    {
        return $this->hasMany(HandPlayer::class)->where('has_packed', false);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
