<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="AI image generation" />

    <div class="relative min-h-screen overflow-hidden bg-studio-950">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[560px] bg-[radial-gradient(60%_100%_at_50%_0%,rgba(232,178,88,0.16),transparent)]"
        />
        <div
            class="pointer-events-none absolute -left-40 top-96 h-96 w-96 rounded-full bg-ember-500/5 blur-3xl"
        />

        <header class="relative mx-auto max-w-6xl px-6 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-white">
                    <ApplicationLogo class="h-7 w-7 text-ember-500" />
                    <span class="text-lg font-semibold tracking-tight">Lumina</span>
                </div>
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="nav-link"
                    >
                        Sign in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="btn-primary"
                    >
                        Start creating
                    </Link>
                </nav>
            </div>
        </header>

        <main class="relative mx-auto max-w-6xl px-6 pb-24">
            <section class="grid items-center gap-14 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:py-24">
                <div>
                    <p
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-ember-500/25 bg-ember-500/[0.07] px-3.5 py-1.5 text-xs font-medium text-ember-300"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-ember-400" />
                        Powered by Pixelforge
                    </p>

                    <h1
                        class="text-4xl font-semibold leading-[1.08] tracking-tight text-white sm:text-6xl"
                    >
                        Generate images.
                        <span class="text-ember-400">Know the price</span>
                        before you press go.
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-zinc-400">
                        Lumina turns a prompt into a finished image and shows a
                        live cost estimate while you build it. We charge
                        Pixelforge's exact token cost plus a 25% markup — no
                        subscriptions, no credits burned on failures.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="btn-primary px-6 py-3"
                        >
                            Create your studio
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
                                    d="M5 12h14m-6-6 6 6-6 6"
                                />
                            </svg>
                        </Link>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="btn-ghost px-6 py-3"
                        >
                            I already have an account
                        </Link>
                    </div>

                    <p class="mt-6 text-sm text-zinc-600">
                        New accounts start with a $5 welcome balance.
                    </p>
                </div>

                <div class="relative">
                    <div
                        class="absolute -inset-6 rounded-[2rem] bg-ember-500/5 blur-2xl"
                    />

                    <div class="panel relative overflow-hidden p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-widest text-zinc-500">
                                Cost preview
                            </span>
                            <span
                                class="rounded-full bg-ember-500/10 px-2.5 py-1 text-[11px] font-semibold text-ember-300 ring-1 ring-inset ring-ember-400/30"
                            >
                                25% markup
                            </span>
                        </div>

                        <p class="mt-5 text-sm text-zinc-400">
                            A cinematic portrait of a lighthouse at dusk, warm
                            fog, 35mm film grain
                        </p>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="chip in ['1024 × 1024', 'High quality', '2 references']"
                                :key="chip"
                                class="rounded-lg border border-white/10 px-2.5 py-1 text-xs text-zinc-400"
                            >
                                {{ chip }}
                            </span>
                        </div>

                        <div class="mt-6 rounded-xl border border-white/[0.06] bg-studio-950/60 p-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm text-zinc-400">
                                    Estimated charge
                                </span>
                                <span class="text-2xl font-semibold text-white">
                                    $0.044874
                                </span>
                            </div>
                            <div class="mt-1 flex items-baseline justify-between text-xs text-zinc-500">
                                <span>Worst case while reasoning varies</span>
                                <span>$0.063259</span>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2 text-xs text-zinc-500">
                            <div class="flex items-center justify-between">
                                <span>Output tokens</span>
                                <span class="text-zinc-300">4,300</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Reference image tokens</span>
                                <span class="text-zinc-300">1,530</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Reasoning tokens (midpoint)</span>
                                <span class="text-zinc-300">4,500</span>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-2 rounded-xl bg-emerald-500/[0.07] px-3 py-2.5 text-xs text-emerald-300 ring-1 ring-inset ring-emerald-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            Settled at the provider's exact cost on completion
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="feature in [
                        {
                            title: 'Transparent pricing',
                            body: 'Estimates use Pixelforge’s published rates. The final charge is calculated from the exact token usage returned with each generation.',
                        },
                        {
                            title: 'Live lifecycle',
                            body: 'Signed webhooks update every generation in real time, with a backup poller so a missed delivery never leaves a job stuck.',
                        },
                        {
                            title: 'Failures cost nothing',
                            body: 'If Pixelforge rejects or fails a generation, the reserved amount is refunded automatically.',
                        },
                    ]"
                    :key="feature.title"
                    class="panel p-6"
                >
                    <h2 class="text-sm font-semibold text-white">
                        {{ feature.title }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-400">
                        {{ feature.body }}
                    </p>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-white/[0.06]">
            <div
                class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-6 text-xs text-zinc-600 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>Lumina — a take-home build for the Pixelforge API.</span>
                <span>Laravel · Inertia · Vue · Tailwind</span>
            </div>
        </footer>
    </div>
</template>
