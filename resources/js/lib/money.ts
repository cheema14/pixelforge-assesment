import type { Pricing } from '@/types';

const NANO_PER_DOLLAR = 1_000_000_000n;

/** Convert an exact decimal string such as "0.046125000" to nanodollars. */
export function nanoFromDecimal(amount: string): bigint {
    const match = /^(-)?(\d+)(?:\.(\d{1,9}))?$/.exec(amount.trim());

    if (!match) {
        throw new Error(`Invalid money amount [${amount}].`);
    }

    const fraction = (match[3] ?? '').padEnd(9, '0');
    const nano = BigInt(match[2]) * NANO_PER_DOLLAR + BigInt(fraction);

    return match[1] === '-' ? -nano : nano;
}

/** tokens × USD-per-million-tokens, rounded half up, in nanodollars. */
export function costForTokens(tokens: number, ratePerMillion: string): bigint {
    return (BigInt(tokens) * nanoFromDecimal(ratePerMillion) + 500_000n) / 1_000_000n;
}

/** Apply a whole-percent markup, rounded half up. */
export function withMarkup(providerCostNano: bigint, markupPercent: number): bigint {
    return (providerCostNano * BigInt(100 + markupPercent) + 50n) / 100n;
}

/**
 * Format nanodollars as USD. Mirrors the server formatter: at least two
 * decimals, trailing zeroes trimmed, never more than `maxDecimals`.
 */
export function formatNano(value: bigint | number, maxDecimals = 6): string {
    let nano = BigInt(value);
    const sign = nano < 0n ? '-' : '';
    nano = nano < 0n ? -nano : nano;

    const decimals = Math.max(2, Math.min(9, maxDecimals));
    const roundTo = 10n ** BigInt(9 - decimals);
    const rounded = ((nano + roundTo / 2n) / roundTo) * roundTo;

    const whole = rounded / NANO_PER_DOLLAR;
    let fraction = (rounded % NANO_PER_DOLLAR)
        .toString()
        .padStart(9, '0')
        .slice(0, decimals)
        .replace(/0+$/, '');

    fraction = fraction.padEnd(2, '0');

    return `${sign}$${whole.toLocaleString('en-US')}.${fraction}`;
}

export interface CostEstimate {
    textTokens: number;
    maxTextTokens: number;
    imageTokens: number;
    outputTokens: number;
    reasoningTokens: number;
    maxReasoningTokens: number;
    providerCostNano: bigint;
    chargeNano: bigint;
    maxProviderCostNano: bigint;
    maxChargeNano: bigint;
}

interface EstimateInput {
    prompt: string;
    size: string;
    quality: string;
    referenceCount: number;
}

/**
 * Client-side mirror of PricingService::estimate. Lets the form show a live
 * price before submission; the server recalculates and stores the real
 * estimate so a tampered preview cannot change what is reserved.
 */
export function estimateGeneration(
    pricing: Pricing,
    { prompt, size, quality, referenceCount }: EstimateInput,
    markupPercent: number,
): CostEstimate | null {
    const outputTokens = pricing.tokens.output[quality]?.[size];
    const reasoningMin = pricing.tokens.reasoning[quality]?.min;
    const reasoningMax = pricing.tokens.reasoning[quality]?.max;

    if (outputTokens === undefined || reasoningMin === undefined || reasoningMax === undefined) {
        return null;
    }

    const charactersPerToken = Math.max(1, pricing.tokens.characters_per_text_token);
    const textTokens = Math.ceil(Array.from(prompt).length / charactersPerToken);
    const maxTextTokens = Math.ceil(textTokens * (1 + pricing.tokens.text_token_variance));
    const imageTokens = referenceCount * pricing.tokens.per_reference_image;
    const reasoningTokens = Math.trunc((reasoningMin + reasoningMax) / 2);

    const tokenCost = (
        text: number,
        inputImages: number,
        output: number,
        reasoning: number,
    ): bigint =>
        costForTokens(text, pricing.rates.input_text_tokens) +
        costForTokens(inputImages, pricing.rates.input_image_tokens) +
        costForTokens(output, pricing.rates.output_tokens) +
        costForTokens(reasoning, pricing.rates.reasoning_tokens);

    const providerCostNano = tokenCost(textTokens, imageTokens, outputTokens, reasoningTokens);
    const maxProviderCostNano = tokenCost(maxTextTokens, imageTokens, outputTokens, reasoningMax);

    return {
        textTokens,
        maxTextTokens,
        imageTokens,
        outputTokens,
        reasoningTokens,
        maxReasoningTokens: reasoningMax,
        providerCostNano,
        chargeNano: withMarkup(providerCostNano, markupPercent),
        maxProviderCostNano,
        maxChargeNano: withMarkup(maxProviderCostNano, markupPercent),
    };
}
