<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-studio-950 px-4 py-10"
    >
        <div
            class="pointer-events-none fixed inset-x-0 top-0 h-96 bg-[radial-gradient(50%_100%_at_50%_0%,rgba(232,178,88,0.12),transparent)]"
        />

        <Link href="/" class="relative mb-6 flex items-center gap-2.5 text-white">
            <ApplicationLogo class="h-8 w-8 text-ember-500" />
            <span class="text-lg font-semibold tracking-tight">Lumina</span>
        </Link>

        <div class="panel relative w-full max-w-md p-6 sm:p-8">
            <slot />
        </div>

        <p class="relative mt-6 text-xs text-zinc-600">
            AI image generation, priced transparently.
        </p>
    </div>
</template>
