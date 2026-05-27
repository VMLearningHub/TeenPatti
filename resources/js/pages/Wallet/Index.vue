<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Wallet', href: '/wallet' }],
    },
});

interface Transaction {
    id: number;
    type: string;
    amount: string;
    balance_after: string;
    reference_type: string | null;
    reference_id: number | null;
    created_at: string;
}

defineProps<{
    wallet: string;
    transactions: Transaction[];
}>();

const depositForm = useForm({ amount: 500 });

function deposit() {
    depositForm.post('/wallet/deposit', { preserveScroll: true });
}

function typeBadge(type: string) {
    const map: Record<string, string> = {
        deposit: 'bg-emerald-500/15 text-emerald-600',
        withdraw: 'bg-rose-500/15 text-rose-600',
        bet: 'bg-rose-500/15 text-rose-600',
        win: 'bg-emerald-500/15 text-emerald-600',
        buy_in: 'bg-amber-500/15 text-amber-600',
        cash_out: 'bg-sky-500/15 text-sky-600',
    };
    return map[type] ?? 'bg-zinc-500/15 text-zinc-600';
}
</script>

<template>
    <Head title="Wallet" />

    <div class="flex flex-col gap-6 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Wallet</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="mb-4 text-3xl font-bold">₹{{ wallet }}</div>
                <form class="flex gap-2" @submit.prevent="deposit">
                    <Input v-model.number="depositForm.amount" type="number" min="1" class="w-32" />
                    <Button type="submit" :disabled="depositForm.processing">Deposit (test)</Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Transactions</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="transactions.length === 0" class="py-6 text-center text-muted-foreground">
                    No transactions yet.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2">When</th>
                                <th>Type</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transactions" :key="t.id" class="border-t">
                                <td class="py-2 text-muted-foreground">{{ new Date(t.created_at).toLocaleString() }}</td>
                                <td>
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="typeBadge(t.type)">
                                        {{ t.type }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums">
                                    <span :class="parseFloat(t.amount) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ parseFloat(t.amount) >= 0 ? '+' : '' }}{{ t.amount }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums">₹{{ t.balance_after }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
