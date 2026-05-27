# Teen Patti (3 Patti) — Laravel + Vue/Inertia

A real-time, multiplayer Teen Patti web game built on Laravel 13 + Vue 3 + Inertia + SQLite.

The build follows the spec in `teen-patti-laravel-prompt.md` with one tech swap:
**Inertia/Vue instead of Livewire** (since this starter kit ships with Inertia), and
client-side **polling every 2.5s** in lieu of Reverb WebSockets (broadcasting layer
not configured in this environment).

---

## 1. Tech Stack

| Layer       | Choice                                        |
|-------------|-----------------------------------------------|
| Backend     | Laravel 13 (PHP 8.3+)                         |
| Frontend    | Vue 3 + Inertia + Tailwind v4                 |
| DB          | SQLite (default; switch via `.env`)           |
| Auth        | Laravel Fortify (already wired)               |
| Tests       | Pest 4                                        |
| Realtime    | HTTP polling (Inertia partial reload, 2.5 s)  |

---

## 2. Setup

```bash
# from project root
composer install
cp .env.example .env             # only if .env missing
php artisan key:generate
touch database/database.sqlite   # only if missing
php artisan migrate --force
php artisan db:seed --force
npm install
npm run dev                      # or `npm run build` for prod (needs Node ≥ 20.19)
php artisan serve
```

Open <http://localhost:8000>. Log in with one of the seeded users:

| Email                 | Password   | Wallet  |
|-----------------------|------------|---------|
| `aarav@example.com`   | `password` | ₹9,000  |
| `priya@example.com`   | `password` | ₹9,000  |
| `vikram@example.com`  | `password` | ₹9,000  |
| `meera@example.com`   | `password` | ₹9,000  |

A demo table with code `DEMO01` is pre-created and pre-seated.

---

## 3. Domain Model

```
users 1 ── ∞ table_players ∞ ── 1 poker_tables
                                        │
                                        ∞
                                        │
                                       hands 1 ── ∞ hand_players
                                        │
                                        ∞
                                       bets

users 1 ── ∞ transactions  (wallet ledger)
```

### Key tables

| Table            | Purpose                                              |
|------------------|------------------------------------------------------|
| `users`          | Auth + `wallet_balance`, `avatar`, `is_online`       |
| `poker_tables`   | Boot/min/max bet, betting type, seat count, status   |
| `table_players`  | Seat assignments + chips-on-table per table          |
| `hands`          | One row per round; pot, current stake, turn, status  |
| `hand_players`   | Per-hand cards (JSON), blind/seen, packed, winner    |
| `bets`           | Full action history per hand                         |
| `transactions`   | Append-only wallet ledger                            |

---

## 4. Card Engine (`app/Services/Cards/`)

### `Card.php`
Immutable value object: `rank` (`2`–`10`, `J`, `Q`, `K`, `A`) + `suit`
(`S`, `H`, `D`, `C`). Helpers: `value()`, `suitRank()`, `toArray()`,
`toString()`, `fromArray()`.

### `Deck.php`
- `build()` → 52 cards
- `shuffle()` → **cryptographically secure Fisher–Yates** (`random_int`)
- `deal(int $count)` → array of Card
- `fingerprint()` → SHA-256 of remaining cards (provable-fairness hook)

### `HandEvaluator.php`
`evaluate(array $cards): array` returns
`['rank' => 1..6, 'rank_name' => '…', 'tiebreakers' => [...]]` where lower
rank = stronger hand.

| Rank | Name           | Notes                                                  |
|------|----------------|--------------------------------------------------------|
| 1    | trio           | Three of a kind                                        |
| 2    | pure_sequence  | Straight + flush. A-K-Q highest, A-2-3 valid           |
| 3    | sequence       | Straight, mixed suits                                  |
| 4    | color          | Flush, not sequential; suit S>H>D>C as final tiebreak  |
| 5    | pair           | Two same rank + kicker                                 |
| 6    | high_card      | None of the above                                      |

**A-2-3** is a valid sequence (treated as the lowest straight, tiebreaker = 3).
**K-A-2** is **not** a sequence (explicitly rejected; falls through to color/high-card).

### `HandComparator.php`
`compare($handA, $handB): int` → `1 / 0 / -1`. Used by sideshow and showdown.

---

## 5. Game Flow (`app/Services/Game/GameService.php`)

### `startHand(PokerTable $table): Hand`
1. Require ≥ 2 active players.
2. Rotate dealer to next active seat.
3. Shuffle deck (FY + `random_int`), deal 3 cards to each.
4. Debit boot from every seated player → credit pot.
5. Set `current_turn_seat` to player left of dealer (counter-clockwise = next seat number).
6. Transition hand `dealing → betting`.

### `playerAction(Hand, User, string $action, array $data)`

| Action               | Constraint                                                       | Side effect                                                                 |
|----------------------|------------------------------------------------------------------|------------------------------------------------------------------------------|
| `see_cards`          | Player must be blind                                             | `is_blind = false`                                                           |
| `pack`               | Player must be on turn                                           | `has_packed = true`, advance turn                                            |
| `chaal`              | Amount in `[stake, 2·stake]` blind / `[2·stake, 4·stake]` seen   | Debit wallet, increment pot, update `current_stake`, advance turn            |
| `sideshow_request`   | Requester seen, prev active player also seen, ≥3 active          | Debit `2·stake`, set `sideshow_requester/target_id`                          |
| `sideshow_respond`   | Only by sideshow target                                          | Accept → compare hands (tie → requester folds); Decline → continue           |
| `show`               | Exactly 2 active players, on turn                                | Debit cost (`2·stake` both seen / `4·stake` vs blind), evaluate, end hand    |

### `endHand(Hand, ?User $winner)`
Credit pot to winner, mark `is_winner`, set hand `completed`, clear
`current_hand_id` on table. On a tie show, pot is split equally.

---

## 6. Betting Rules (`app/Services/Game/BettingRules.php`)

```php
$stake = $hand->current_stake;
$range = $player->is_blind
    ? [$stake,       $stake * 2]   // blind chaal
    : [$stake * 2,   $stake * 4];  // seen chaal
```

After a bet:
- **Blind bettor** → `current_stake = amount`
- **Seen bettor**  → `current_stake = amount / 2`

Keeps blind-vs-seen math consistent (seen pays 2× of stake, blind pays 1×).

---

## 7. Wallet & Anti-Cheat

- **Server-authoritative** deck shuffle, deal, hand evaluation, bet validation.
- Opponents' hole cards are **never** sent to the client until showdown
  (see `GameController::show` — cards filtered per `user_id`).
- All wallet mutations go through `Transaction` ledger rows (`bet`, `win`,
  `buy_in`, `cash_out`, `deposit`).
- Negative balances throw — checked before every debit.
- Hand actions wrapped in `DB::transaction` with `lockForUpdate()` on the hand
  row to prevent races.

---

## 8. HTTP Routes (`routes/web.php`)

All under `auth + verified` middleware.

| Method | URI                                | Action                                 |
|--------|------------------------------------|----------------------------------------|
| GET    | `/lobby`                           | List tables + create/join forms        |
| POST   | `/lobby/tables`                    | Create table + auto-join               |
| POST   | `/lobby/join`                      | Join by 6-char code                    |
| POST   | `/lobby/tables/{table:code}/join`  | Join a listed table                    |
| GET    | `/table/{table:code}`              | Game table view (polled)               |
| POST   | `/table/{table:code}/start`        | Deal next hand                         |
| POST   | `/table/{table:code}/action`       | Play action (chaal/pack/show/…)        |
| POST   | `/table/{table:code}/leave`        | Cash out & leave                       |
| GET    | `/wallet`                          | Balance + transaction history          |
| POST   | `/wallet/deposit`                  | Test-money deposit                     |

---

## 9. Vue Pages (`resources/js/pages/`)

| Page                 | Purpose                                                                 |
|----------------------|-------------------------------------------------------------------------|
| `Lobby/Index.vue`    | Table list, create form, join-by-code                                   |
| `Game/Show.vue`      | Oval-felt UI: seats around the table, pot, action buttons, sideshow modal, 2.5 s polling |
| `Wallet/Index.vue`   | Balance, test-deposit, ledger                                           |

Sidebar updated in `resources/js/components/AppSidebar.vue` to include
**Lobby** and **Wallet** entries alongside Dashboard.

---

## 10. Tests

```bash
php artisan test
```

All **52 tests / 152 assertions** pass.

New test files:

- `tests/Unit/HandEvaluatorTest.php` — every category, A-2-3, K-A-2 rejection, suit tiebreakers
- `tests/Unit/HandComparatorTest.php` — trio>pure, pair tiebreak by kicker, identical → tie
- `tests/Unit/BettingRulesTest.php` — blind & seen ranges, `nextStake` semantics

---

## 11. Verified End-to-End (Smoke Test)

```text
Hand started: id=1, pot=40.00, stake=10.00       # 4 × boot 10
seat 1 (Aarav): [2C, JC, KC]
seat 2 (Priya): [7S, JS, QS]                     # pure sequence!
seat 3 (Vikram): [AC, KH, 3C]
seat 4 (Meera): [10S, 6C, 10C]                   # pair

Priya blind chaal 10 → pot 50, stake 10, turn 3
Vikram packs            → turn 4
Meera sees + chaal 20   → pot 70, stake 10, turn 1
Aarav packs             → 2 active players left
Priya show              → status=completed, winner=Priya
```

---

## 12. Project Tree (new/changed files)

```
app/
├── Http/Controllers/
│   ├── LobbyController.php          (NEW)
│   ├── GameController.php           (NEW)
│   └── WalletController.php         (NEW)
├── Models/
│   ├── User.php                     (extended: wallet relationships)
│   ├── PokerTable.php               (NEW)
│   ├── TablePlayer.php              (NEW)
│   ├── Hand.php                     (NEW)
│   ├── HandPlayer.php               (NEW)
│   ├── Bet.php                      (NEW)
│   └── Transaction.php              (NEW)
└── Services/
    ├── Cards/
    │   ├── Card.php                 (NEW)
    │   ├── Deck.php                 (NEW)
    │   ├── HandEvaluator.php        (NEW)
    │   └── HandComparator.php       (NEW)
    └── Game/
        ├── BettingRules.php         (NEW)
        ├── GameService.php          (NEW)
        └── TableService.php         (NEW)

database/
├── migrations/
│   ├── 2026_05_27_000001_add_game_columns_to_users_table.php
│   ├── 2026_05_27_000002_create_poker_tables_table.php
│   ├── 2026_05_27_000003_create_table_players_table.php
│   ├── 2026_05_27_000004_create_hands_table.php
│   ├── 2026_05_27_000005_create_hand_players_table.php
│   ├── 2026_05_27_000006_create_bets_table.php
│   └── 2026_05_27_000007_create_transactions_table.php
└── seeders/
    └── DatabaseSeeder.php           (4 demo users + DEMO01 table)

resources/js/
├── components/AppSidebar.vue        (added Lobby/Wallet nav)
└── pages/
    ├── Lobby/Index.vue              (NEW)
    ├── Game/Show.vue                (NEW – the oval-felt UI)
    └── Wallet/Index.vue             (NEW)

routes/web.php                       (added lobby/table/wallet routes)
tests/
├── Pest.php                         (Unit suite bound to TestCase)
└── Unit/
    ├── HandEvaluatorTest.php        (NEW)
    ├── HandComparatorTest.php       (NEW)
    └── BettingRulesTest.php         (NEW)
```

---

## 13. Out of Scope / Known Limitations

- **No WebSockets.** Polling every 2.5 s is functional but not push-based.
  To upgrade: install Reverb, register events
  (`HandStarted`, `HandUpdated`, `SideshowRequested`, `HandEnded`),
  broadcast on `presence-table.{id}` and `private-user.{id}`, then swap
  `router.reload` for Echo listeners.
- **No variants** (AK47, Muflis, Joker, Best-of-4, community card) — Phase 2.
- **No auto-pack timer** for idle players — `php artisan schedule:work` hook
  not added.
- **`npm run build`** requires Node ≥ 20.19; this environment has 18.19.1.
  `npm run dev` (Vite dev server) works for development.
- Play-money only. No real-currency integration.

---

## 14. Interaction Transcript Summary

The build was driven by a single prompt: *"implement [the Teen Patti spec
in `teen-patti-laravel-prompt.md`]"*. Work executed in this order:

1. **Inspected starter kit** — discovered Laravel 13 + Inertia + Vue 3
   (not Livewire as the spec assumed). Chose to swap the frontend layer to
   Inertia + Vue since the starter is already wired for it; everything else
   in the spec maps 1:1.
2. **7 migrations** for users (extension), `poker_tables`, `table_players`,
   `hands`, `hand_players`, `bets`, `transactions`.
3. **7 models** with relationships (`belongsToMany`, `hasMany`, `belongsTo`).
4. **Card engine** with Pest tests covering every rank category, A-2-3, the
   K-A-2 rejection, and suit tiebreakers.
5. **Betting rules + game service** — server-authoritative, transactional,
   `lockForUpdate` on the hand row.
6. **3 controllers** + routes, with cards filtered per-viewer.
7. **3 Vue pages** + sidebar nav updates. Felt-styled oval table with seats
   placed via `cos/sin` around the perimeter.
8. **Seeder** with 4 demo users and a pre-seated `DEMO01` table.
9. **Ran migrations, seeders, tests** — all 52 pass.
10. **Smoke-tested** an end-to-end hand via `tinker`: 4 players boot →
    blind chaal → pack → see+chaal → pack → show → winner credited.
11. **`vue-tsc` type check** clean.

Total result: 22 new tests + 30 pre-existing = **52 / 52 passing,
152 assertions**.
