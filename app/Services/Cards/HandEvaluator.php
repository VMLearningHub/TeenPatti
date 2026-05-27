<?php

namespace App\Services\Cards;

use InvalidArgumentException;

class HandEvaluator
{
    public const RANK_TRIO = 1;

    public const RANK_PURE_SEQUENCE = 2;

    public const RANK_SEQUENCE = 3;

    public const RANK_COLOR = 4;

    public const RANK_PAIR = 5;

    public const RANK_HIGH_CARD = 6;

    public const RANK_NAMES = [
        self::RANK_TRIO => 'trio',
        self::RANK_PURE_SEQUENCE => 'pure_sequence',
        self::RANK_SEQUENCE => 'sequence',
        self::RANK_COLOR => 'color',
        self::RANK_PAIR => 'pair',
        self::RANK_HIGH_CARD => 'high_card',
    ];

    /**
     * Evaluate a 3-card Teen Patti hand.
     *
     * @param  array<int, Card>  $cards
     * @return array{rank:int, rank_name:string, tiebreakers:array<int,int>}
     */
    public function evaluate(array $cards): array
    {
        if (count($cards) !== 3) {
            throw new InvalidArgumentException('Teen Patti hands must have exactly 3 cards.');
        }

        // Sort descending by value for consistent tiebreakers
        $sorted = $cards;
        usort($sorted, fn (Card $a, Card $b) => $b->value() <=> $a->value());

        $values = array_map(fn (Card $c) => $c->value(), $sorted);
        $suits = array_map(fn (Card $c) => $c->suit, $sorted);
        $isSameSuit = count(array_unique($suits)) === 1;

        // Trio
        if ($values[0] === $values[1] && $values[1] === $values[2]) {
            return $this->result(self::RANK_TRIO, [$values[0]]);
        }

        $sequenceInfo = $this->detectSequence($values);

        // Pure Sequence (Straight Flush)
        if ($sequenceInfo !== null && $isSameSuit) {
            return $this->result(self::RANK_PURE_SEQUENCE, [$sequenceInfo['high']]);
        }

        // Sequence (Straight)
        if ($sequenceInfo !== null) {
            return $this->result(self::RANK_SEQUENCE, [$sequenceInfo['high']]);
        }

        // Color (Flush)
        if ($isSameSuit) {
            $suitRank = Card::SUIT_RANK[$sorted[0]->suit];

            return $this->result(self::RANK_COLOR, [$values[0], $values[1], $values[2], $suitRank]);
        }

        // Pair
        $pairValue = null;
        $kicker = null;
        if ($values[0] === $values[1]) {
            $pairValue = $values[0];
            $kicker = $values[2];
        } elseif ($values[1] === $values[2]) {
            $pairValue = $values[1];
            $kicker = $values[0];
        } elseif ($values[0] === $values[2]) {
            // Cannot happen after sort (since sort puts equals adjacent), but kept for safety
            $pairValue = $values[0];
            $kicker = $values[1];
        }

        if ($pairValue !== null) {
            return $this->result(self::RANK_PAIR, [$pairValue, $kicker]);
        }

        // High Card
        return $this->result(self::RANK_HIGH_CARD, $values);
    }

    /**
     * @param  array<int, int>  $sortedDescValues  values in descending order
     * @return array{high:int}|null
     */
    protected function detectSequence(array $sortedDescValues): ?array
    {
        $a = $sortedDescValues[0];
        $b = $sortedDescValues[1];
        $c = $sortedDescValues[2];

        // Normal descending consecutive (e.g. A-K-Q = 14-13-12, ..., 4-3-2)
        if ($a - 1 === $b && $b - 1 === $c) {
            return ['high' => $a];
        }

        // Special case: A-2-3 (low ace). Sorted desc would be 14-3-2 — convert ace to "1" for sequence.
        if ($a === 14 && $b === 3 && $c === 2) {
            // High card in this sequence is 3 for tiebreak purposes (it's the lowest straight)
            return ['high' => 3];
        }

        // K-A-2 is invalid (explicitly excluded)
        return null;
    }

    /**
     * @param  array<int, int>  $tiebreakers
     */
    protected function result(int $rank, array $tiebreakers): array
    {
        return [
            'rank' => $rank,
            'rank_name' => self::RANK_NAMES[$rank],
            'tiebreakers' => array_values($tiebreakers),
        ];
    }
}
