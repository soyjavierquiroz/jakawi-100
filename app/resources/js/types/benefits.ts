export type PartnerSummary = {
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
    image_srcset?: Array<{ src: string; width: number }>;
    hero_url?: string | null;
    hero_srcset?: Array<{ src: string; width: number }>;
    is_featured?: boolean;
    redemption_limit_per_member?: number | null;
    starts_at?: string | null;
    ends_at?: string | null;
    partner?: PartnerSummary | null;
};
