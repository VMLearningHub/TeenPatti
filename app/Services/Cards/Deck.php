<?php

namespace App\Services\Cards;

use RuntimeException;

class Deck
{
    /** @var array<int, Card> */
    protected array $cards = [];

    public function __construct()
    {
        $this->build();
    }

    public function build(): self
    {
        $this->cards = [];
        foreach (Card::SUITS as $suit) {
            foreach (Card::RANKS as $rank) {
                $this->cards[] = new Card($rank, $suit);
            }
        }

        return $this;
    }

    /**
     * Cryptographically secure Fisher–Yates shuffle.
     */
    public function shuffle(): self
    {
        $count = count($this->cards);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$this->cards[$i], $this->cards[$j]] = [$this->cards[$j], $this->cards[$i]];
        }

        return $this;
    }

    /**
     * @return array<int, Card>
     */
    public function deal(int $count): array
    {
        if ($count > count($this->cards)) {
            throw new RuntimeException('Not enough cards in deck.');
        }

        return array_splice($this->cards, 0, $count);
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode(',', array_map(fn (Card $c) => $c->toString(), $this->cards)));
    }
}
