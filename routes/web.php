<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Lobby
    Route::get('lobby', [LobbyController::class, 'index'])->name('lobby');
    Route::post('lobby/tables', [LobbyController::class, 'store'])->name('table.create');
    Route::post('lobby/join', [LobbyController::class, 'joinByCode'])->name('table.joinByCode');
    Route::post('lobby/tables/{table:code}/join', [LobbyController::class, 'join'])->name('table.join');

    // Game
    Route::get('table/{table:code}', [GameController::class, 'show'])->name('table.show');
    Route::post('table/{table:code}/start', [GameController::class, 'start'])->name('table.start');
    Route::post('table/{table:code}/action', [GameController::class, 'action'])->name('table.action');
    Route::post('table/{table:code}/leave', [GameController::class, 'leave'])->name('table.leave');

    // Wallet
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
});

require __DIR__.'/settings.php';
