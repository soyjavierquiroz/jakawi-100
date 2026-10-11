import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import { Head, Link, usePage } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingCTA, MarketingFAQ } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import Show from '@/pages/social-challenges/show';
import { useEffect, useRef, useState, type ComponentProps } from 'react';

type Props = ComponentProps<typeof Show> & {
    vsl?: LandingVideoConfig | null;
    presentation: { id:number; name:string; slug:string; default_scope:'NONE'|'GUESTS'|'ALL'; hero_alt:string|null };
    copy: { eyebrow:string; headline:string; subheadline:string; mechanism:string; confidence:string; reward:string; rewardType:string; rewardNote:string|null; rewardDetail:string|null; deadline:string|null; qualification:string; selection:string; membership:string; review:string; benefits:Array<{title:string;description:string}>; steps:Array<{title:string;description:string}>; rules:Array<{title:string;description:string}>; faq:Array<{question:string;answer:string}>; finalHeadline:string };
    nativeUrl:string; canonical:string; noindex:boolean;
};

function formattedDate(value:string|null) { return value ? new Intl.DateTimeFormat('es-BO',{dateStyle:'long',timeStyle:'short',timeZone:'America/La_Paz'}).format(new Date(value)) : null; }

export default function ChallengeLanding({ vsl, presentation, copy, nativeUrl, canonical, noindex, ...challengeProps }:Props) {
    const {auth}=usePage().props as {auth:{user?:{id:number}|null}};
    const participating=Boolean(challengeProps.participation);
    const grant=challengeProps.grant;
    const qualified=challengeProps.participation?.qualification_status==='qualified';
    const membershipRequired=qualified&&challengeProps.challenge.reward_eligibility==='ACTIVE_MEMBERS'&&!challengeProps.isMember&&!grant;
    const closed=!challengeProps.canParticipate;
    const action=grant&&['granted','fulfilled'].includes(grant.status) ? {href:'#participar',label:'VER MI PREMIO'}
        : membershipRequired ? {href:`/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id=${challengeProps.challenge.id}`,label:'ACTIVAR MEMBRESÍA'}
        : closed ? {href:'#mecanica',label:'VER RESULTADOS'}
        : participating ? {href:'#participar',label:'VER MI PARTICIPACIÓN'}
        : {href:'#participar',label:auth.user?'PARTICIPAR EN EL RETO':'PARTICIPAR AHORA'};
    const heroCTA = useRef<HTMLDivElement>(null);
    const finalCTA = useRef<HTMLElement>(null);
    const [stickyVisible, setStickyVisible] = useState(false);
    useEffect(() => {
        const update = () => {
            const hero = heroCTA.current?.getBoundingClientRect();
            const final = finalCTA.current?.getBoundingClientRect();
            setStickyVisible(Boolean(hero && final && hero.bottom < 0 && final.top >= window.innerHeight));
        };
        const observer = new IntersectionObserver(update);
        if (heroCTA.current) observer.observe(heroCTA.current);
        if (finalCTA.current) observer.observe(finalCTA.current);
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
        return () => { observer.disconnect(); window.removeEventListener('scroll', update); window.removeEventListener('resize', update); };
    }, []);
    const primaryAction = {...action, kind: membershipRequired ? 'membership' : (!participating && !closed ? 'participate' : 'other')};
    const hasMedia = Boolean(vsl || challengeProps.heroUrl);
    const trackGrowthClick = useGrowthLandingClicks();
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        {challengeProps.preview&&<div className="mx-auto max-w-6xl px-4 pt-5"><StatusBanner title="VISTA PREVIA" description="Esta presentación aún no está disponible públicamente."/></div>}
        <main className="pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0" onClickCapture={trackGrowthClick}>
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <section className={`grid gap-6 py-7 sm:py-10 lg:items-center lg:gap-12 ${hasMedia ? 'lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]' : ''}`}>
                    <div className="min-w-0">
                        <p className="text-xs font-extrabold tracking-[0.18em] text-brand uppercase">{copy.eyebrow}</p>
                        <h1 className="mt-3 max-w-2xl text-[clamp(2rem,8.6vw,2.75rem)] leading-[1.04] font-extrabold tracking-[-0.045em] uppercase lg:text-[clamp(2.75rem,4vw,3.75rem)]">{copy.headline}</h1>
                        <p className="mt-4 max-w-xl text-base leading-6 text-foreground-soft">{copy.mechanism}</p>
                        <div ref={heroCTA} className="mt-5"><MarketingCTA action={primaryAction} location="hero" /></div>
                        <p className="mt-3 max-w-xl text-sm leading-5 font-semibold">{copy.confidence}</p>
                        {copy.deadline&&<p className="mt-2 text-sm text-muted-foreground">Cierra el {formattedDate(copy.deadline)}</p>}
                        <div className="mt-5 border-l-4 border-brand pl-4" aria-label="Premio del reto">
                            <p className="discovery-eyebrow text-brand">PREMIO DEL RETO</p>
                            <p className="mt-1 text-lg leading-6 font-extrabold">{copy.reward}</p>
                            {copy.rewardDetail&&<p className="mt-2 text-sm leading-6 text-foreground-soft">{copy.rewardDetail}</p>}
                            {copy.rewardNote&&<p className="mt-2 text-sm leading-5 text-muted-foreground">{copy.rewardNote}</p>}
                        </div>
                    </div>
                    {hasMedia&&<div className="mx-auto w-full max-w-[22rem] overflow-hidden rounded-[var(--radius-card)] bg-surface-muted shadow-featured lg:max-w-[24rem]">
                        {vsl ? <LandingVideo config={{...vsl, theme:'inherit'}}/> : <JakawiImage src={challengeProps.heroUrl!} alt={presentation.hero_alt || challengeProps.challenge.title} className="aspect-[4/3] w-full object-cover" loading="eager" priority/>}
                    </div>}
                </section>
                <section className="border-t border-border py-10 sm:py-14"><p className="discovery-eyebrow text-brand">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase sm:text-4xl">TRES PASOS PARA PARTICIPAR</h2><div className="mt-6 grid gap-5 md:grid-cols-3">{copy.steps.map((step,index)=><article key={step.title} className="border-t border-border pt-5"><span className="text-sm font-extrabold text-brand">0{index+1}</span><h3 className="mt-2 text-xl font-extrabold uppercase">{step.title}</h3><p className="mt-2 leading-6 text-foreground-soft">{step.description}</p></article>)}</div></section>

                <section id="mecanica" className="border-t border-border py-10 sm:py-14"><p className="discovery-eyebrow text-brand">MECÁNICA</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{challengeProps.challenge.selection_type==='TOP_N'?'RANKING Y RESULTADO':challengeProps.challenge.selection_type==='FIRST_N'?'PRIMEROS EN CUMPLIR':'CÓMO SE DEFINE EL RESULTADO'}</h2><p className="mt-5 max-w-3xl text-lg leading-8">{copy.qualification} {copy.selection}</p>{challengeProps.slots&&<p className="mt-4 font-bold">{challengeProps.slots.awarded} premios otorgados · {Math.max(0,challengeProps.slots.limit-challengeProps.slots.reserved)} disponibles</p>}</section>
                <section className="border-t border-border py-10 sm:py-14"><p className="discovery-eyebrow text-brand">REGLAS ESENCIALES</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE DEBES SABER</h2><dl className="mt-8 grid gap-5 sm:grid-cols-2">{copy.rules.map(rule=><div key={rule.title} className="rounded-[var(--radius-card)] bg-surface-muted p-5"><dt className="font-extrabold">{rule.title}</dt><dd className="mt-2 text-foreground-soft">{rule.title==='Cierre'&&copy.deadline?formattedDate(copy.deadline):rule.description}</dd></div>)}</dl><details className="mt-7 border-t border-b border-border py-5"><summary className="cursor-pointer font-extrabold">REGLAS COMPLETAS</summary><div className="mt-4 space-y-3 text-sm leading-7"><p>{challengeProps.challenge.description}</p><p>{challengeProps.challenge.instructions}</p><p>{copy.qualification} {copy.selection} {copy.membership} {copy.review}</p>{challengeProps.challenge.allowed_platforms?.length>0&&<p>Plataformas: {challengeProps.challenge.allowed_platforms.join(', ')}</p>}{challengeProps.challenge.required_hashtags?.length>0&&<p>Etiquetas: {challengeProps.challenge.required_hashtags.join(' ')}</p>}{challengeProps.challenge.required_mentions?.length>0&&<p>Menciones: {challengeProps.challenge.required_mentions.join(' ')}</p>}<Link href={nativeUrl} className="font-bold text-brand underline">Ver ficha y reglas del producto</Link></div></details></section>
                <section className="border-t border-border py-10 sm:py-14" aria-label="Tu participación"><p className="discovery-eyebrow text-brand">TU PARTICIPACIÓN</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{participating?'SIGUE TU RETO':'PARTICIPA EN ESTE RETO'}</h2><Show {...challengeProps} embedded landingSlug={presentation.slug}/></section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section ref={finalCTA} className="bg-foreground py-12 text-background sm:py-16"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-background/70">JAKAWI</p><h2 className="mt-3 max-w-4xl text-4xl font-extrabold uppercase sm:text-6xl">{participating ? 'SIGUE TU PARTICIPACIÓN' : copy.finalHeadline}</h2><p className="mt-5 max-w-2xl text-background/75">{copy.reward} · {copy.selection}</p><div className="mt-8"><MarketingCTA action={primaryAction}/></div></div></section>
            {stickyVisible&&<div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom)+var(--pwa-install-clearance,0px))] shadow-featured backdrop-blur lg:hidden">
                <MarketingCTA action={primaryAction} location="body" />
            </div>}
        </main>
    </>;
}
