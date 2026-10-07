import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { useOpportunityAnalytics } from '@/hooks/use-opportunity-analytics';
import type { DiscoveryOpportunity } from '@/types/discovery';

type DiscoverySection = 'FOR_YOU' | 'HAPPENING_NOW' | 'DISCOVER_MORE' | 'RESULTS';

export function opportunityLabel(type: DiscoveryOpportunity['type']) {
    return type === 'BENEFIT' ? 'VER BENEFICIO' : type === 'EXPERIENCE' ? 'VER EXPERIENCIA' : type === 'CHALLENGE' ? 'VER RETO' : 'VER DESBLOQUEO';
}

export function opportunityType(type: DiscoveryOpportunity['type']) {
    return type === 'BENEFIT' ? 'Beneficio' : type === 'EXPERIENCE' ? 'Experiencia' : type === 'CHALLENGE' ? 'Reto' : 'Desbloqueo';
}

export function experienceTiming(opportunity: DiscoveryOpportunity) {
    if (!opportunity.starts_at) return opportunity.secondary_value;
    const startsAt = new Date(opportunity.starts_at);
    if (Number.isNaN(startsAt.getTime())) return opportunity.secondary_value;
    return new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' }).format(startsAt);
}

// A membership explanation is useful context, but it is not an editorial offer.
// Keep the contract value intact while allowing the actual title to lead when it
// is the only meaningful value supplied by the opportunity.
export function benefitValue(opportunity: DiscoveryOpportunity) {
    const value = opportunity.primary_value?.trim();
    if (!value) return null;
    return /beneficio para miembros|en tu pr[oó]xima visita|miembros jakawi/i.test(value) ? null : value;
}

function UnlockProgress({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const target = typeof opportunity.metadata.target === 'number' ? opportunity.metadata.target : null;
    const progress = typeof opportunity.metadata.progress === 'number' ? opportunity.metadata.progress : null;
    const remaining = typeof opportunity.metadata.remaining === 'number' ? opportunity.metadata.remaining : null;
    const percentage = target && progress !== null ? Math.min(100, Math.max(0, (progress / target) * 100)) : null;

    return <div className="mt-3">
        {remaining !== null ? <p className="text-sm font-extrabold text-foreground">FALTAN {remaining}</p> : opportunity.primary_value ? <p className="text-sm font-extrabold text-foreground">{opportunity.primary_value}</p> : null}
        {percentage !== null ? <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-border" aria-label={`${progress} de ${target}`}><div className="h-full rounded-full bg-brand" style={{ width: `${percentage}%` }} /></div> : null}
        {opportunity.secondary_value ? <p className="mt-1 text-xs font-semibold text-muted-foreground">{opportunity.secondary_value}</p> : null}
    </div>;
}

function OpportunityDetails({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const context = opportunity.location?.name ?? opportunity.partner?.name;
    if (opportunity.type === 'BENEFIT') {
        const value = benefitValue(opportunity);
        return <>{value ? <p className="mt-2 text-xl leading-none font-extrabold tracking-[-0.035em] text-foreground">{value}</p> : null}<h3 className={`${value ? 'mt-2' : 'mt-2'} text-lg leading-tight font-extrabold tracking-tight`}>{opportunity.title}</h3>{opportunity.partner?.name ? <p className="mt-2 text-sm font-medium text-muted-foreground">{opportunity.partner.name}</p> : null}</>;
    }
    if (opportunity.type === 'EXPERIENCE') return <><h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3>{experienceTiming(opportunity) ? <p className="mt-3 text-sm leading-tight font-extrabold text-foreground">{experienceTiming(opportunity)}</p> : null}{context ? <p className="mt-1.5 text-sm font-medium text-muted-foreground">{context}</p> : null}</>;
    if (opportunity.type === 'CHALLENGE') return <><h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3><p className="mt-2 text-sm font-bold">{opportunity.primary_value}</p><p className="mt-1 text-sm text-muted-foreground">{opportunity.secondary_value}</p></>;
    return <><h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3><UnlockProgress opportunity={opportunity} /></>;
}

export function OpportunityCard({ opportunity, section, position, compact = false, surface = 'HOME', variant = 'home-carousel' }: { opportunity: DiscoveryOpportunity; section: DiscoverySection; position: number; compact?: boolean; surface?: 'HOME' | 'EXPLORE' | 'SEARCH'; variant?: 'home-carousel' | 'explore-grid' }) {
    const { elementRef, onOpen } = useOpportunityAnalytics({ opportunity, surface, section, position });
    if (variant === 'explore-grid') {
        return <Link ref={elementRef} href={opportunity.destination_url} onClick={onOpen} className="group grid min-h-40 grid-cols-[42%_1fr] overflow-hidden rounded-[var(--radius-card)] bg-surface-elevated shadow-editorial transition duration-200 hover:-translate-y-0.5 hover:shadow-featured focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:scale-[.99] sm:block"><div className="overflow-hidden bg-surface-muted sm:aspect-[5/4]"><JakawiImage src={opportunity.image} sizes="(min-width: 1024px) 29vw, (min-width: 640px) 44vw, 42vw" alt={opportunity.title} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" fallbackContent={false} /></div><div className="min-w-0 p-4 sm:p-[var(--space-card)]"><p className="discovery-eyebrow text-muted-foreground">{opportunityType(opportunity.type)}</p><OpportunityDetails opportunity={opportunity} /><span className="mt-3 block text-xs font-extrabold tracking-[0.08em] text-muted-foreground transition group-hover:text-foreground" aria-hidden="true">DESCUBRIR ↗</span></div></Link>;
    }
    const width = compact ? 'w-[72vw] sm:w-auto' : 'w-[80vw] sm:w-auto';
    const media = compact ? 'aspect-[16/9]' : 'aspect-[5/4]';
    const padding = compact ? 'p-4' : 'p-[var(--space-card)]';
    return <Link ref={elementRef} href={opportunity.destination_url} onClick={onOpen} className={`group block shrink-0 overflow-hidden rounded-[var(--radius-card)] bg-surface-elevated shadow-editorial transition duration-200 hover:-translate-y-0.5 hover:shadow-featured focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:scale-[.99] ${width}`}><div className={`${media} overflow-hidden bg-surface-muted`}><JakawiImage src={opportunity.image} sizes="(min-width: 1024px) 32vw, (min-width: 640px) 45vw, 80vw" alt={opportunity.title} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" fallbackContent={false} /></div><div className={padding}><p className="discovery-eyebrow text-muted-foreground">{opportunityType(opportunity.type)}</p><OpportunityDetails opportunity={opportunity} /><span className="mt-3 block text-xs font-extrabold tracking-[0.08em] text-muted-foreground transition group-hover:text-foreground" aria-hidden="true">DESCUBRIR ↗</span></div></Link>;
}
