<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandPlayer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cards' => 'array',
        'is_blind' => 'boolean',
        'has_packed' => 'boolean',
        'is_winner' => 'boolean',
        'seat_position' => 'integer',
        'total_contributed' => 'decimal:2',
        'hand_rank' => 'integer',
    ];

    public function hand(): BelongsTo
    {
        return $this->belongsTo(Hand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
