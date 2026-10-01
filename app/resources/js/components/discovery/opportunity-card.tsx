import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { useOpportunityAnalytics } from '@/hooks/use-opportunity-analytics';
import type { DiscoveryOpportunity } from '@/types/discovery';

type HomeSection = 'FOR_YOU' | 'HAPPENING_NOW' | 'DISCOVER_MORE';

export function opportunityLabel(type: DiscoveryOpportunity['type']) {
    return type === 'BENEFIT' ? 'VER BENEFICIO' : type === 'EXPERIENCE' ? 'VER EXPERIENCIA' : 'VER DESBLOQUEO';
}

export function opportunityType(type: DiscoveryOpportunity['type']) {
    return type === 'BENEFIT' ? 'Beneficio' : type === 'EXPERIENCE' ? 'Experiencia' : 'Desbloqueo';
}

export function experienceTiming(opportunity: DiscoveryOpportunity) {
    if (!opportunity.starts_at) return opportunity.secondary_value;
    const startsAt = new Date(opportunity.starts_at);
    if (Number.isNaN(startsAt.getTime())) return opportunity.secondary_value;
    return new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' }).format(startsAt);
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
    if (opportunity.type === 'BENEFIT') return <>{opportunity.primary_value ? <p className="mt-2 text-lg leading-snug font-extrabold text-foreground">{opportunity.primary_value}</p> : null}<h3 className="mt-1 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3>{opportunity.partner?.name ? <p className="mt-2 text-sm text-muted-foreground">{opportunity.partner.name}</p> : null}</>;
    if (opportunity.type === 'EXPERIENCE') return <><h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3>{experienceTiming(opportunity) ? <p className="mt-2 text-sm font-extrabold text-foreground">{experienceTiming(opportunity)}</p> : null}{context ? <p className="mt-1 text-sm text-muted-foreground">{context}</p> : null}</>;
    return <><h3 className="mt-2 text-lg leading-tight font-extrabold tracking-tight">{opportunity.title}</h3><UnlockProgress opportunity={opportunity} /></>;
}

export function OpportunityCard({ opportunity, section, position, compact = false }: { opportunity: DiscoveryOpportunity; section: HomeSection; position: number; compact?: boolean }) {
    const { elementRef, onOpen } = useOpportunityAnalytics({ opportunity, surface: 'HOME', section, position });
    return <Link ref={elementRef} href={opportunity.destination_url} onClick={onOpen} className={`group block shrink-0 overflow-hidden rounded-[var(--radius-card)] bg-surface-elevated shadow-[0_8px_24px_color-mix(in_srgb,var(--foreground)_8%,transparent)] transition duration-200 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:scale-[.99] ${compact ? 'w-[15.5rem] sm:w-auto' : 'w-[17.5rem] sm:w-auto'}`}><div className={`${compact ? 'aspect-[16/9]' : 'aspect-[4/3]'} overflow-hidden bg-surface-muted`}><JakawiImage src={opportunity.image} sizes="(min-width: 640px) 33vw, 280px" alt={opportunity.title} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" fallbackContent={false} /></div><div className="p-[var(--space-card)]"><p className="discovery-eyebrow text-brand">{opportunityType(opportunity.type)}</p><OpportunityDetails opportunity={opportunity} /><span className="mt-4 inline-flex min-h-8 items-center text-xs font-extrabold tracking-[0.08em] text-brand">{opportunityLabel(opportunity.type)}</span></div></Link>;
}
