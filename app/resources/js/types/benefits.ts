export type MerchantSummary = {
    id?: number;
    name: string;
    category?: string | null;
    address?: string | null;
    city?: string | null;
};

export type BenefitSummary = {
    id: number;
    title: string;
    slug: string;
    short_description?: string | null;
    description?: string | null;
    terms?: string | null;
    benefit_type?: string | null;
    estimated_savings?: string | number | null;
    image_url?: string | null;
    is_featured?: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    merchant: MerchantSummary;
};
