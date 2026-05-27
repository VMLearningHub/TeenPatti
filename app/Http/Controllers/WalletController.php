<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('Wallet/Index', [
            'wallet' => $user->wallet_balance,
            'transactions' => $transactions,
        ]);
    }

    public function deposit(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
        ]);

        $user = Auth::user();
        $user->increment('wallet_balance', $data['amount']);
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $data['amount'],
            'balance_after' => $user->fresh()->wallet_balance,
        ]);

        return back();
    }
}
