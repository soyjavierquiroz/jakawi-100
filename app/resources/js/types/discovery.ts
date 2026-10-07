export type OpportunityType = 'BENEFIT' | 'EXPERIENCE' | 'UNLOCK' | 'CHALLENGE';

export type DiscoveryCity = {
    id: number;
    name: string;
    slug: string;
};

export type DiscoveryPartner = {
    id: number;
    name: string;
    slug: string;
};

export type DiscoveryLocation = {
    id: number;
    name: string;
    label: string | null;
};

export type DiscoveryAvailability = {
    is_available: boolean;
    state: string | null;
    reason: string | null;
};

export type DiscoveryUrgency = {
    code: string;
    label: string | null;
    at: string | null;
};

export type DiscoveryOpportunity = {
    type: OpportunityType;
    source_id: number | string;
    title: string;
    destination_url: string;
    city: DiscoveryCity;
    subtitle: string | null;
    image: string | null;
    partner: DiscoveryPartner | null;
    location: DiscoveryLocation | null;
    categories: string[];
    availability: DiscoveryAvailability | null;
    starts_at: string | null;
    ends_at: string | null;
    primary_value: string | null;
    secondary_value: string | null;
    urgency: DiscoveryUrgency | null;
    editorial_priority: number | null;
    metadata: Record<string, boolean | number | string | null>;
};

export type HomeDiscovery = {
    hero: DiscoveryOpportunity | null;
    forYou: DiscoveryOpportunity[];
    happeningNow: DiscoveryOpportunity[];
    discoverMore: DiscoveryOpportunity[];
};
