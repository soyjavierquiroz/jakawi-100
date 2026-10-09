import { Head, Link, router } from '@inertiajs/react';
import { useRef, type MouseEvent } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Item = { title: string; description: string };
type Props = {
    nativeUrl: string; canonical: string; noindex: boolean; preview: boolean;
    copy: {
        title: string; slug: string; eyebrow: string; headline: string; subheadline: string;
        reward: string; description: string | null; valueNote: string | null; heroUrl: string | null; heroAlt: string;
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

export default function UnlockLanding({ nativeUrl, canonical, noindex, preview, copy }: Props) {
    const intentSent = useRef(false);
    const disabled = preview || copy.action.kind === 'unavailable';
    const action = { ...copy.action, disabled, onClick: (event: MouseEvent<HTMLAnchorElement>) => {
        if (disabled) { event.preventDefault(); return; }
        // Guest POST uses the existing signup continuation; authenticated commitments stay in Product Detail.
        if (copy.action.kind === 'guest') { event.preventDefault(); router.post(copy.action.href); return; }
        if (copy.action.kind === 'membership' && !intentSent.current) {
            intentSent.current = true;
            trackJourneyIntent('unlock', copy.slug);
        }
    } };
    const number = (value: number) => new Intl.NumberFormat('es-BO', { maximumFractionDigits: 1 }).format(value);
    const heroDescription = `${copy.subheadline} ${number(copy.progress.current)} / ${number(copy.progress.target)} compromisos${!copy.success && !copy.failed && !copy.expired ? ` · Faltan ${number(copy.progress.remaining)}` : ''}.`;
    const progress = <section aria-label="Progreso real del desbloqueo" className="rounded-[var(--radius-card)] bg-surface-muted p-6 sm:p-8">
        <p className="discovery-eyebrow text-brand">{copy.stateLabel}</p>
        {copy.progress.percent !== null && <p className="mt-4 text-6xl font-extrabold tracking-tight sm:text-7xl">{number(copy.progress.percent)}<span className="text-3xl">%</span></p>}
        {copy.progress.percent !== null && <div role="progressbar" aria-label="Compromisos hacia la meta" aria-valuemin={0} aria-valuemax={copy.progress.target} aria-valuenow={Math.min(copy.progress.current, copy.progress.target)} aria-valuetext={`${number(copy.progress.current)} de ${number(copy.progress.target)} compromisos`} className="mt-5 h-5 overflow-hidden rounded-full bg-border"><div className="h-full rounded-full bg-brand" style={{ width: `${Math.min(100, copy.progress.percent)}%` }}/></div>}
        <dl className="mt-6 grid grid-cols-3 gap-3">
            {([{ label: 'ACTUAL', value: copy.progress.current }, { label: 'META', value: copy.progress.target }, { label: 'FALTA', value: copy.progress.remaining }]).map(fact => <div key={fact.label} className="min-w-0"><dt className="text-xs font-extrabold tracking-wide">{fact.label}</dt><dd className="mt-2 break-all text-2xl font-extrabold sm:text-3xl">{number(fact.value)}</dd></div>)}
        </dl>
        <p className="mt-4 text-sm text-foreground-soft">Compromisos reales hacia la meta.</p>
        {copy.deadline && <p className="mt-4 text-sm font-bold">Plazo de compromisos: {copy.deadline}</p>}
    </section>;
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/><meta name="description" content={copy.subheadline}/>{noindex && <meta name="robots" content="noindex,follow"/>}</Head>
        <main className="overflow-x-clip break-words font-discovery">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                {preview && <StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5"/>}
                <div className="mt-6 overflow-hidden rounded-[var(--radius-card)] bg-surface-muted sm:mt-10"><JakawiImage src={copy.heroUrl} alt={copy.heroAlt} className="aspect-[4/3] w-full object-cover sm:aspect-[16/7]" fallbackClassName="aspect-[4/3] sm:aspect-[16/7] border-0 bg-surface-muted" loading="eager" priority/></div>
                <MarketingHero eyebrow={copy.eyebrow} title={copy.headline} description={heroDescription} action={action} aside={progress}/>
                <section className="border-t border-border py-14 sm:py-20"><p className="discovery-eyebrow text-brand">LO QUE DESBLOQUEAMOS</p><h2 className="mt-3 max-w-3xl text-3xl font-extrabold uppercase sm:text-5xl">{copy.title}</h2><p className="mt-6 max-w-3xl whitespace-pre-line leading-7 text-foreground-soft">{copy.reward}</p>{copy.offers.length > 0 && <dl className="mt-7 grid gap-6 sm:grid-cols-2">{copy.offers.map(offer => <div key={offer.label} className="rounded-[var(--radius-card)] bg-surface-muted p-6"><dt className="discovery-eyebrow text-brand">{offer.label}</dt><dd className="mt-3 whitespace-pre-line text-lg font-bold">{offer.value}</dd></div>)}</dl>}{copy.valueNote && <p className="mt-5 leading-7">{copy.valueNote}</p>}</section>
                <MarketingBenefits eyebrow="POR QUÉ PARTICIPAR" title={copy.success ? 'LA META SE LOGRÓ' : (copy.failed || copy.expired ? 'EL RESULTADO' : 'HAGAMOS QUE SUCEDA')} items={copy.benefits}/>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase">DE LA PROPUESTA AL RESULTADO</h2><div className="mt-10 grid gap-7 md:grid-cols-3">{copy.steps.map((step, index) => <article key={step.title} className="border-t border-border pt-5"><span className="font-extrabold text-brand">0{index + 1}</span><h3 className="mt-4 text-xl font-extrabold">{step.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{step.description}</p></article>)}</div></section>
                {copy.partner && <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">QUIÉN LO HACE POSIBLE</p><Link href={`/partners/${copy.partner.slug}`} className="mt-5 inline-flex min-h-11 items-center text-2xl font-extrabold text-brand">{copy.partner.name}</Link><p className="mt-3 text-foreground-soft">JAKAWI conecta esta propuesta contigo.</p></section>}
                <section id="condiciones" className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">{copy.success ? 'CONDICIONES DEL RESULTADO' : (copy.failed || copy.expired ? 'CONDICIONES DEL DESBLOQUEO' : 'ANTES DE SUMARTE')}</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE NECESITAS SABER</h2><p className="mt-5 font-extrabold">{copy.stateLabel}</p>{copy.viewer.reason && <StatusBanner title={copy.action.label} description={copy.viewer.reason} className="mt-6"/>}<ul className="mt-6 space-y-4 leading-7 text-foreground-soft">{copy.conditions.map(condition => <li key={condition}>{condition}</li>)}</ul>{copy.locations.length > 0 && <p className="mt-5 leading-7">Dónde: {copy.locations.map(location => [location.name, location.city].filter(Boolean).join(' · ')).join(' / ')}</p>}{copy.terms && <details className="mt-7 border-y border-border py-5"><summary className="cursor-pointer font-extrabold">CONDICIONES COMPLETAS</summary><p className="mt-4 whitespace-pre-line leading-7">{copy.terms}</p></details>}<Link href={nativeUrl} className="mt-6 inline-flex min-h-11 items-center font-bold text-brand underline">Ver ficha del desbloqueo</Link></section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section aria-label="CTA final" className="bg-foreground py-16 text-background sm:py-24"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-background/70">{copy.stateLabel}</p><h2 className="mt-3 max-w-4xl text-4xl leading-tight font-extrabold uppercase sm:text-6xl">{copy.finalHeadline}</h2><p className="mt-5 text-background/75">{copy.title} · {number(copy.progress.current)} / {number(copy.progress.target)} compromisos{!copy.success && !copy.failed && !copy.expired && ` · Faltan ${number(copy.progress.remaining)}`}</p><div className="mt-8"><MarketingCTA action={action}/></div></div></section>
        </main>
    </>;
}
