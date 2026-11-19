<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { formatDate, sizeLabel, timeAgo } from '@/lib/format';
import { formatNano } from '@/lib/money';
import type { Generation, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    generations: Paginated<Generation>;
    filter: { status: string | null };
}>();

const filters = [
    { value: null, label: 'All' },
    { value: 'queued', label: 'Queued' },
    { value: 'processing', label: 'Processing' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'failed', label: 'Failed' },
];

const hasActive = computed(() =>
    props.generations.data.some(
        (generation) =>
            generation.status === 'queued' || generation.status === 'processing',
    ),
);

let poller: number | undefined;

onMounted(() => {
    poller = window.setInterval(() => {
        if (hasActive.value) {
            router.reload({
                only: ['generations'],
            });
        }
    }, 5000);
});

onUnmounted(() => {
    if (poller) {
        window.clearInterval(poller);
    }
});

const costLabel = (generation: Generation): string => {
    if (generation.status === 'failed') {
        return 'No charge';
    }

    if (generation.chargedNano !== null) {
        return `Charged ${formatNano(generation.chargedNano)}`;
    }

    return `Est. ${formatNano(generation.estimatedChargeNano)}`;
};
</script>

<template>
    <Head title="History" />

    <AuthenticatedLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-ember-500">
                    Studio
                </p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">
                    Generation history
                </h1>
                <p class="mt-1.5 text-sm text-zinc-500">
                    Every job and its live status. Active jobs refresh on their
                    own.
                </p>
            </div>

            <Link :href="route('generations.create')" class="btn-primary">
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 5v14m-7-7h14"
                    />
                </svg>
                New generation
            </Link>
        </div>

        <div class="mb-6 flex flex-wrap gap-1.5">
            <Link
                v-for="option in filters"
                :key="option.label"
                :href="
                    route(
                        'generations.index',
                        option.value ? { status: option.value } : {},
                    )
                "
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm transition"
                :class="
                    filter.status === option.value
                        ? 'bg-white/[0.08] font-medium text-white'
                        : 'text-zinc-500 hover:text-zinc-200'
                "
            >
                {{ option.label }}
            </Link>
        </div>

        <div
            v-if="generations.data.length === 0"
            class="panel flex flex-col items-center gap-4 px-6 py-20 text-center"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-ember-500/10 text-ember-400"
            >
                <svg
                    class="h-6 w-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.6"
                        d="M4 16.5 7.5 9l3 6 2-3.5 3.5 5M4 5h16v14H4z"
                    />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-white">
                    {{ filter.status ? 'Nothing with that status yet' : 'Your studio is empty' }}
                </p>
                <p class="mt-1 text-sm text-zinc-500">
                    {{
                        filter.status
                            ? 'Try another filter, or start a new generation.'
                            : 'Create your first image to see it appear here with live status.'
                    }}
                </p>
            </div>
            <Link :href="route('generations.create')" class="btn-primary">
                Create a generation
            </Link>
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="generation in generations.data"
                :key="generation.id"
                class="panel group overflow-hidden"
            >
                <div class="relative aspect-[4/3] overflow-hidden bg-studio-800">
                    <img
                        v-if="
                            generation.status === 'succeeded' &&
                            generation.imageUrl &&
                            !generation.imageExpired
                        "
                        :src="generation.imageUrl"
                        :alt="generation.prompt"
                        loading="lazy"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]"
                    />

                    <div
                        v-else-if="
                            generation.status === 'succeeded' &&
                            generation.imageExpired
                        "
                        class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center"
                    >
                        <svg
                            class="h-7 w-7 text-zinc-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M12 8v4m0 4h.01M10.3 3.9 2.5 17.4A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-2.6L13.7 3.9a2 2 0 0 0-3.4 0Z"
                            />
                        </svg>
                        <p class="text-xs text-zinc-500">
                            The provider's image link has expired.
                        </p>
                    </div>

                    <div
                        v-else-if="generation.status === 'failed'"
                        class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center"
                    >
                        <svg
                            class="h-7 w-7 text-rose-400/70"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M6 18 18 6M6 6l12 12"
                            />
                        </svg>
                        <p class="text-xs text-rose-300/80">
                            Generation failed — nothing was charged.
                        </p>
                    </div>

                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center gap-3"
                    >
                        <div class="relative flex h-10 w-10 items-center justify-center">
                            <span
                                class="absolute inset-0 animate-ping rounded-full bg-ember-500/20"
                            />
                            <span
                                class="relative h-3 w-3 rounded-full bg-ember-400"
                            />
                        </div>
                        <p class="text-xs uppercase tracking-widest text-zinc-500">
                            {{
                                generation.status === 'queued'
                                    ? 'Queued at provider'
                                    : 'Rendering'
                            }}
                        </p>
                    </div>

                    <div class="absolute left-3 top-3">
                        <StatusBadge
                            :status="generation.status"
                            :label="generation.statusLabel"
                        />
                    </div>
                </div>

                <div class="p-5">
                    <p class="line-clamp-3 text-sm leading-relaxed text-zinc-300">
                        {{ generation.prompt }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px] text-zinc-500">
                        <span class="rounded-md border border-white/[0.08] px-2 py-0.5">
                            {{ sizeLabel(generation.size) }}
                        </span>
                        <span class="rounded-md border border-white/[0.08] px-2 py-0.5 capitalize">
                            {{ generation.quality }}
                        </span>
                        <span
                            v-if="generation.referenceImages.length"
                            class="rounded-md border border-white/[0.08] px-2 py-0.5"
                        >
                            {{ generation.referenceImages.length }}
                            reference{{ generation.referenceImages.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <p
                        v-if="generation.errorMessage"
                        class="mt-3 line-clamp-2 text-xs text-rose-300/80"
                    >
                        {{ generation.errorMessage }}
                    </p>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-white/[0.06] pt-3"
                    >
                        <div>
                            <p class="text-sm font-medium text-white">
                                {{ costLabel(generation) }}
                            </p>
                            <p
                                v-if="generation.chargedNano !== null"
                                class="text-[11px] text-zinc-600"
                            >
                                Estimated {{ formatNano(generation.estimatedChargeNano) }}
                            </p>
                            <p class="text-[11px] text-zinc-600">
                                {{ timeAgo(generation.createdAt) }}
                                <span v-if="generation.completedAt">
                                    · finished {{ formatDate(generation.completedAt) }}
                                </span>
                            </p>
                        </div>

                        <a
                            v-if="
                                generation.status === 'succeeded' &&
                                generation.imageUrl &&
                                !generation.imageExpired
                            "
                            :href="generation.imageUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg border border-white/10 p-2 text-zinc-400 transition hover:border-white/20 hover:text-white"
                            title="Open full image"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M14 5h5v5M19 5l-7 7M9 5H5v14h14v-4"
                                />
                            </svg>
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="generations.data.length" class="mt-8">
            <Pagination :paginated="generations" />
        </div>
    </AuthenticatedLayout>
</template>
