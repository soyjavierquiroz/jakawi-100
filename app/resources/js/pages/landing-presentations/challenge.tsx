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
    const allQualified = challengeProps.challenge.selection_type === 'ALL_QUALIFIED';
    const socialPost = challengeProps.challenge.evidence_type === 'SOCIAL_POST';
    const supportCopy = socialPost
        ? `${copy.mechanism.split('. ')[0]}. JAKAWI verifica si cumpliste el reto.`
        : copy.mechanism;
    const steps = socialPost ? [
        {title: 'PUBLICA', description: copy.mechanism.split('. ')[0] + '.'},
        {title: 'ENVÍA', description: 'Con tu cuenta y correo verificado, envía el enlace de tu publicación.'},
        {title: 'CALIFICA', description: copy.qualification},
    ] : copy.steps;
    const hasMedia = Boolean(vsl || challengeProps.heroUrl);
    const trackGrowthClick = useGrowthLandingClicks();
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        {challengeProps.preview&&<div className="mx-auto max-w-6xl px-4 pt-5"><StatusBanner title="VISTA PREVIA" description="Esta presentación aún no está disponible públicamente."/></div>}
        <main className="pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0" onClickCapture={trackGrowthClick}>
            <section data-marketing-surface="dark" className="bg-[var(--ink)] text-[var(--cream)]">
                <div className="mx-auto grid max-w-6xl gap-5 px-4 py-6 sm:px-6 sm:py-10 lg:grid-cols-2 lg:items-start lg:gap-x-12 lg:px-8 lg:py-14">
                    <div className="min-w-0 lg:sticky lg:top-8">
                        <p className="text-xs font-extrabold tracking-[0.18em] uppercase">{copy.eyebrow}</p>
                        <h1 className="mt-3 max-w-2xl text-[clamp(2rem,8.2vw,2.5rem)] leading-[1.06] font-extrabold tracking-[-0.035em] uppercase lg:text-[clamp(2.75rem,4vw,3.75rem)]">{copy.headline.endsWith('LA ROMPA?') ? <>{copy.headline.slice(0, -9)}<span className="text-[var(--marketing-emphasis)]">LA ROMPA?</span></> : copy.headline}</h1>
                        <p className="mt-3 max-w-xl text-base leading-6 text-[var(--cream)]/80">{supportCopy}</p>
                    </div>
                    <div className="min-w-0 lg:col-start-2">
                        {hasMedia && <div data-landing-media className="w-full">
                            {vsl ? <LandingVideo config={{...vsl, theme:'inherit'}}/> : <JakawiImage src={challengeProps.heroUrl!} alt={presentation.hero_alt || challengeProps.challenge.title} className="aspect-[4/3] w-full rounded-[var(--radius-card)] object-cover" loading="eager" priority/>}
                        </div>}
                        <div ref={heroCTA} className={hasMedia ? 'mt-4' : ''}><MarketingCTA action={primaryAction} location="hero" className="sm:w-full" /></div>
                        <p className="mt-3 text-sm leading-5 font-semibold">{copy.confidence}</p>
                        {copy.deadline && <p className="mt-2 text-xs leading-5 text-[var(--cream)]/70">Cierra el {formattedDate(copy.deadline)}</p>}
                    </div>
                </div>
            </section>
            <div data-marketing-surface="light">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <section className="py-7" aria-label="Premio del reto">
                        <div className="rounded-[var(--radius-card)] bg-[var(--marketing-reward)] p-5 text-[var(--marketing-reward-text)]">
                            <p className="discovery-eyebrow">PREMIO DEL RETO</p>
                            <h2 className="mt-2 text-xl leading-7 font-extrabold">{copy.reward}</h2>
                            {copy.rewardDetail && <p className="mt-2 text-sm leading-6">{copy.rewardDetail}</p>}
                            {copy.rewardNote && <p className="mt-2 text-sm leading-5">{copy.rewardNote}</p>}
                            <p className="mt-3 text-sm leading-5"><span aria-hidden="true">✓ </span>{copy.confidence}</p>
                        </div>
                    </section>
                <section className="border-t border-border py-10 sm:py-14"><p className="discovery-eyebrow text-foreground">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase sm:text-4xl"><span className="text-foreground">3 PASOS</span> PARA PARTICIPAR</h2><div className="mt-6 grid gap-5 md:grid-cols-3">{steps.map((step,index)=><article key={step.title} className="border-t border-border pt-5"><span className="inline-flex size-9 items-center justify-center rounded-full bg-brand text-sm font-extrabold text-brand-foreground">0{index+1}</span><h3 className="mt-2 text-xl font-extrabold uppercase">{step.title}</h3><p className="mt-2 leading-6 text-foreground-soft">{step.description}</p></article>)}</div></section>

                </div>
                <section data-marketing-surface="dark" id="mecanica" className="bg-[var(--ink)] py-10 text-[var(--cream)] sm:py-14">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <p className="discovery-eyebrow">RESULTADO DEL RETO</p>
                        <h2 className="mt-3 text-3xl font-extrabold uppercase text-foreground">{allQualified ? 'NO ES SORTEO' : challengeProps.challenge.selection_type === 'TOP_N' ? 'RANKING Y RESULTADO' : challengeProps.challenge.selection_type === 'FIRST_N' ? 'PRIMEROS EN CUMPLIR' : 'CÓMO SE DEFINE EL RESULTADO'}</h2>
                        {allQualified && <p className="mt-4 text-xl font-bold">Si cumples las reglas, calificas.</p>}
                        <p className="mt-3 max-w-3xl text-lg leading-7">{allQualified ? 'Todos los participantes que califican reciben el premio.' : copy.selection}</p>
                        <p className="mt-4 max-w-3xl text-sm leading-6 text-[var(--cream)]/75">{copy.qualification} {copy.membership}</p>
                        {challengeProps.slots && <p className="mt-4 font-bold">{challengeProps.slots.awarded} premios otorgados · {Math.max(0,challengeProps.slots.limit-challengeProps.slots.reserved)} disponibles</p>}
                    </div>
                </section>
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <section className="border-t border-border py-10 sm:py-14"><p className="discovery-eyebrow text-foreground">REGLAS ESENCIALES</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE DEBES SABER</h2><dl className="mt-8 grid gap-5 sm:grid-cols-2">{copy.rules.map(rule=><div key={rule.title} className="rounded-[var(--radius-card)] bg-surface-muted p-5"><dt className="font-extrabold">{rule.title}</dt><dd className="mt-2 text-foreground-soft">{rule.title==='Cierre'&&copy.deadline?formattedDate(copy.deadline):rule.description}</dd></div>)}</dl><details className="mt-7 border-t border-b border-border py-5"><summary className="cursor-pointer font-extrabold">REGLAS COMPLETAS</summary><div className="mt-4 space-y-3 text-sm leading-7"><p>{challengeProps.challenge.description}</p><p>{challengeProps.challenge.instructions}</p><p>{copy.qualification} {copy.selection} {copy.membership} {copy.review}</p>{challengeProps.challenge.allowed_platforms?.length>0&&<p>Plataformas: {challengeProps.challenge.allowed_platforms.join(', ')}</p>}{challengeProps.challenge.required_hashtags?.length>0&&<p>Etiquetas: {challengeProps.challenge.required_hashtags.join(' ')}</p>}{challengeProps.challenge.required_mentions?.length>0&&<p>Menciones: {challengeProps.challenge.required_mentions.join(' ')}</p>}<Link href={nativeUrl} className="font-bold text-foreground underline">Ver ficha y reglas del producto</Link></div></details></section>
                <section className="border-t border-border py-10 sm:py-14" aria-label="Tu participación"><p className="discovery-eyebrow text-foreground">TU PARTICIPACIÓN</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{participating?'SIGUE TU RETO':'PARTICIPA EN ESTE RETO'}</h2><Show {...challengeProps} embedded landingSlug={presentation.slug}/></section>
                <MarketingFAQ items={copy.faq}/>
                </div>
            </div>
            <section data-marketing-surface="dark" ref={finalCTA} className="bg-[var(--ink)] py-12 text-[var(--cream)] sm:py-16"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-[var(--cream)]/70">JAKAWI</p><h2 className="mt-3 max-w-4xl text-4xl font-extrabold uppercase sm:text-6xl">{participating ? 'SIGUE TU PARTICIPACIÓN' : copy.finalHeadline}</h2><p className="mt-5 max-w-2xl text-[var(--cream)]/75">{copy.reward} · {copy.selection}</p><div className="mt-8"><MarketingCTA action={primaryAction}/></div></div></section>
            {stickyVisible&&<div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom)+var(--pwa-install-clearance,0px))] shadow-featured backdrop-blur lg:hidden">
                <MarketingCTA action={primaryAction} location="body" />
            </div>}
        </main>
    </>;
}
