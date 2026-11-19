<script setup lang="ts">
import type { GenerationStatus } from '@/types';

defineProps<{
    status: GenerationStatus;
    label?: string;
}>();

const styles: Record<GenerationStatus, string> = {
    queued: 'bg-zinc-500/10 text-zinc-300 ring-zinc-400/20',
    processing: 'bg-ember-500/10 text-ember-300 ring-ember-400/30',
    succeeded: 'bg-emerald-500/10 text-emerald-300 ring-emerald-400/30',
    failed: 'bg-rose-500/10 text-rose-300 ring-rose-400/30',
};

const dots: Record<GenerationStatus, string> = {
    queued: 'bg-zinc-400',
    processing: 'bg-ember-400 animate-pulse',
    succeeded: 'bg-emerald-400',
    failed: 'bg-rose-400',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset"
        :class="styles[status]"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="dots[status]" />
        {{ label ?? status }}
    </span>
</template>
