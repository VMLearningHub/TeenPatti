<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: '/admin/users' }],
    },
});

interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin';
    is_active: boolean;
    wallet_balance: string;
    created_at: string;
}

defineProps<{
    users: AdminUser[];
}>();

function toggleActive(user: AdminUser) {
    router.patch(`/admin/users/${user.id}/toggle`, {}, { preserveScroll: true });
}

// Inline wallet editing.
const editingId = ref<number | null>(null);
const editValue = ref<number>(0);
const saving = ref(false);

function startEdit(user: AdminUser) {
    editingId.value = user.id;
    editValue.value = Number(user.wallet_balance);
}

function cancelEdit() {
    editingId.value = null;
}

function saveWallet(user: AdminUser) {
    router.patch(
        `/admin/users/${user.id}/wallet`,
        { wallet_balance: editValue.value },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => {
                saving.value = false;
                editingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-6 p-4">
        <Card>
            <CardHeader>
                <CardTitle>Users</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2">Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th class="text-right">Wallet</th>
                                <th>Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id" class="border-t">
                                <td class="py-3 font-medium">{{ u.name }}</td>
                                <td class="text-muted-foreground">{{ u.email }}</td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs capitalize"
                                        :class="u.role === 'admin'
                                            ? 'bg-amber-500/15 text-amber-600'
                                            : 'bg-zinc-500/15 text-zinc-500'"
                                    >
                                        {{ u.role }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div v-if="editingId === u.id" class="flex items-center justify-end gap-2">
                                        <Input
                                            v-model.number="editValue"
                                            type="number"
                                            min="0"
                                            class="w-28 text-right"
                                            @keyup.enter="saveWallet(u)"
                                        />
                                        <Button size="sm" :disabled="saving" @click="saveWallet(u)">Save</Button>
                                        <Button size="sm" variant="outline" :disabled="saving" @click="cancelEdit">Cancel</Button>
                                    </div>
                                    <div v-else class="flex items-center justify-end gap-2">
                                        <span class="tabular-nums">₹{{ u.wallet_balance }}</span>
                                        <Button size="sm" variant="outline" @click="startEdit(u)">Edit</Button>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="u.is_active
                                            ? 'bg-emerald-500/15 text-emerald-600'
                                            : 'bg-rose-500/15 text-rose-600'"
                                    >
                                        {{ u.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <Button
                                        size="sm"
                                        :variant="u.is_active ? 'destructive' : 'default'"
                                        @click="toggleActive(u)"
                                    >
                                        {{ u.is_active ? 'Deactivate' : 'Activate' }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
