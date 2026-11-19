export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    credit_balance_nano: number;
    email_verified_at?: string | null;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    appName: string;
    auth: {
        user: User | null;
    };
    flash: Flash;
};

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
}

export type GenerationStatus =
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

export interface Generation {
    id: string;
    status: GenerationStatus;
    statusLabel: string;
    prompt: string;
    size: string;
    quality: string;
    referenceImages: string[];
    imageUrl: string | null;
    imageUrlExpiresAt: string | null;
    imageExpired: boolean;
    estimatedChargeNano: number;
    maxChargeNano: number;
    chargedNano: number | null;
    errorCode: string | null;
    errorMessage: string | null;
    createdAt: string | null;
    startedAt: string | null;
    completedAt: string | null;
}

export interface CreditTransaction {
    id: number;
    type: string;
    typeLabel: string;
    amountNano: number;
    description: string;
    generationId: string | null;
    createdAt: string | null;
}

export interface Pricing {
    rates: Record<
        | 'input_text_tokens'
        | 'input_image_tokens'
        | 'output_tokens'
        | 'reasoning_tokens',
        string
    >;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
}

export interface ReportDay {
    date: string;
    generations: number;
    chargedNano: number;
    providerCostNano: number;
    profitNano: number;
    providerUsageNano: number | null;
    providerUsageGenerations: number | null;
    differenceNano: number | null;
    reconciled: boolean;
}

export interface ReportUser {
    userId: number;
    name: string;
    email: string;
    generations: number;
    chargedNano: number;
    providerCostNano: number;
}

export interface AdminReport {
    period: { from: string; to: string };
    days: ReportDay[];
    users: ReportUser[];
    totals: {
        generations: number;
        chargedNano: number;
        providerCostNano: number;
        profitNano: number;
        providerUsageNano: number | null;
        providerUsageGenerations: number | null;
        differenceNano: number | null;
        reconciled: boolean;
    };
    usageError: string | null;
    markupPercent: number;
}
