<?php

use App\Services\Cards\Card;
use App\Services\Cards\HandComparator;
use App\Services\Cards\HandEvaluator;

function makeCards(array $strings): array
{
    return array_map(function (string $s) {
        $suit = substr($s, -1);
        $rank = substr($s, 0, strlen($s) - 1);

        return new Card($rank, $suit);
    }, $strings);
}

beforeEach(function () {
    $this->ev = new HandEvaluator;
    $this->cmp = new HandComparator;
});

it('trio beats pure sequence', function () {
    $trio = $this->ev->evaluate(makeCards(['2S', '2H', '2D']));
    $pure = $this->ev->evaluate(makeCards(['AS', 'KS', 'QS']));
    expect($this->cmp->compare($trio, $pure))->toBe(1);
    expect($this->cmp->compare($pure, $trio))->toBe(-1);
});

it('higher pair wins', function () {
    $kings = $this->ev->evaluate(makeCards(['KS', 'KH', '2D']));
    $queens = $this->ev->evaluate(makeCards(['QS', 'QH', 'AD']));
    expect($this->cmp->compare($kings, $queens))->toBe(1);
});

it('same pair uses kicker', function () {
    $a = $this->ev->evaluate(makeCards(['KS', 'KH', 'AD']));
    $b = $this->ev->evaluate(makeCards(['KS', 'KC', '5D']));
    expect($this->cmp->compare($a, $b))->toBe(1);
});

it('identical hands tie', function () {
    $a = $this->ev->evaluate(makeCards(['KS', 'KH', '2D']));
    $b = $this->ev->evaluate(makeCards(['KS', 'KH', '2D']));
    expect($this->cmp->compare($a, $b))->toBe(0);
});

it('pure sequence A-K-Q beats pure sequence K-Q-J', function () {
    $a = $this->ev->evaluate(makeCards(['AS', 'KS', 'QS']));
    $b = $this->ev->evaluate(makeCards(['KH', 'QH', 'JH']));
    expect($this->cmp->compare($a, $b))->toBe(1);
});

it('low A-2-3 sequence is lowest sequence', function () {
    $low = $this->ev->evaluate(makeCards(['AH', '2D', '3S']));
    $other = $this->ev->evaluate(makeCards(['4H', '5D', '6S']));
    expect($this->cmp->compare($other, $low))->toBe(1);
});
