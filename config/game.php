<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Turn timer
    |--------------------------------------------------------------------------
    |
    | How many seconds a player has to act on their turn before the server
    | auto-folds (packs) them. Drives both the client countdown ring and the
    | AutoFoldTurn enforcement job.
    |
    */

    'turn_seconds' => (int) env('GAME_TURN_SECONDS', 20),

];
