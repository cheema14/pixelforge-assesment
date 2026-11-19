<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { formatNano, estimateGeneration } from '@/lib/money';
import type { Pricing } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    pricing: Pricing;
    markupPercent: number;
    balanceNano: number;
    initialPrompt: string;
}>();

const sizes = [
    { value: '1024x1024', label: 'Square', hint: '1024 × 1024' },
    { value: '1536x1024', label: 'Landscape', hint: '1536 × 1024' },
    { value: '1024x1536', label: 'Portrait', hint: '1024 × 1536' },
];

const qualities = [
    {
        value: 'low',
        label: 'Low',
        hint: 'Fast draft, fewest reasoning tokens',
    },
    {
        value: 'medium',
        label: 'Medium',
        hint: 'Balanced detail and cost',
    },
    {
        value: 'high',
        label: 'High',
        hint: 'Maximum detail, widest token range',
    },
];

const form = useForm({
    prompt: props.initialPrompt ?? '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const promptLength = computed(() => Array.from(form.prompt).length);

const filledReferences = computed(() =>
    form.reference_images.filter((url) => url.trim() !== ''),
);

const estimate = computed(() =>
    estimateGeneration(
        props.pricing,
        {
            prompt: form.prompt,
            size: form.size,
            quality: form.quality,
            referenceCount: filledReferences.value.length,
        },
        props.markupPercent,
    ),
);

const affordable = computed(
    () =>
        estimate.value !== null &&
        BigInt(props.balanceNano) >= estimate.value.chargeNano,
);

const balanceError = computed(
    () => (form.errors as Record<string, string>).balance,
);

const addReference = () => {
    if (form.reference_images.length < 4) {
        form.reference_images.push('');
    }
};

const removeReference = (index: number) => {
    form.reference_images.splice(index, 1);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        reference_images: data.reference_images
            .map((url) => url.trim())
            .filter(Boolean),
    })).post(route('generations.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Create" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <p class="text-xs uppercase tracking-widest text-ember-500">
                Studio
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white">
                New generation
            </h1>
            <p class="mt-1.5 text-sm text-zinc-500">
                Describe the image, tune the settings, and review the cost
                before you submit.
            </p>
        </div>

        <form
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]"
            @submit.prevent="submit"
        >
            <div class="panel space-y-7 p-6 sm:p-8">
                <div>
                    <div class="flex items-center justify-between">
                        <InputLabel for="prompt" value="Prompt" />
                        <span
                            class="text-xs"
                            :class="
                                promptLength > 4000
                                    ? 'text-rose-400'
                                    : 'text-zinc-600'
                            "
                        >
                            {{ promptLength }} / 4000
                        </span>
                    </div>

                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="6"
                        maxlength="4000"
                        required
                        placeholder="A cinematic portrait of a lighthouse at dusk, warm fog, 35mm film grain…"
                        class="field mt-2 resize-none leading-relaxed"
                    />

                    <InputError class="mt-2" :message="form.errors.prompt" />
                </div>

                <div>
                    <InputLabel value="Size" />
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="size in sizes"
                            :key="size.value"
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left transition"
                            :class="
                                form.size === size.value
                                    ? 'border-ember-500/60 bg-ember-500/[0.07]'
                                    : 'border-white/10 bg-studio-800/60 hover:border-white/20'
                            "
                            @click="form.size = size.value"
                        >
                            <span
                                class="block text-sm font-medium"
                                :class="
                                    form.size === size.value
                                        ? 'text-ember-200'
                                        : 'text-zinc-200'
                                "
                            >
                                {{ size.label }}
                            </span>
                            <span class="mt-0.5 block text-xs text-zinc-500">
                                {{ size.hint }}
                            </span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.size" />
                </div>

                <div>
                    <InputLabel value="Quality" />
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="quality in qualities"
                            :key="quality.value"
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left transition"
                            :class="
                                form.quality === quality.value
                                    ? 'border-ember-500/60 bg-ember-500/[0.07]'
                                    : 'border-white/10 bg-studio-800/60 hover:border-white/20'
                            "
                            @click="form.quality = quality.value"
                        >
                            <span
                                class="block text-sm font-medium"
                                :class="
                                    form.quality === quality.value
                                        ? 'text-ember-200'
                                        : 'text-zinc-200'
                                "
                            >
                                {{ quality.label }}
                            </span>
                            <span class="mt-0.5 block text-xs text-zinc-500">
                                {{ quality.hint }}
                            </span>
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.quality" />
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <InputLabel
                            value="Reference images (optional)"
                        />
                        <button
                            v-if="form.reference_images.length < 4"
                            type="button"
                            class="text-xs font-medium text-ember-400 transition hover:text-ember-300"
                            @click="addReference"
                        >
                            + Add reference
                        </button>
                    </div>

                    <p class="mt-1 text-xs text-zinc-600">
                        Up to 4 public image URLs. Each reference adds image
                        tokens to the cost.
                    </p>

                    <div
                        v-if="form.reference_images.length === 0"
                        class="mt-3 rounded-xl border border-dashed border-white/10 px-4 py-4 text-center text-xs text-zinc-600"
                    >
                        No references — the prompt stands on its own.
                    </div>

                    <div v-else class="mt-3 space-y-2">
                        <div
                            v-for="(url, index) in form.reference_images"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="form.reference_images[index]"
                                type="url"
                                placeholder="https://example.com/reference.jpg"
                                class="field"
                            />
                            <button
                                type="button"
                                class="rounded-lg p-2 text-zinc-500 transition hover:bg-white/5 hover:text-rose-300"
                                aria-label="Remove reference"
                                @click="removeReference(index)"
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
                                        d="M6 6l12 12M18 6 6 18"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <InputError
                        class="mt-2"
                        :message="
                            form.errors['reference_images'] ??
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('reference_images.'),
                            )?.[1]
                        "
                    />
                </div>
            </div>

            <aside class="panel sticky top-6 p-6">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-widest text-zinc-500">
                        Cost preview
                    </span>
                    <span
                        class="rounded-full bg-ember-500/10 px-2.5 py-1 text-[11px] font-semibold text-ember-300 ring-1 ring-inset ring-ember-400/30"
                    >
                        +{{ markupPercent }}% markup
                    </span>
                </div>

                <div v-if="estimate" class="mt-5">
                    <p class="text-3xl font-semibold tracking-tight text-white">
                        {{ formatNano(estimate.chargeNano) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">
                        Estimated charge · up to
                        {{ formatNano(estimate.maxChargeNano) }} if reasoning
                        runs long
                    </p>

                    <div class="mt-5 space-y-2.5 border-t border-white/[0.06] pt-4 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Input text</span>
                            <span class="text-zinc-300">
                                {{ estimate.textTokens.toLocaleString() }} tokens
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">
                                Reference images ({{ filledReferences.length }})
                            </span>
                            <span class="text-zinc-300">
                                {{ estimate.imageTokens.toLocaleString() }} tokens
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Output</span>
                            <span class="text-zinc-300">
                                {{ estimate.outputTokens.toLocaleString() }} tokens
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">
                                Reasoning (midpoint)
                            </span>
                            <span class="text-zinc-300">
                                {{ estimate.reasoningTokens.toLocaleString() }} tokens
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">Worst-case reasoning</span>
                            <span class="text-zinc-400">
                                {{ estimate.maxReasoningTokens.toLocaleString() }} tokens
                            </span>
                        </div>
                    </div>

                    <div
                        class="mt-5 rounded-xl border border-white/[0.06] bg-studio-950/60 p-3.5"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-400">Available balance</span>
                            <span
                                :class="
                                    affordable ? 'text-white' : 'text-rose-300'
                                "
                            >
                                {{ formatNano(balanceNano) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="!affordable"
                        class="mt-3 rounded-xl border border-rose-500/25 bg-rose-500/[0.07] px-3.5 py-2.5 text-xs text-rose-200"
                    >
                        Your balance does not cover this estimate. Buy credits
                        or lower the quality to continue.
                    </div>

                    <InputError
                        class="mt-3"
                        :message="balanceError"
                    />
                </div>

                <div v-else class="mt-6 text-sm text-zinc-500">
                    Choose a size and quality to see the price.
                </div>

                <button
                    type="submit"
                    class="btn-primary mt-6 w-full py-3"
                    :disabled="
                        form.processing ||
                        estimate === null ||
                        !affordable ||
                        promptLength === 0
                    "
                >
                    {{
                        form.processing
                            ? 'Submitting…'
                            : `Generate for ${estimate ? formatNano(estimate.chargeNano) : '—'}`
                    }}
                </button>

                <p class="mt-3 text-center text-[11px] leading-relaxed text-zinc-600">
                    The final charge is settled from the provider's reported
                    usage. Failed generations are refunded.
                </p>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
