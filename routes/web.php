<?php

use App\Http\Controllers\AdminController;
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
    // Deposit is restricted to admins.
    Route::post('wallet/deposit', [WalletController::class, 'deposit'])
        ->middleware('admin')->name('wallet.deposit');

    // Admin-only area
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        // User module
        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::patch('users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
        Route::patch('users/{user}/wallet', [AdminController::class, 'updateWallet'])->name('users.wallet');

        // Tables module
        Route::get('tables', [AdminController::class, 'tables'])->name('tables');
        Route::delete('tables/{table:code}', [AdminController::class, 'deleteTable'])->name('tables.delete');
    });
});

require __DIR__.'/settings.php';
