<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PokerTable extends Model
{
    use HasFactory;

    protected $table = 'poker_tables';

    protected $guarded = [];

    protected $casts = [
        'boot_amount' => 'decimal:2',
        'min_bet' => 'decimal:2',
        'max_bet' => 'decimal:2',
        'pot_limit_multiplier' => 'integer',
        'max_players' => 'integer',
        'dealer_seat' => 'integer',
    ];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'table_players')
            ->withPivot(['seat_position', 'chips_on_table', 'is_active'])
            ->withTimestamps();
    }

    public function tablePlayers(): HasMany
    {
        return $this->hasMany(TablePlayer::class);
    }

    public function activeTablePlayers(): HasMany
    {
        return $this->hasMany(TablePlayer::class)->where('is_active', true);
    }

    public function hands(): HasMany
    {
        return $this->hasMany(Hand::class);
    }

    public function currentHand(): BelongsTo
    {
        return $this->belongsTo(Hand::class, 'current_hand_id');
    }
}
