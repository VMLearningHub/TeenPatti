<?php

use App\Models\Hand;
use App\Models\HandPlayer;
use App\Models\PokerTable;
use App\Services\Game\BettingRules;

it('validates blind bet range', function () {
    $table = new PokerTable(['boot_amount' => 10, 'min_bet' => 10, 'max_bet' => 1000]);
    $hand = new Hand(['current_stake' => 10]);
    $hand->setRelation('table', $table);
    $player = new HandPlayer(['is_blind' => true]);

    $rules = new BettingRules;
    expect($rules->validateBet($hand, $player, 10))->toBeTrue();   // = stake
    expect($rules->validateBet($hand, $player, 20))->toBeTrue();   // 2× stake
    expect($rules->validateBet($hand, $player, 9))->toBeFalse();   // below stake
    expect($rules->validateBet($hand, $player, 21))->toBeFalse();  // above 2× stake
});

it('validates seen bet range', function () {
    $table = new PokerTable(['boot_amount' => 10, 'min_bet' => 10, 'max_bet' => 1000]);
    $hand = new Hand(['current_stake' => 10]);
    $hand->setRelation('table', $table);
    $player = new HandPlayer(['is_blind' => false]);

    $rules = new BettingRules;
    expect($rules->validateBet($hand, $player, 20))->toBeTrue();   // 2× stake
    expect($rules->validateBet($hand, $player, 40))->toBeTrue();   // 4× stake
    expect($rules->validateBet($hand, $player, 19))->toBeFalse();
    expect($rules->validateBet($hand, $player, 41))->toBeFalse();
});

it('computes nextStake correctly', function () {
    $rules = new BettingRules;
    $blind = new HandPlayer(['is_blind' => true]);
    $seen = new HandPlayer(['is_blind' => false]);

    expect($rules->nextStake($blind, 20))->toBe(20.0);
    expect($rules->nextStake($seen, 40))->toBe(20.0);
});
