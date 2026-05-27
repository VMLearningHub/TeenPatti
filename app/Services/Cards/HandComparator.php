<?php

namespace App\Services\Cards;

class HandComparator
{
    /**
     * Compare two evaluated hands (output of HandEvaluator::evaluate).
     * Returns 1 if A wins, -1 if B wins, 0 on exact tie.
     */
    public function compare(array $handA, array $handB): int
    {
        // Lower rank number = better hand
        if ($handA['rank'] !== $handB['rank']) {
            return $handA['rank'] < $handB['rank'] ? 1 : -1;
        }

        $aTb = $handA['tiebreakers'];
        $bTb = $handB['tiebreakers'];
        $len = max(count($aTb), count($bTb));
        for ($i = 0; $i < $len; $i++) {
            $av = $aTb[$i] ?? 0;
            $bv = $bTb[$i] ?? 0;
            if ($av !== $bv) {
                return $av > $bv ? 1 : -1;
            }
        }

        return 0;
    }
}
