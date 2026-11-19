<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { formatNano } from '@/lib/money';
import type { AdminReport, ReportDay } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    report: AdminReport;
    filter: { from: string; to: string };
}>();

const filters = useForm({
    from: props.filter.from,
    to: props.filter.to,
});

const apply = () => {
    filters.get(route('admin.reports.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const totals = computed(() => props.report.totals);

const dayLabel = (date: string): string =>
    new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));

const reconciledRows = computed(
    () =>
        props.report.days.filter(
            (day) => day.providerUsageNano !== null && day.generations > 0,
        ).length,
);

const rowTone = (day: ReportDay): string => {
    if (day.providerUsageNano === null || day.generations === 0) {
        return 'text-zinc-600';
    }

    return day.reconciled ? 'text-emerald-300' : 'text-amber-300';
};
</script>

<template>
    <Head title="Admin report" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-ember-500">
                    Admin
                </p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">
                    Revenue & provider cost
                </h1>
                <p class="mt-1.5 text-sm text-zinc-500">
                    Credit spend, Pixelforge cost and profit by UTC completion
                    day, reconciled against <code class="text-zinc-400">GET /v1/usage</code>.
                </p>
            </div>

            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="apply"
            >
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-zinc-500">
                        From
                    </label>
                    <input
                        v-model="filters.from"
                        type="date"
                        class="field py-2"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-zinc-500">
                        To
                    </label>
                    <input
                        v-model="filters.to"
                        type="date"
                        class="field py-2"
                    />
                </div>
                <button
                    type="submit"
                    class="btn-ghost py-2"
                    :disabled="filters.processing"
                >
                    Apply
                </button>
            </form>
        </div>

        <InputError class="mb-4" :message="filters.errors.from ?? filters.errors.to" />

        <div
            v-if="report.usageError"
            class="mb-6 rounded-xl border border-rose-500/25 bg-rose-500/[0.07] px-4 py-3 text-sm text-rose-200"
        >
            Could not load the Pixelforge usage report: {{ report.usageError }}.
            The figures below come from our own settled generations.
        </div>

        <div
            v-else-if="totals.providerUsageNano !== null"
            class="mb-6 rounded-xl border px-4 py-3 text-sm"
            :class="
                totals.reconciled
                    ? 'border-emerald-500/25 bg-emerald-500/[0.07] text-emerald-200'
                    : 'border-amber-500/25 bg-amber-500/[0.07] text-amber-200'
            "
        >
            <template v-if="totals.reconciled">
                Provider cost matches Pixelforge usage for this period
                ({{ totals.providerUsageGenerations ?? 0 }} billed generations).
            </template>
            <template v-else>
                Provider cost differs from Pixelforge usage by
                {{ formatNano(totals.differenceNano ?? 0) }}. Usage is scoped to
                this API key, so another environment sharing the key can cause a
                difference.
            </template>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-zinc-500">
                    Charged to users
                </p>
                <p class="mt-2 text-2xl font-semibold text-white">
                    {{ formatNano(totals.chargedNano) }}
                </p>
                <p class="mt-1 text-xs text-zinc-600">
                    {{ totals.generations.toLocaleString() }} settled generations
                </p>
            </div>
            <div class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-zinc-500">
                    Provider cost
                </p>
                <p class="mt-2 text-2xl font-semibold text-white">
                    {{ formatNano(totals.providerCostNano) }}
                </p>
                <p class="mt-1 text-xs text-zinc-600">
                    From exact usage returned by Pixelforge
                </p>
            </div>
            <div class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-zinc-500">
                    Profit
                </p>
                <p class="mt-2 text-2xl font-semibold text-emerald-300">
                    {{ formatNano(totals.profitNano) }}
                </p>
                <p class="mt-1 text-xs text-zinc-600">
                    {{ report.markupPercent }}% markup
                </p>
            </div>
            <div class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-zinc-500">
                    Pixelforge usage
                </p>
                <p class="mt-2 text-2xl font-semibold text-white">
                    {{
                        totals.providerUsageNano === null
                            ? '—'
                            : formatNano(totals.providerUsageNano)
                    }}
                </p>
                <p class="mt-1 text-xs text-zinc-600">
                    {{ totals.providerUsageGenerations ?? '—' }} billed generations
                </p>
            </div>
        </div>

        <section class="mt-10">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white">By day</h2>
                <p class="text-xs text-zinc-600">
                    {{ reconciledRows }} of {{ report.days.length }} days have usage data
                </p>
            </div>

            <div class="panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/[0.06]">
                        <thead class="bg-white/[0.02]">
                            <tr>
                                <th class="table-head">Day (UTC)</th>
                                <th class="table-head text-right">Gens</th>
                                <th class="table-head text-right">Charged</th>
                                <th class="table-head text-right">Provider</th>
                                <th class="table-head text-right">Profit</th>
                                <th class="table-head text-right">Usage</th>
                                <th class="table-head text-right">Delta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr
                                v-for="day in report.days"
                                :key="day.date"
                                class="hover:bg-white/[0.02]"
                            >
                                <td class="table-cell text-zinc-200">
                                    {{ dayLabel(day.date) }}
                                </td>
                                <td class="table-cell text-right">
                                    {{ day.generations }}
                                </td>
                                <td class="table-cell text-right">
                                    {{ formatNano(day.chargedNano) }}
                                </td>
                                <td class="table-cell text-right">
                                    {{ formatNano(day.providerCostNano) }}
                                </td>
                                <td
                                    class="table-cell text-right"
                                    :class="day.profitNano >= 0 ? 'text-emerald-300' : 'text-rose-300'"
                                >
                                    {{ formatNano(day.profitNano) }}
                                </td>
                                <td class="table-cell text-right">
                                    {{
                                        day.providerUsageNano === null
                                            ? '—'
                                            : formatNano(day.providerUsageNano)
                                    }}
                                </td>
                                <td
                                    class="table-cell text-right font-medium"
                                    :class="rowTone(day)"
                                >
                                    <template v-if="day.providerUsageNano === null">
                                        —
                                    </template>
                                    <template v-else-if="day.reconciled">
                                        Matched
                                    </template>
                                    <template v-else>
                                        {{ formatNano(day.differenceNano ?? 0) }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="mb-3 text-sm font-semibold text-white">By user</h2>

            <div
                v-if="report.users.length === 0"
                class="panel px-6 py-10 text-center text-sm text-zinc-500"
            >
                No settled generations in this period.
            </div>

            <div v-else class="panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/[0.06]">
                        <thead class="bg-white/[0.02]">
                            <tr>
                                <th class="table-head">User</th>
                                <th class="table-head text-right">Gens</th>
                                <th class="table-head text-right">Charged</th>
                                <th class="table-head text-right">Provider</th>
                                <th class="table-head text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.04]">
                            <tr
                                v-for="user in report.users"
                                :key="user.userId"
                                class="hover:bg-white/[0.02]"
                            >
                                <td class="table-cell">
                                    <span class="text-zinc-200">{{ user.name }}</span>
                                    <span class="ml-2 text-xs text-zinc-600">
                                        {{ user.email }}
                                    </span>
                                </td>
                                <td class="table-cell text-right">
                                    {{ user.generations }}
                                </td>
                                <td class="table-cell text-right">
                                    {{ formatNano(user.chargedNano) }}
                                </td>
                                <td class="table-cell text-right">
                                    {{ formatNano(user.providerCostNano) }}
                                </td>
                                <td class="table-cell text-right text-emerald-300">
                                    {{
                                        formatNano(
                                            user.chargedNano - user.providerCostNano,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
