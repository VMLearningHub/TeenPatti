<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lobby', href: '/lobby' }],
    },
});

interface PokerTable {
    id: number;
    code: string;
    name: string;
    boot_amount: string;
    min_bet: string;
    max_bet: string;
    max_players: number;
    status: string;
    table_players_count: number;
}

defineProps<{
    tables: PokerTable[];
    wallet: string;
}>();

const createForm = useForm({
    name: '',
    boot_amount: 10,
    min_bet: 10,
    max_bet: 1000,
    max_players: 6,
    buy_in: 500,
});

const joinForm = useForm({ code: '' });

function submitCreate() {
    createForm.post('/lobby/tables');
}

function submitJoin() {
    joinForm.post('/lobby/join');
}

function joinTable(code: string) {
    router.post(`/lobby/tables/${code}/join`);
}
</script>

<template>
    <Head title="Lobby" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Teen Patti Lobby</h1>
            <div class="text-sm text-muted-foreground">
                Wallet: <span class="font-semibold text-foreground">₹{{ wallet }}</span>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Create Table</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submitCreate">
                        <Input v-model="createForm.name" placeholder="Table name" />
                        <div class="grid grid-cols-2 gap-3">
                            <Input v-model="createForm.boot_amount" type="number" placeholder="Boot" min="1" />
                            <Input v-model="createForm.max_players" type="number" placeholder="Max players" min="2" max="10" />
                            <Input v-model="createForm.min_bet" type="number" placeholder="Min bet" min="1" />
                            <Input v-model="createForm.max_bet" type="number" placeholder="Max bet" min="1" />
                            <Input v-model="createForm.buy_in" type="number" placeholder="Buy-in" min="1" class="col-span-2" />
                        </div>
                        <Button type="submit" :disabled="createForm.processing" class="w-full">Create &amp; Join</Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Join by Code</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="flex gap-2" @submit.prevent="submitJoin">
                        <Input v-model="joinForm.code" placeholder="ABC123" maxlength="6" class="uppercase" />
                        <Button type="submit" :disabled="joinForm.processing">Join</Button>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Open Tables</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="tables.length === 0" class="py-8 text-center text-muted-foreground">
                    No tables yet. Create one above.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="t in tables"
                        :key="t.id"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="font-semibold">{{ t.name }}</div>
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
                        </div>
                        <div class="mt-2 text-sm text-muted-foreground">
                            Code: <span class="font-mono">{{ t.code }}</span> •
                            Boot ₹{{ t.boot_amount }} •
                            {{ t.table_players_count }}/{{ t.max_players }} seats
                        </div>
                        <div class="mt-3 flex gap-2">
                            <Button size="sm" @click="joinTable(t.code)">Join</Button>
                            <Button size="sm" variant="outline" as-child>
                                <a :href="`/table/${t.code}`">View</a>
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
