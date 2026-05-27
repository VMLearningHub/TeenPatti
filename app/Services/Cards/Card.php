<?php

namespace App\Services\Cards;

use InvalidArgumentException;

class Card
{
    public const RANKS = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    public const SUITS = ['S', 'H', 'D', 'C'];

    public const RANK_VALUES = [
        '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7,
        '8' => 8, '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14,
    ];

    public const SUIT_RANK = ['S' => 4, 'H' => 3, 'D' => 2, 'C' => 1];

    public function __construct(
        public readonly string $rank,
        public readonly string $suit,
    ) {
        if (! in_array($rank, self::RANKS, true)) {
            throw new InvalidArgumentException("Invalid rank: {$rank}");
        }
        if (! in_array($suit, self::SUITS, true)) {
            throw new InvalidArgumentException("Invalid suit: {$suit}");
        }
    }

    public function value(): int
    {
        return self::RANK_VALUES[$this->rank];
    }

    public function suitRank(): int
    {
        return self::SUIT_RANK[$this->suit];
    }

    public function toArray(): array
    {
        return ['rank' => $this->rank, 'suit' => $this->suit];
    }

    public function toString(): string
    {
        return $this->rank.$this->suit;
    }

    public static function fromArray(array $data): self
    {
        return new self($data['rank'], $data['suit']);
    }
}
