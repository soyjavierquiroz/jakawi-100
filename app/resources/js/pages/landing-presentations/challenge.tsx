import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import { Head, Link, usePage } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingCTA } from '@/components/marketing/primitives';
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
    // Editorial demo only: never replace the configured domain reward or rules.
    const commercialDemo = presentation.slug === 'qa-bafabo-marketing' && challengeProps.challenge.id === 1 && challengeProps.challenge.slug === 'reto-de-prueba-jakawi-bafabo';
    const demoDisclosure = 'Demo visual — premio ilustrativo, no canjeable.';
    const displayedReward = commercialDemo ? 'Cena para dos' : copy.reward;
    const confidence = commercialDemo ? 'Si cumples las reglas, calificas. Premio QA sin valor comercial.' : copy.confidence;
    const participating=Boolean(challengeProps.participation);
    const grant=challengeProps.grant;
    const qualified=challengeProps.participation?.qualification_status==='qualified';
    const membershipRequired=qualified&&challengeProps.challenge.reward_eligibility==='ACTIVE_MEMBERS'&&!challengeProps.isMember&&!grant;
    const closed=!challengeProps.canParticipate;
    const action=grant&&['granted','fulfilled'].includes(grant.status) ? {href:'#participar',label:commercialDemo ? 'VER MI PARTICIPACIÓN' : 'VER MI PREMIO'}
        : membershipRequired ? {href:`/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id=${challengeProps.challenge.id}`,label:'ACTIVAR MEMBRESÍA'}
        : participating ? {href:'#participar',label:'VER MI PARTICIPACIÓN'}
        : closed ? {href:'#mecanica',label:'VER RESULTADOS'}
        : {href:'#participar',label:auth.user?'PARTICIPAR EN EL RETO':'CREAR CUENTA Y PARTICIPAR'};
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
    const supportCopy = commercialDemo ? 'Publica un Reel mostrando un plan que valga la pena vivir en la ciudad. Usa #bafabo y envía tu enlace.' : socialPost
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
        <Head title={commercialDemo ? 'Muéstranos tu mejor plan en Cochabamba · Demo' : copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        {challengeProps.preview&&<div className="mx-auto max-w-6xl px-4 pt-5"><StatusBanner title="VISTA PREVIA" description="Esta presentación aún no está disponible públicamente."/></div>}
        <main className="challenge-marketing-v3 pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-0" onClickCapture={trackGrowthClick}>
            <section data-marketing-surface="dark" data-challenge-chapter="hero" className="bg-[var(--ink)] text-[var(--cream)]">
                <div className="mx-auto grid max-w-6xl gap-4 px-4 py-5 sm:px-6 sm:py-10 lg:grid-cols-2 lg:items-start lg:gap-x-12 lg:px-8 lg:pt-14 lg:pb-12">
                    <div className="min-w-0 lg:sticky lg:top-8">
                        <p className="text-xs font-extrabold tracking-[0.18em] uppercase">{commercialDemo ? 'RETO JAKAWI · #BAFABO' : copy.eyebrow}</p>
                        <h1 className="challenge-hook mt-3 max-w-2xl font-extrabold uppercase">{commercialDemo ? <>MUÉSTRANOS<br/>TU MEJOR PLAN<span className="block text-[var(--marketing-emphasis)]">EN COCHABAMBA</span></> : copy.headline.endsWith('LA ROMPA?') ? <>{copy.headline.slice(0, -9)}<span className="block text-[var(--marketing-emphasis)]">LA ROMPA?</span></> : copy.headline}</h1>
                        {commercialDemo && <div data-demo-reward className="mt-5 border-l-4 border-[var(--marketing-reward)] pl-4">
                            <p className="text-xs font-extrabold tracking-widest text-[var(--marketing-reward)]">PREMIO DEMO</p>
                            <p className="mt-1 text-2xl font-extrabold uppercase">CENA PARA DOS</p>
                            <p className="mt-2 text-sm leading-5 text-[var(--cream)]">{demoDisclosure}</p>
                        </div>}
                        <p className="mt-3 max-w-xl text-sm leading-5 text-[var(--marketing-text-secondary)] sm:text-base sm:leading-6">{supportCopy}</p>
                    </div>
                    <div className="min-w-0 lg:col-start-2">
                        {hasMedia && <div data-landing-media className="w-full">
                            {vsl ? <LandingVideo config={{...vsl, theme:'inherit'}}/> : <JakawiImage src={challengeProps.heroUrl!} alt={presentation.hero_alt || challengeProps.challenge.title} className="aspect-[4/3] w-full rounded-[var(--radius-card)] object-cover" loading="eager" priority/>}
                        </div>}
                        <div ref={heroCTA} className={hasMedia ? 'mt-4' : ''}><MarketingCTA action={primaryAction} location="hero" className="challenge-action sm:w-full" /></div>
                        <p className="mt-3 text-xs font-extrabold tracking-widest">PUBLICA · ENVÍA · CALIFICA</p>
                        <p className="mt-3 text-sm leading-5 font-semibold">{confidence}</p>
                        {copy.deadline && <p className="mt-2 text-xs leading-5 text-[var(--cream)]/70">Cierra el {formattedDate(copy.deadline)}</p>}
                    </div>
                </div>
            </section>
            <div data-marketing-surface="light">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <section className="challenge-offer py-8 sm:py-14" aria-label="Premio del reto">
                        <div className="grid gap-4 lg:grid-cols-[1fr_2fr] lg:gap-12">
                            <div><p className="inline-flex rounded-full bg-[var(--marketing-reward)] px-4 py-2 text-xs font-extrabold tracking-widest text-[var(--marketing-reward-text)]">{commercialDemo ? 'PREMIO DEMO' : 'PREMIO DEL RETO'}</p></div>
                            <div><h2 className="max-w-3xl text-3xl leading-[1.08] font-extrabold tracking-tight sm:text-5xl">{displayedReward}</h2>
                            {commercialDemo && <p className="mt-3 text-base leading-6 font-semibold">{demoDisclosure}</p>}
                            {copy.rewardDetail && <p className="mt-4 max-w-2xl leading-6 text-[var(--marketing-text-secondary)]">{copy.rewardDetail}</p>}
                            {copy.rewardNote && <p className="mt-3 text-sm leading-5 text-[var(--marketing-text-muted)]">{copy.rewardNote}</p>}
                            <p className="mt-5 border-t border-border pt-4 text-sm leading-5 font-semibold">{confidence}</p></div>
                        </div>
                    </section>
                <section data-challenge-chapter="steps" className="border-t border-border py-10 sm:py-16"><p className="discovery-eyebrow text-foreground">CÓMO FUNCIONA</p><h2 className="mt-3 text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl"><span className="text-foreground">3 PASOS</span> PARA PARTICIPAR</h2><div className="mt-8 grid gap-8 md:grid-cols-3 md:gap-10">{steps.map((step,index)=><article key={step.title} className="pt-2"><span className="inline-flex h-14 min-w-18 items-center justify-center rounded-[var(--radius-control)] bg-[var(--marketing-action)] px-3 text-3xl font-extrabold tracking-tight text-[var(--marketing-action-text)]">0{index+1}</span><h3 className="mt-4 text-3xl font-extrabold tracking-tight uppercase">{step.title}</h3><p className="mt-3 max-w-sm leading-6 text-[var(--marketing-text-secondary)]">{step.description}</p></article>)}</div></section>

                </div>
                <section data-marketing-surface="dark" id="mecanica" className="challenge-result bg-[var(--marketing-bg-dark)] py-12 text-[var(--cream)] sm:py-14">
                    <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                        <p className="discovery-eyebrow">RESULTADO DEL RETO</p>
                        <h2 className="mt-4 text-5xl leading-none font-extrabold tracking-tight uppercase text-foreground sm:text-7xl">{allQualified ? 'NO ES SORTEO' : challengeProps.challenge.selection_type === 'TOP_N' ? 'RANKING Y RESULTADO' : challengeProps.challenge.selection_type === 'FIRST_N' ? 'PRIMEROS EN CUMPLIR' : 'CÓMO SE DEFINE EL RESULTADO'}</h2>
                        {allQualified && <p className="mt-6 max-w-3xl text-3xl leading-tight font-extrabold tracking-tight sm:text-5xl">Si cumples las reglas, calificas.</p>}
                        <p className="mt-5 max-w-3xl text-lg leading-7 text-[var(--marketing-text-secondary)]">{allQualified ? commercialDemo ? 'Todos los participantes que califican reciben el premio QA configurado, sin valor comercial. La cena es ilustrativa y no canjeable.' : 'Todos los participantes que califican reciben el premio.' : copy.selection}</p>
                        <p className="mt-4 max-w-3xl text-sm leading-6 text-[var(--cream)]/75">{copy.qualification} {copy.membership}</p>
                        {challengeProps.slots && <p className="mt-4 font-bold">{challengeProps.slots.awarded} premios otorgados · {Math.max(0,challengeProps.slots.limit-challengeProps.slots.reserved)} disponibles</p>}
                    </div>
                </section>
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <section data-challenge-chapter="rules" className="py-12 sm:py-20"><p className="discovery-eyebrow text-foreground">REGLAS ESENCIALES</p><h2 className="mt-3 max-w-3xl text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl">LO QUE DEBES SABER</h2><dl className="mt-8 grid gap-x-12 sm:grid-cols-2">{copy.rules.map(rule=><div key={rule.title} className="border-t border-border py-6"><dt className="font-extrabold">{rule.title}</dt><dd className="mt-2 max-w-xl leading-7 text-[var(--marketing-text-secondary)]">{rule.title==='Cierre'&&copy.deadline?formattedDate(copy.deadline):rule.description}</dd></div>)}</dl><details className="mt-7 border-t border-b border-border py-5"><summary className="cursor-pointer font-extrabold">REGLAS COMPLETAS</summary><div className="mt-4 space-y-3 text-sm leading-7"><p>{challengeProps.challenge.description}</p><p>{challengeProps.challenge.instructions}</p><p>{copy.qualification} {copy.selection} {copy.membership} {copy.review}</p>{challengeProps.challenge.allowed_platforms?.length>0&&<p>Plataformas: {challengeProps.challenge.allowed_platforms.join(', ')}</p>}{challengeProps.challenge.required_hashtags?.length>0&&<p>Etiquetas: {challengeProps.challenge.required_hashtags.join(' ')}</p>}{challengeProps.challenge.required_mentions?.length>0&&<p>Menciones: {challengeProps.challenge.required_mentions.join(' ')}</p>}<Link href={nativeUrl} className="font-bold text-foreground underline">Ver ficha y reglas del producto</Link></div></details></section>
                <section className="border-t border-border py-10 sm:py-14" aria-label="Tu participación"><p className="discovery-eyebrow text-foreground">TU PARTICIPACIÓN</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{participating?'SIGUE TU RETO':'PARTICIPA EN ESTE RETO'}</h2><Show {...challengeProps} embedded landingSlug={presentation.slug}/></section>
                <section data-challenge-chapter="faq" className="border-t border-border py-12 sm:py-20"><p className="discovery-eyebrow">SIN DUDAS</p><h2 className="mt-3 text-4xl leading-none font-extrabold tracking-tight uppercase sm:text-6xl">Preguntas frecuentes</h2><div className="mt-8 divide-y divide-border border-y border-border">{(commercialDemo ? [{question:'¿La cena se puede canjear?', answer:demoDisclosure + ' Este reto QA no ofrece una cena real ni un premio comercial.'}, ...copy.faq] : copy.faq).map(item => <details key={item.question} className="group py-6"><summary className="flex cursor-pointer list-none items-start justify-between gap-6 text-lg font-bold marker:hidden">{item.question}<span aria-hidden="true" className="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xl text-foreground group-open:rotate-45">+</span></summary><p className="mt-4 max-w-3xl leading-7 text-[var(--marketing-text-secondary)]">{item.answer}</p></details>)}</div></section>
                </div>
            </div>
            <section data-marketing-surface="dark" ref={finalCTA} data-challenge-chapter="final" className="bg-[var(--ink)] py-12 text-[var(--cream)] sm:py-16"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-[var(--cream)]/70">JAKAWI</p><h2 className="mt-4 max-w-4xl text-5xl leading-[0.95] font-extrabold tracking-tight uppercase sm:text-7xl">{participating ? 'SIGUE TU PARTICIPACIÓN' : copy.finalHeadline}</h2><p className="mt-5 max-w-2xl text-[var(--cream)]/75">{commercialDemo ? demoDisclosure : `${copy.reward} · ${copy.selection}`}</p><div className="mt-8"><MarketingCTA action={primaryAction} className="challenge-action"/></div></div></section>
            {stickyVisible&&<div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom)+var(--pwa-install-clearance,0px))] shadow-featured backdrop-blur lg:hidden">
                <MarketingCTA action={primaryAction} location="body" className="challenge-action" />
            </div>}
        </main>
    </>;
}
