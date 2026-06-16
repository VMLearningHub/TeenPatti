<?php

use App\Models\TablePlayer;
use Illuminate\Support\Facades\Broadcast;

// Per-user private channel (reserved for per-user payloads such as freshly
// dealt hole cards). Standard Laravel convention.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public table state channel. Only players seated at the table may subscribe.
// Carries public deltas only (pot, turn, dealer, actions) — never hole cards.
Broadcast::channel('table.{code}', function ($user, string $code) {
    return TablePlayer::query()
        ->where('user_id', $user->id)
        ->whereHas('table', fn ($q) => $q->where('code', $code))
        ->exists();
});
