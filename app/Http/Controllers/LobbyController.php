<?php

namespace App\Http\Controllers;

use App\Models\PokerTable;
use App\Services\Game\TableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LobbyController extends Controller
{
    public function __construct(protected TableService $tableService) {}

    public function index(): Response
    {
        $tables = PokerTable::query()
            ->withCount(['tablePlayers' => fn ($q) => $q->where('is_active', true)])
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('Lobby/Index', [
            'tables' => $tables,
            'wallet' => Auth::user()?->wallet_balance,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:60',
            'boot_amount' => 'nullable|numeric|min:1|max:10000',
            'min_bet' => 'nullable|numeric|min:1|max:10000',
            'max_bet' => 'nullable|numeric|min:1|max:1000000',
            'max_players' => 'nullable|integer|min:2|max:10',
            'buy_in' => 'nullable|numeric|min:1|max:1000000',
        ]);

        $table = $this->tableService->create(Auth::user(), $data);

        return redirect()->route('table.show', $table->code);
    }

    public function join(Request $request, PokerTable $table)
    {
        $data = $request->validate([
            'buy_in' => 'nullable|numeric|min:1|max:1000000',
        ]);
        $this->tableService->join($table, Auth::user(), (float) ($data['buy_in'] ?? 500));

        return redirect()->route('table.show', $table->code);
    }

    public function joinByCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);
        $table = PokerTable::where('code', strtoupper($data['code']))->firstOrFail();
        $this->tableService->join($table, Auth::user());

        return redirect()->route('table.show', $table->code);
    }
}
