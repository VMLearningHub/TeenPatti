<?php

namespace App\Services\Game;

use App\Models\Hand;
use App\Models\HandPlayer;
use App\Models\PokerTable;

class BettingRules
{
    /**
     * Validate that a "chaal" amount falls in the legal range for this player.
     */
    public function validateBet(Hand $hand, HandPlayer $player, float $amount): bool
    {
        $stake = (float) $hand->current_stake;
        $table = $hand->table ?? PokerTable::find($hand->poker_table_id);
        $min = (float) ($table->min_bet ?? $stake);
        $max = (float) ($table->max_bet ?? PHP_INT_MAX);

        if ($stake <= 0) {
            $stake = $min;
        }

        if ($player->is_blind) {
            $low = $stake;
            $high = $stake * 2;
        } else {
            $low = $stake * 2;
            $high = $stake * 4;
        }

        if ($amount < $low - 0.001 || $amount > $high + 0.001) {
            return false;
        }

        if ($amount < $min - 0.001 || $amount > $max + 0.001) {
            return false;
        }

        return true;
    }

    /**
     * Return [min, max] legal chaal range for this player.
     *
     * @return array{0:float,1:float}
     */
    public function legalRange(Hand $hand, HandPlayer $player): array
    {
        $stake = (float) $hand->current_stake;
        if ($stake <= 0) {
            $table = $hand->table ?? PokerTable::find($hand->poker_table_id);
            $stake = (float) $table->min_bet;
        }

        if ($player->is_blind) {
            return [$stake, $stake * 2];
        }

        return [$stake * 2, $stake * 4];
    }

    /**
     * After a player bets `amount`, what becomes the new current_stake?
     * Blind: stake = amount.  Seen: stake = amount / 2.
     */
    public function nextStake(HandPlayer $player, float $amount): float
    {
        return $player->is_blind ? $amount : $amount / 2;
    }

    /**
     * Cost for player to "show" (only legal with 2 players left).
     * If both seen → 2× stake. If vs blind → 4× stake.
     */
    public function showCost(Hand $hand, HandPlayer $requester, HandPlayer $other): float
    {
        $stake = (float) $hand->current_stake;
        if ($requester->is_blind || $other->is_blind) {
            return $stake * 4;
        }

        return $stake * 2;
    }
}
