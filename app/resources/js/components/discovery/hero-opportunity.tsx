import { Link } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { useOpportunityAnalytics } from '@/hooks/use-opportunity-analytics';
import type { DiscoveryOpportunity } from '@/types/discovery';
import { benefitValue, experienceTiming, opportunityLabel, opportunityType } from './opportunity-card';

function HeroCopy({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const context = opportunity.location?.name ?? opportunity.partner?.name;
    if (opportunity.type === 'BENEFIT') {
        const value = benefitValue(opportunity);
        return <>{value ? <p className="text-2xl leading-none font-extrabold tracking-[-0.04em] sm:text-3xl">{value}</p> : null}<h2 className={`${value ? 'mt-2' : ''} text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl`}>{opportunity.title}</h2>{opportunity.partner?.name ? <p className="mt-2 text-sm font-medium text-background/85">{opportunity.partner.name}</p> : null}</>;
    }
    if (opportunity.type === 'EXPERIENCE') return <><h2 className="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl">{opportunity.title}</h2>{experienceTiming(opportunity) ? <p className="mt-2 text-base font-extrabold">{experienceTiming(opportunity)}</p> : null}{context ? <p className="mt-1 text-sm font-medium text-background/80">{context}</p> : null}</>;
    const target = typeof opportunity.metadata.target === 'number' ? opportunity.metadata.target : null;
    const progress = typeof opportunity.metadata.progress === 'number' ? opportunity.metadata.progress : null;
    const remaining = typeof opportunity.metadata.remaining === 'number' ? opportunity.metadata.remaining : null;
    const percentage = target && progress !== null ? Math.min(100, Math.max(0, (progress / target) * 100)) : null;
    return <><h2 className="text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl">{opportunity.title}</h2>{remaining !== null ? <p className="mt-2 text-base font-extrabold">FALTAN {remaining}</p> : opportunity.primary_value ? <p className="mt-2 text-base font-extrabold">{opportunity.primary_value}</p> : null}{percentage !== null ? <div className="mt-3 h-1.5 max-w-xs overflow-hidden rounded-full bg-background/30" aria-label={`${progress} de ${target}`}><div className="h-full rounded-full bg-action" style={{ width: `${percentage}%` }} /></div> : null}{opportunity.secondary_value ? <p className="mt-1 text-sm text-background/80">{opportunity.secondary_value}</p> : null}</>;
}

export function HeroOpportunity({ opportunity }: { opportunity: DiscoveryOpportunity }) {
    const { elementRef, onOpen } = useOpportunityAnalytics({ opportunity, surface: 'HOME', section: 'HERO', position: 0 });
    const hasImage = Boolean(opportunity.image);
    return <section className="mt-7 overflow-hidden rounded-[calc(var(--radius-card)+0.25rem)] bg-foreground text-background shadow-[0_18px_45px_color-mix(in_srgb,var(--foreground)_18%,transparent)]"><Link ref={elementRef} href={opportunity.destination_url} onClick={onOpen} className="group relative block min-h-[19rem] focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-brand sm:min-h-[24rem] lg:min-h-[27rem]">{hasImage ? <JakawiImage src={opportunity.image} sizes="(min-width: 1024px) 1120px, 100vw" alt={opportunity.title} className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.02]" loading="eager" priority fallbackContent={false} /> : <div role="img" aria-label={opportunity.title} className="absolute inset-0 bg-foreground" />}<div aria-hidden="true" className="absolute inset-0 bg-gradient-to-t from-[color:var(--overlay-scrim)] via-[color:var(--overlay-scrim)]/60 to-transparent" /><div className="absolute inset-x-0 bottom-0 p-5 sm:max-w-2xl sm:p-8"><p className="discovery-eyebrow mb-3 text-background/85">{opportunityType(opportunity.type)}{opportunity.type !== 'UNLOCK' && opportunity.partner?.name ? ` · ${opportunity.partner.name}` : ''}</p><HeroCopy opportunity={opportunity} /><span className="mt-5 inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-background px-4 text-sm font-extrabold tracking-[0.04em] text-foreground shadow-sm">{opportunityLabel(opportunity.type)}</span></div></Link></section>;
}
