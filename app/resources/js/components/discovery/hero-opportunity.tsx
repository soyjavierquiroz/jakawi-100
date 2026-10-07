import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { useOpportunityAnalytics } from '@/hooks/use-opportunity-analytics';
import type { DiscoveryOpportunity } from '@/types/discovery';
import { benefitValue, experienceTiming, opportunityLabel, opportunityType } from './opportunity-card';

function HeroCopy({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const context = opportunity.location?.name ?? opportunity.partner?.name;
    if (opportunity.type === 'BENEFIT') {
        const value = benefitValue(opportunity);
        return <>{value ? <p className="text-2xl leading-none font-extrabold tracking-[-0.04em] text-foreground sm:text-3xl">{value}</p> : null}<h2 className={`${value ? 'mt-2' : ''} text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl`}>{opportunity.title}</h2>{opportunity.partner?.name ? <p className="mt-2 text-sm font-medium text-muted-foreground">{opportunity.partner.name}</p> : null}</>;
    }
    if (opportunity.type === 'EXPERIENCE') return <><h2 className="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl">{opportunity.title}</h2>{experienceTiming(opportunity) ? <p className="mt-3 text-base font-extrabold text-foreground">{experienceTiming(opportunity)}</p> : null}{context ? <p className="mt-1 text-sm font-medium text-muted-foreground">{context}</p> : null}</>;
    if (opportunity.type === 'CHALLENGE') return <><h2 className="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl">{opportunity.title}</h2><p className="mt-3 text-base font-extrabold">{opportunity.primary_value}</p><p className="mt-1 text-sm text-muted-foreground">{opportunity.secondary_value}</p></>;
    const target = typeof opportunity.metadata.target === 'number' ? opportunity.metadata.target : null;
    const progress = typeof opportunity.metadata.progress === 'number' ? opportunity.metadata.progress : null;
    const remaining = typeof opportunity.metadata.remaining === 'number' ? opportunity.metadata.remaining : null;
    const percentage = target && progress !== null ? Math.min(100, Math.max(0, (progress / target) * 100)) : null;
    return <><h2 className="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl">{opportunity.title}</h2>{remaining !== null ? <p className="mt-3 text-base font-extrabold text-foreground">FALTAN {remaining}</p> : opportunity.primary_value ? <p className="mt-3 text-base font-extrabold text-foreground">{opportunity.primary_value}</p> : null}{percentage !== null ? <div className="mt-3 h-1.5 max-w-xs overflow-hidden rounded-full bg-surface-muted" aria-label={`${progress} de ${target}`}><div className="h-full rounded-full bg-brand" style={{ width: `${percentage}%` }} /></div> : null}{opportunity.secondary_value ? <p className="mt-1 text-sm text-muted-foreground">{opportunity.secondary_value}</p> : null}</>;
}

export function HeroOpportunity({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const { elementRef, onOpen } = useOpportunityAnalytics({ opportunity, surface: 'HOME', section: 'HERO', position: 0 });
    const hasImage = Boolean(opportunity.image);
    return <section className="overflow-hidden rounded-[calc(var(--radius-card)+0.25rem)] bg-surface-elevated shadow-featured"><Link ref={elementRef} href={opportunity.destination_url} onClick={onOpen} className="group grid focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-brand sm:grid-cols-[3fr_2fr]"><div className="order-1 relative min-h-[13rem] overflow-hidden sm:min-h-[24rem] lg:min-h-[27rem]">{hasImage ? <JakawiImage src={opportunity.image} sizes="(min-width: 1024px) 672px, 100vw" alt={opportunity.title} className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.02]" loading="eager" priority fallbackContent={false} /> : <div role="img" aria-label={opportunity.title} className="absolute inset-0 bg-surface-muted" />}</div><div className="order-2 flex min-h-[15rem] flex-col justify-end p-5 sm:min-h-[24rem] sm:p-8 lg:min-h-[27rem] lg:p-10"><span className="mb-4 block h-1 w-10 rounded-full bg-brand" aria-hidden="true" /><p className="discovery-eyebrow text-muted-foreground">{opportunityType(opportunity.type)}{opportunity.type !== 'UNLOCK' && opportunity.partner?.name ? ` · ${opportunity.partner.name}` : ''}</p><div className="mt-2"><HeroCopy opportunity={opportunity} /></div><span className="mt-6 inline-flex min-h-11 w-fit items-center rounded-[var(--radius-control)] bg-brand px-4 text-sm font-extrabold tracking-[0.04em] text-brand-foreground transition group-hover:bg-brand/90">{opportunityLabel(opportunity.type)}</span></div></Link></section>;
}
