<?php

use App\Services\Cards\Card;
use App\Services\Cards\HandEvaluator;

function makeHand(array $strings): array
{
    return array_map(function (string $s) {
        // e.g. "AH" → rank A, suit H. "10S" → rank 10, suit S.
        $suit = substr($s, -1);
        $rank = substr($s, 0, strlen($s) - 1);

        return new Card($rank, $suit);
    }, $strings);
}

beforeEach(function () {
    $this->evaluator = new HandEvaluator;
});

it('detects a trio', function () {
    $hand = makeHand(['AS', 'AH', 'AD']);
    $r = $this->evaluator->evaluate($hand);
    expect($r['rank'])->toBe(HandEvaluator::RANK_TRIO);
    expect($r['tiebreakers'][0])->toBe(14);
});

it('ranks aces trio higher than twos trio', function () {
    $a = $this->evaluator->evaluate(makeHand(['AS', 'AH', 'AD']));
    $b = $this->evaluator->evaluate(makeHand(['2S', '2H', '2D']));
    expect($a['tiebreakers'][0])->toBeGreaterThan($b['tiebreakers'][0]);
});

it('detects pure sequence A-K-Q', function () {
    $r = $this->evaluator->evaluate(makeHand(['AS', 'KS', 'QS']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_PURE_SEQUENCE);
    expect($r['tiebreakers'][0])->toBe(14);
});

it('detects pure sequence A-2-3 with rank 3 as high', function () {
    $r = $this->evaluator->evaluate(makeHand(['AH', '2H', '3H']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_PURE_SEQUENCE);
    expect($r['tiebreakers'][0])->toBe(3);
});

it('rejects K-A-2 as a sequence', function () {
    $r = $this->evaluator->evaluate(makeHand(['KS', 'AS', '2S']));
    // All same suit, not a sequence → color
    expect($r['rank'])->toBe(HandEvaluator::RANK_COLOR);
});

it('detects sequence (mixed suit)', function () {
    $r = $this->evaluator->evaluate(makeHand(['7S', '8H', '9D']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_SEQUENCE);
    expect($r['tiebreakers'][0])->toBe(9);
});

it('detects sequence A-2-3 (mixed suit)', function () {
    $r = $this->evaluator->evaluate(makeHand(['AH', '2D', '3S']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_SEQUENCE);
    expect($r['tiebreakers'][0])->toBe(3);
});

it('detects color', function () {
    $r = $this->evaluator->evaluate(makeHand(['2H', '5H', 'JH']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_COLOR);
    expect($r['tiebreakers'][0])->toBe(11);
});

it('uses suit as final tiebreaker on identical color values', function () {
    $a = $this->evaluator->evaluate(makeHand(['2S', '5S', 'JS'])); // spades
    $b = $this->evaluator->evaluate(makeHand(['2C', '5C', 'JC'])); // clubs
    // Both rank=color, values equal — spades > clubs
    expect($a['tiebreakers'][3])->toBeGreaterThan($b['tiebreakers'][3]);
});

it('detects pair', function () {
    $r = $this->evaluator->evaluate(makeHand(['KS', 'KH', '5D']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_PAIR);
    expect($r['tiebreakers'][0])->toBe(13);
    expect($r['tiebreakers'][1])->toBe(5);
});

it('detects high card', function () {
    $r = $this->evaluator->evaluate(makeHand(['KS', 'QH', '5D']));
    expect($r['rank'])->toBe(HandEvaluator::RANK_HIGH_CARD);
    expect($r['tiebreakers'])->toBe([13, 12, 5]);
});

it('throws for non-three-card hands', function () {
    $this->evaluator->evaluate(makeHand(['KS', 'QH']));
})->throws(InvalidArgumentException::class);
