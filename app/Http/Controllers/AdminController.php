<?php

namespace App\Http\Controllers;

use App\Models\PokerTable;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * User-management module — list every user with their status.
     */
    public function users(): Response
    {
        $users = User::query()
            ->orderByDesc('id')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'wallet_balance', 'created_at']);

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    /**
     * Toggle a user between active and inactive.
     */
    public function toggleUser(User $user)
    {
        // An admin cannot lock themselves out.
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot change your own active status.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with(
            'success',
            "{$user->name} is now ".($user->is_active ? 'active' : 'inactive').'.'
        );
    }

    /**
     * Set a user's wallet balance to a new value and record the adjustment.
     */
    public function updateWallet(Request $request, User $user)
    {
        $data = $request->validate([
            'wallet_balance' => 'required|numeric|min:0|max:100000000',
        ]);

        $new = (float) $data['wallet_balance'];
        $old = (float) $user->wallet_balance;
        $delta = round($new - $old, 2);

        $user->update(['wallet_balance' => $new]);

        if ($delta !== 0.0) {
            Transaction::create([
                'user_id' => $user->id,
                'type' => $delta > 0 ? 'deposit' : 'withdraw',
                'amount' => $delta,
                'balance_after' => $new,
                'reference_type' => 'admin_adjustment',
                'reference_id' => Auth::id(),
            ]);
        }

        return back()->with('success', "{$user->name}'s wallet updated to ₹{$new}.");
    }

    /**
     * Admin module — list every lobby table.
     */
    public function tables(): Response
    {
        $tables = PokerTable::query()
            ->withCount(['tablePlayers' => fn ($q) => $q->where('is_active', true)])
            ->with(['tablePlayers' => fn ($q) => $q->orderBy('seat_position')
                ->with('user:id,name,role')])
            ->latest()
            ->get();

        return Inertia::render('Admin/Tables', [
            'tables' => $tables,
        ]);
    }

    /**
     * Delete a lobby table (cascades to its players, hands and bets).
     */
    public function deleteTable(PokerTable $table)
    {
        $name = $table->name;
        $table->delete();

        return back()->with('success', "Table \"{$name}\" deleted.");
    }
}
