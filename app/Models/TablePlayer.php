<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TablePlayer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'chips_on_table' => 'decimal:2',
        'is_active' => 'boolean',
        'seat_position' => 'integer',
        'joined_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(PokerTable::class, 'poker_table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
