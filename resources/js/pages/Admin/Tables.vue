<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'All Tables', href: '/admin/tables' }],
    },
});

interface TableSeat {
    id: number;
    seat_position: number;
    chips_on_table: string;
    is_active: boolean;
    user: { id: number; name: string; role: 'user' | 'admin' } | null;
}

interface AdminTable {
    id: number;
    code: string;
    name: string;
    boot_amount: string;
    min_bet: string;
    max_bet: string;
    max_players: number;
    status: string;
    table_players_count: number;
    table_players: TableSeat[];
    created_at: string;
}

defineProps<{
    tables: AdminTable[];
}>();

function deleteTable(table: AdminTable) {
    if (!confirm(`Delete table "${table.name}"? This removes all its players and hands and cannot be undone.`)) {
        return;
    }
    router.delete(`/admin/tables/${table.code}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="All Tables" />

    <div class="flex flex-col gap-6 p-4">
        <Card>
            <CardHeader>
                <CardTitle>All Lobby Tables</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="tables.length === 0" class="py-8 text-center text-muted-foreground">
                    No tables created yet.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2">Name</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th class="text-right">Boot</th>
                                <th class="text-right">Min / Max</th>
                                <th class="text-right">Seats</th>
                                <th>Players</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in tables" :key="t.id" class="border-t">
                                <td class="py-3 font-medium">{{ t.name }}</td>
                                <td class="font-mono">{{ t.code }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="{
                                            'bg-emerald-500/15 text-emerald-600': t.status === 'waiting',
                                            'bg-amber-500/15 text-amber-600': t.status === 'in_progress',
                                            'bg-zinc-500/15 text-zinc-500': t.status === 'ended',
                                        }"
                                    >
                                        {{ t.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums">₹{{ t.boot_amount }}</td>
                                <td class="text-right tabular-nums">₹{{ t.min_bet }} / ₹{{ t.max_bet }}</td>
                                <td class="text-right tabular-nums">{{ t.table_players_count }}/{{ t.max_players }}</td>
                                <td>
                                    <div v-if="t.table_players.length" class="flex flex-wrap gap-1.5 py-1">
                                        <span
                                            v-for="seat in t.table_players"
                                            :key="seat.id"
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs"
                                            :class="seat.is_active ? '' : 'opacity-50'"
                                        >
                                            {{ seat.user?.name ?? 'Unknown' }}
                                            <span
                                                v-if="seat.user?.role === 'admin'"
                                                class="rounded-full bg-amber-500/20 px-1 text-[10px] font-semibold text-amber-600"
                                            >
                                                admin
                                            </span>
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" as-child>
                                            <a :href="`/table/${t.code}`">View</a>
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="deleteTable(t)">Delete</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
