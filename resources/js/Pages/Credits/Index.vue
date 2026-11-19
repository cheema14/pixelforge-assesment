<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import InputError from '@/Components/InputError.vue';
import { formatDate } from '@/lib/format';
import { formatNano } from '@/lib/money';
import type { CreditTransaction, Paginated } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    balanceNano: number;
    transactions: Paginated<CreditTransaction>;
    presets: number[];
    maxPurchase: number;
    markupPercent: number;
}>();

const form = useForm({ amount: '10' });

const choose = (amount: number) => {
    form.amount = String(amount);
};

const purchase = () => {
    form.post(route('credits.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('amount'),
    });
};
</script>

<template>
    <Head title="Credits" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <p class="text-xs uppercase tracking-widest text-ember-500">
                Billing
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">
                Credits
            </h1>
            <p class="mt-1.5 text-sm text-zinc-500">
                Generation costs are reserved up front and settled at the
                provider's exact usage.
            </p>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[1fr_380px]">
            <div class="panel p-6 sm:p-8">
                <p class="text-xs uppercase tracking-widest text-zinc-500">
                    Available balance
                </p>
                <p class="mt-3 text-4xl font-semibold tracking-tight text-white">
                    {{ formatNano(balanceNano) }}
                </p>
                <p class="mt-2 text-sm text-zinc-500">
                    A failed generation is refunded automatically. Succeeded
                    generations are charged at the real token cost plus
                    {{ markupPercent }}% markup.
                </p>

                <div
                    class="mt-6 grid gap-3 border-t border-white/[0.06] pt-6 sm:grid-cols-3"
                >
                    <div>
                        <p class="text-xs text-zinc-500">Reservations</p>
                        <p class="mt-1 text-sm text-zinc-300">
                            Held from submission until completion
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500">Rounding</p>
                        <p class="mt-1 text-sm text-zinc-300">
                            Stored to the ninth decimal of a dollar
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-500">Demo purchases</p>
                        <p class="mt-1 text-sm text-zinc-300">
                            No payment provider is connected
                        </p>
                    </div>
                </div>
            </div>

            <div class="panel p-6">
                <h2 class="text-sm font-semibold text-white">Buy credits</h2>
                <p class="mt-1 text-xs text-zinc-600">
                    This is a demo purchase — it credits the ledger
                    immediately.
                </p>

                <form class="mt-5" @submit.prevent="purchase">
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-sm font-medium transition"
                            :class="
                                form.amount === String(preset)
                                    ? 'border-ember-500/60 bg-ember-500/[0.07] text-ember-200'
                                    : 'border-white/10 text-zinc-300 hover:border-white/20'
                            "
                            @click="choose(preset)"
                        >
                            ${{ preset }}
                        </button>
                    </div>

                    <div class="mt-4">
                        <label
                            for="amount"
                            class="mb-1.5 block text-xs font-medium text-zinc-400"
                        >
                            Custom amount (USD)
                        </label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-zinc-500"
                            >
                                $
                            </span>
                            <input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                :max="maxPurchase"
                                step="0.01"
                                class="field pl-7"
                            />
                        </div>
                        <p class="mt-1.5 text-[11px] text-zinc-600">
                            Between $1 and ${{ maxPurchase }} per purchase.
                        </p>
                        <InputError class="mt-2" :message="form.errors.amount" />
                    </div>

                    <button
                        type="submit"
                        class="btn-primary mt-5 w-full py-3"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Adding…' : 'Buy credits' }}
                    </button>
                </form>
            </div>
        </div>

        <section class="mt-10">
            <h2 class="mb-4 text-sm font-semibold text-white">Ledger</h2>

            <div
                v-if="transactions.data.length === 0"
                class="panel px-6 py-12 text-center text-sm text-zinc-500"
            >
                No transactions yet.
            </div>

            <div v-else class="panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/[0.06]">
                        <thead class="bg-white/[0.02]">
                            <tr>
                                <th class="table-head">Type</th>
                                <th class="table-head">Description</th>
                                <th class="table-head">Date</th>
                                <th class="table-head text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr
                                v-for="transaction in transactions.data"
                                :key="transaction.id"
                            >
                                <td class="table-cell">
                                    <span
                                        class="rounded-md border border-white/[0.08] px-2 py-0.5 text-[11px] text-zinc-400"
                                    >
                                        {{ transaction.typeLabel }}
                                    </span>
                                </td>
                                <td class="table-cell">
                                    {{ transaction.description }}
                                </td>
                                <td class="table-cell text-zinc-500">
                                    {{ formatDate(transaction.createdAt) }}
                                </td>
                                <td
                                    class="table-cell text-right font-medium"
                                    :class="
                                        transaction.amountNano >= 0
                                            ? 'text-emerald-300'
                                            : 'text-zinc-300'
                                    "
                                >
                                    {{
                                        transaction.amountNano >= 0 ? '+' : ''
                                    }}{{ formatNano(transaction.amountNano) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="transactions.data.length" class="mt-6">
                <Pagination :paginated="transactions" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
