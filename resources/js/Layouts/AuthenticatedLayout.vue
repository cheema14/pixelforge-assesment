<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { formatNano } from '@/lib/money';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const showingMenu = ref(false);
const dismissedFlash = ref(false);

watch(
    () => [flash.value.success, flash.value.error],
    () => {
        dismissedFlash.value = false;
    },
);

const navItems = computed(() => {
    const items = [
        { label: 'Create', href: route('generations.create'), match: 'generations.create' },
        { label: 'History', href: route('generations.index'), match: 'generations.index' },
        { label: 'Credits', href: route('credits.index'), match: 'credits.index' },
    ];

    if (user.value?.is_admin) {
        items.push({
            label: 'Admin',
            href: route('admin.reports.index'),
            match: 'admin.reports.index',
        });
    }

    return items;
});
</script>

<template>
    <div class="min-h-screen bg-studio-950">
        <div
            class="pointer-events-none fixed inset-x-0 top-0 h-72 bg-[radial-gradient(60%_100%_at_50%_0%,rgba(232,178,88,0.10),transparent)]"
        />

        <header class="relative border-b border-white/[0.06]">
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-8">
                    <Link
                        :href="route('generations.index')"
                        class="flex items-center gap-2.5 text-white"
                    >
                        <ApplicationLogo class="h-6 w-6 text-ember-500" />
                        <span class="text-base font-semibold tracking-tight">
                            Lumina
                        </span>
                    </Link>

                    <nav class="hidden items-center gap-1 md:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.match"
                            :href="item.href"
                            class="nav-link"
                            :class="{
                                'nav-link-active': route().current(item.match),
                            }"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="user"
                        :href="route('credits.index')"
                        class="hidden items-center gap-2 rounded-full border border-ember-500/25 bg-ember-500/[0.07] px-3.5 py-1.5 text-sm font-semibold text-ember-300 transition hover:border-ember-400/50 sm:inline-flex"
                        title="Credit balance"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-ember-400" />
                        {{ formatNano(user.credit_balance_nano) }}
                    </Link>

                    <details
                        v-if="user"
                        class="group relative hidden sm:block"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-400 transition hover:bg-white/[0.06] hover:text-white [&::-webkit-details-marker]:hidden"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-studio-700 text-xs font-semibold text-ember-300"
                            >
                                {{ user.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="max-w-32 truncate">{{ user.name }}</span>
                        </summary>
                        <div
                            class="panel absolute right-0 z-20 mt-2 w-48 overflow-hidden p-1.5"
                        >
                            <div
                                class="px-3 py-2 text-xs text-zinc-500"
                            >
                                {{ user.email }}
                            </div>
                            <Link
                                :href="route('profile.edit')"
                                class="block rounded-lg px-3 py-2 text-sm text-zinc-300 hover:bg-white/[0.06] hover:text-white"
                            >
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-zinc-300 hover:bg-white/[0.06] hover:text-white"
                            >
                                Log out
                            </Link>
                        </div>
                    </details>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-400 hover:bg-white/[0.06] hover:text-white md:hidden"
                        aria-label="Toggle navigation"
                        @click="showingMenu = !showingMenu"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M4 7h16M4 12h16M4 17h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <div
                v-if="showingMenu"
                class="border-t border-white/[0.06] px-4 pb-4 pt-2 md:hidden"
            >
                <div class="flex flex-col gap-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.match"
                        :href="item.href"
                        class="nav-link"
                        :class="{
                            'nav-link-active': route().current(item.match),
                        }"
                        @click="showingMenu = false"
                    >
                        {{ item.label }}
                    </Link>
                    <Link
                        v-if="user"
                        :href="route('profile.edit')"
                        class="nav-link"
                        @click="showingMenu = false"
                    >
                        Profile
                    </Link>
                    <Link
                        v-if="user"
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="nav-link text-left"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </header>

        <main class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div
                v-if="
                    !dismissedFlash &&
                    (flash.success || flash.error)
                "
                class="mb-6 flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm"
                :class="
                    flash.error
                        ? 'border-rose-500/25 bg-rose-500/[0.07] text-rose-200'
                        : 'border-emerald-500/25 bg-emerald-500/[0.07] text-emerald-200'
                "
            >
                <p>{{ flash.error ?? flash.success }}</p>
                <button
                    type="button"
                    class="text-xs uppercase tracking-wide opacity-60 hover:opacity-100"
                    @click="dismissedFlash = true"
                >
                    Dismiss
                </button>
            </div>

            <slot />
        </main>
    </div>
</template>
