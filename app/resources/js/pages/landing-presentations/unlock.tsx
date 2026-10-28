import { useLandingAction } from '@/components/marketing/landing-action-context';
import { useAcquisition } from '@/components/marketing/acquisition-context';
import { OfferHero } from '@/components/marketing/offer/blocks';
import { VideoLandingAction, useVideoLandingAction } from '@/components/marketing/VideoLandingAction';
import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import { Head, Link, usePage } from '@inertiajs/react';
import { useRef, type MouseEvent } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Item = { title: string; description: string };
type Props = {
    vsl?: LandingVideoConfig | null;
    nativeUrl: string; canonical: string; noindex: boolean; preview: boolean;
    copy: {
        title: string; slug: string; eyebrow: string; headline: string; subheadline: string;
        reward: string; description: string | null; valueNote: string | null; heroUrl: string | null; heroSrcset?: {src: string; width: number}[]; heroAlt: string;
        offers: { label: string; value: string }[];
        progress: { current: number; target: number; remaining: number; percent: number | null };
        status: string; stateLabel: string; success: boolean; failed: boolean; expired: boolean; deadline: string | null;
        partner: { name: string; slug: string } | null;
        locations: { name: string; slug: string; city: string | null }[];
        membership: string; jpRequired: number; guarantee: string | null; conditions: string[]; terms: string | null;
        viewer: { reason: string | null; participationStatus: string | null };
        action: { label: string; href: string; kind: string };
        benefits: Item[]; steps: Item[]; faq: { question: string; answer: string }[]; finalHeadline: string;
    };
};

export default function UnlockLanding({ vsl, canonical, noindex, preview, copy }: Props) {
    const { prelaunchPublic } = usePage().props;
    const acquire = useAcquisition();
    const continueAction = useLandingAction();
    const landingId = (usePage().props.presentation as { id: number }).id;
    const intentSent = useRef(false);
    const authenticated = Boolean(usePage().props.auth.user);
    const disabled = preview || copy.action.kind === 'unavailable';
    const commercialAction = { ...copy.action, href: copy.action.kind === 'membership' ? copy.action.href + `&landing_presentation_id=${landingId}` : copy.action.href, label: copy.action.kind === 'guest' ? 'QUIERO DESBLOQUEARLO' : copy.action.label, disabled, onClick: (event: MouseEvent<Element>) => {
        if (disabled) { event.preventDefault(); return; }
        // Auth and explicit domain actions remain over this Landing.
        if (copy.action.kind === 'guest') { event.preventDefault(); acquire(); return; }
        if (copy.action.kind === 'native') { event.preventDefault(); if (!authenticated) acquire(); else continueAction(); return; }
        if (copy.action.kind === 'membership') { event.preventDefault(); continueAction(); }
        if (copy.action.kind === 'membership' && !intentSent.current) {
            intentSent.current = true;
            trackJourneyIntent('unlock', copy.slug);
        }
    } };
    const videoAction = useVideoLandingAction(commercialAction, '#condiciones');
    const action = vsl ? videoAction.action : commercialAction;
    const number = (value: number) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(value);
    const heroDescription = `${copy.subheadline} ${number(copy.progress.current)} / ${number(copy.progress.target)} compromisos${!copy.success && !copy.failed && !copy.expired ? ` · Faltan ${number(copy.progress.remaining)}` : ''}.`;
    const progress = <section aria-label="Progreso real del desbloqueo" className="rounded-[var(--radius-card)] bg-surface-muted p-6 sm:p-8">
        <p className="discovery-eyebrow text-foreground">{copy.stateLabel}</p>
        {copy.progress.percent !== null && <p className="mt-4 text-6xl font-extrabold tracking-tight sm:text-7xl">{number(copy.progress.percent)}<span className="text-3xl">%</span></p>}
        {copy.progress.percent !== null && <div role="progressbar" aria-label="Compromisos hacia la meta" aria-valuemin={0} aria-valuemax={copy.progress.target} aria-valuenow={Math.min(copy.progress.current, copy.progress.target)} aria-valuetext={`${number(copy.progress.current)} de ${number(copy.progress.target)} compromisos`} className="mt-5 h-5 overflow-hidden rounded-full bg-border"><div className="h-full rounded-full bg-brand" style={{ width: `${Math.min(100, copy.progress.percent)}%` }}/></div>}
        <dl className="mt-6 grid grid-cols-3 gap-3">
            {([{ label: 'ACTUAL', value: copy.progress.current }, { label: 'META', value: copy.progress.target }, { label: 'FALTA', value: copy.progress.remaining }]).map(fact => <div key={fact.label} className="min-w-0"><dt className="text-xs font-extrabold tracking-wide">{fact.label}</dt><dd className="mt-2 break-all text-2xl font-extrabold sm:text-3xl">{number(fact.value)}</dd></div>)}
        </dl>
        <p className="mt-4 text-sm text-foreground-soft">Compromisos reales hacia la meta.</p>
        {copy.deadline && <p className="mt-4 text-sm font-bold">Plazo de compromisos: {copy.deadline}</p>}
    </section>;
    const trackGrowthClick = useGrowthLandingClicks();
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/><meta name="description" content={copy.subheadline}/>{noindex && <meta head-key="robots" name="robots" content={prelaunchPublic ? "noindex,nofollow" : "noindex,follow"}/>}</Head>
        <main data-video-first={vsl ? '' : undefined} onClickCapture={trackGrowthClick} className="overflow-x-clip break-words font-discovery">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                {vsl ? <>
                    <section data-offer-chapter="hero" className="offer-hero-v3 offer-media-led offer-with-media offer-video-first">
                        <div className="offer-intro min-w-0"><OfferHero hero={{ eyebrow: copy.eyebrow, headline: copy.headline, description: copy.subheadline }} treatment="editorial" /></div>
                        <div className="offer-media min-w-0" data-video-framing={vsl.framing ?? 'horizontal'}><LandingVideo config={{ ...vsl, theme: 'inherit' }} fullFrame /></div>
                        <p data-video-subtitle className="offer-video-subtitle text-lg leading-7">{copy.subheadline}</p>
                    </section>
                    <section id="landing-details" className="pb-8">{progress}</section>
                </> : <>
                    <div className="mt-6 overflow-hidden rounded-[var(--radius-card)] bg-surface-muted sm:mt-10"><JakawiImage src={copy.heroUrl} srcset={copy.heroSrcset} sizes="(max-width: 640px) 100vw, 80vw" alt={copy.heroAlt} className="aspect-[4/3] w-full object-cover sm:aspect-[16/7]" fallbackClassName="aspect-[4/3] sm:aspect-[16/7] border-0 bg-surface-muted" loading="eager" priority /></div>
                    <MarketingHero eyebrow={copy.eyebrow} title={copy.headline} description={heroDescription} action={action} aside={progress} />
                </>}
                {preview && <StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5" />}
                <section className="border-t border-border py-14 sm:py-20"><p className="discovery-eyebrow text-foreground">LO QUE DESBLOQUEAMOS</p><h2 className="mt-3 max-w-3xl text-3xl font-extrabold uppercase sm:text-5xl">{copy.title}</h2><p className="mt-6 max-w-3xl whitespace-pre-line leading-7 text-foreground-soft">{copy.reward}</p>{copy.offers.length > 0 && <dl className="mt-7 grid gap-6 sm:grid-cols-2">{copy.offers.map(offer => <div key={offer.label} className="rounded-[var(--radius-card)] bg-surface-muted p-6"><dt className="discovery-eyebrow text-foreground">{offer.label}</dt><dd className="mt-3 whitespace-pre-line text-lg font-bold">{offer.value}</dd></div>)}</dl>}{copy.valueNote && <p className="mt-5 leading-7">{copy.valueNote}</p>}</section>
                <MarketingBenefits eyebrow="POR QUÉ PARTICIPAR" title={copy.success ? 'LA META SE LOGRÓ' : (copy.failed || copy.expired ? 'EL RESULTADO' : 'HAGAMOS QUE SUCEDA')} items={copy.benefits}/>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-foreground">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase">DE LA PROPUESTA AL RESULTADO</h2><div className="mt-10 grid gap-7 md:grid-cols-3">{copy.steps.map((step, index) => <article key={step.title} className="border-t border-border pt-5"><span className="font-extrabold text-foreground">0{index + 1}</span><h3 className="mt-4 text-xl font-extrabold">{step.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{step.description}</p></article>)}</div></section>
                {copy.partner && <section className="border-t border-border py-14"><p className="discovery-eyebrow text-foreground">QUIÉN LO HACE POSIBLE</p><Link href={`/partners/${copy.partner.slug}`} className="mt-5 inline-flex min-h-11 items-center text-2xl font-extrabold text-foreground">{copy.partner.name}</Link><p className="mt-3 text-foreground-soft">JAKAWI conecta esta propuesta contigo.</p></section>}
                <section id="condiciones" className="border-t border-border py-14"><p className="discovery-eyebrow text-foreground">{copy.success ? 'CONDICIONES DEL RESULTADO' : (copy.failed || copy.expired ? 'CONDICIONES DEL DESBLOQUEO' : 'ANTES DE SUMARTE')}</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE NECESITAS SABER</h2><p className="mt-5 font-extrabold">{copy.stateLabel}</p>{copy.viewer.reason && <StatusBanner title={copy.action.label} description={copy.viewer.reason} className="mt-6"/>}<ul className="mt-6 space-y-4 leading-7 text-foreground-soft">{copy.conditions.map(condition => <li key={condition}>{condition}</li>)}</ul>{copy.locations.length > 0 && <p className="mt-5 leading-7">Dónde: {copy.locations.map(location => [location.name, location.city].filter(Boolean).join(' · ')).join(' / ')}</p>}{copy.terms && <details className="mt-7 border-y border-border py-5"><summary className="cursor-pointer font-extrabold">CONDICIONES COMPLETAS</summary><p className="mt-4 whitespace-pre-line leading-7">{copy.terms}</p></details>}<Link href="#condiciones" onClick={action.onClick} className="mt-6 inline-flex min-h-11 items-center font-bold text-foreground underline">Ver ficha del desbloqueo</Link></section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section data-marketing-surface="dark" aria-label="CTA final" className="bg-background py-16 text-foreground sm:py-24"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-foreground/70">{copy.stateLabel}</p><h2 className="mt-3 max-w-4xl text-4xl leading-tight font-extrabold uppercase sm:text-6xl">{copy.finalHeadline}</h2><p className="mt-5 text-foreground/75">{copy.title} · {number(copy.progress.current)} / {number(copy.progress.target)} compromisos{!copy.success && !copy.failed && !copy.expired && ` · Faltan ${number(copy.progress.remaining)}`}</p><div className="mt-8"><MarketingCTA action={action}/></div></div></section>
            {vsl && <VideoLandingAction {...videoAction} />}
        </main>
    </>;
}
