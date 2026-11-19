<script setup lang="ts">
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    paginated: Paginated<unknown>;
}>();

const pageLinks = computed(() =>
    props.paginated.meta.links.filter(
        (link) => !/previous|next/i.test(link.label),
    ),
);

const previousUrl = computed(
    () => props.paginated.meta.links[0]?.url ?? null,
);

const nextUrl = computed(
    () => props.paginated.meta.links[props.paginated.meta.links.length - 1]?.url ?? null,
);
</script>

<template>
    <nav
        v-if="paginated.meta.last_page > 1"
        class="flex items-center justify-between gap-4 pt-2"
        aria-label="Pagination"
    >
        <Link
            :href="previousUrl ?? ''"
            class="btn-ghost px-3 py-2"
            :class="{ 'pointer-events-none opacity-30': !previousUrl }"
            preserve-scroll
        >
            Previous
        </Link>

        <div class="flex items-center gap-1">
            <template v-for="(link, index) in pageLinks" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="min-w-9 rounded-lg px-3 py-2 text-center text-sm transition"
                    :class="
                        link.active
                            ? 'bg-ember-500 font-semibold text-studio-950'
                            : 'text-zinc-400 hover:bg-white/5 hover:text-white'
                    "
                >
                    {{ link.label }}
                </Link>
                <span
                    v-else
                    class="min-w-9 px-3 py-2 text-center text-sm text-zinc-600"
                >
                    {{ link.label }}
                </span>
            </template>
        </div>

        <Link
            :href="nextUrl ?? ''"
            class="btn-ghost px-3 py-2"
            :class="{ 'pointer-events-none opacity-30': !nextUrl }"
            preserve-scroll
        >
            Next
        </Link>
    </nav>
</template>
