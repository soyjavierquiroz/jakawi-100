import { Head, Link, usePage } from '@inertiajs/react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import Show from '@/pages/social-challenges/show';
import type { ComponentProps } from 'react';

type Props = ComponentProps<typeof Show> & {
    presentation: { id:number; name:string; slug:string; default_scope:'NONE'|'GUESTS'|'ALL'; hero_alt:string|null };
    copy: { eyebrow:string; headline:string; subheadline:string; reward:string; rewardType:string; rewardNote:string|null; rewardDetail:string|null; deadline:string|null; qualification:string; selection:string; membership:string; review:string; benefits:Array<{title:string;description:string}>; steps:Array<{title:string;description:string}>; rules:Array<{title:string;description:string}>; faq:Array<{question:string;answer:string}>; finalHeadline:string };
    nativeUrl:string; canonical:string; noindex:boolean;
};

function formattedDate(value:string|null) { return value ? new Intl.DateTimeFormat('es-BO',{dateStyle:'long',timeStyle:'short'}).format(new Date(value)) : null; }

export default function ChallengeLanding({ presentation, copy, nativeUrl, canonical, noindex, ...challengeProps }:Props) {
    const {auth}=usePage().props as {auth:{user?:{id:number}|null}};
    const participating=Boolean(challengeProps.participation);
    const grant=challengeProps.grant;
    const qualified=challengeProps.participation?.qualification_status==='qualified';
    const membershipRequired=qualified&&challengeProps.challenge.reward_eligibility==='ACTIVE_MEMBERS'&&!challengeProps.isMember&&!grant;
    const closed=!challengeProps.canParticipate;
    const action=grant&&['granted','fulfilled'].includes(grant.status) ? {href:'#participar',label:'VER MI PREMIO'}
        : membershipRequired ? {href:`/membresia?journey=SOCIAL_CHALLENGE&action=PARTICIPATE&resource_id=${challengeProps.challenge.id}`,label:'ACTIVAR MEMBRESÍA'}
        : closed ? {href:'#mecanica',label:'VER RESULTADOS'}
        : participating ? {href:'#participar',label:'VER MI PROGRESO'}
        : {href:'#participar',label:auth.user?'QUIERO PARTICIPAR':'PARTICIPAR AHORA'};
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        {challengeProps.preview&&<div className="mx-auto max-w-6xl px-4 pt-5"><StatusBanner title="VISTA PREVIA" description="Esta presentación aún no está disponible públicamente."/></div>}
        <main>
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <MarketingHero eyebrow={copy.eyebrow} title={copy.headline} description={copy.subheadline} action={action} aside={<div className="overflow-hidden rounded-[var(--radius-card)] bg-surface-muted shadow-featured">{challengeProps.heroUrl&&<JakawiImage src={challengeProps.heroUrl} alt={presentation.hero_alt || challengeProps.challenge.title} className="aspect-[4/3] w-full object-cover" loading="eager" priority/>}<div className="p-6"><p className="discovery-eyebrow text-brand">PREMIO DEL RETO</p><p className="mt-2 text-2xl font-extrabold">{copy.reward}</p>{copy.deadline&&<p className="mt-3 text-sm text-muted-foreground">Cierra el {formattedDate(copy.deadline)}</p>}</div></div>}/>
                <section className="rounded-[var(--radius-card)] bg-foreground p-7 text-background sm:p-10" aria-label="Premio"><p className="text-xs font-extrabold tracking-[0.18em] text-background/70">TU PREMIO</p><h2 className="mt-3 text-4xl font-extrabold uppercase sm:text-5xl">{copy.reward}</h2>{copy.rewardDetail&&<p className="mt-4 max-w-2xl leading-7 text-background/80">{copy.rewardDetail}</p>}<p className="mt-4 max-w-2xl leading-7 text-background/80">{copy.selection}</p>{copy.rewardNote&&<p className="mt-4 text-sm text-background/70">{copy.rewardNote}</p>}</section>
                <MarketingBenefits eyebrow="POR QUÉ PARTICIPAR" title="UN RETO CON UN RESULTADO CLARO" items={copy.benefits}/>
                <section className="border-t border-border py-14 sm:py-20"><p className="discovery-eyebrow text-brand">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase sm:text-5xl">TRES PASOS PARA PARTICIPAR</h2><div className="mt-10 grid gap-7 md:grid-cols-3">{copy.steps.map((step,index)=><article key={step.title} className="border-t border-border pt-5"><span className="text-sm font-extrabold text-brand">0{index+1}</span><h3 className="mt-4 text-xl font-extrabold">{step.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{step.description}</p></article>)}</div></section>
                <section className="border-t border-border py-14" aria-label="Tu participación"><p className="discovery-eyebrow text-brand">TU PARTICIPACIÓN</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{participating?'SIGUE TU RETO':'PARTICIPA EN ESTE RETO'}</h2><Show {...challengeProps} embedded landingSlug={presentation.slug}/></section>
                <section id="mecanica" className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">MECÁNICA</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{challengeProps.challenge.selection_type==='TOP_N'?'RANKING Y RESULTADO':challengeProps.challenge.selection_type==='FIRST_N'?'PRIMEROS EN CUMPLIR':'CÓMO SE DEFINE EL RESULTADO'}</h2><p className="mt-5 max-w-3xl text-lg leading-8">{copy.qualification} {copy.selection}</p>{challengeProps.slots&&<p className="mt-4 font-bold">{challengeProps.slots.awarded} premios otorgados · {Math.max(0,challengeProps.slots.limit-challengeProps.slots.reserved)} disponibles</p>}</section>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">REGLAS ESENCIALES</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE DEBES SABER</h2><dl className="mt-8 grid gap-5 sm:grid-cols-2">{copy.rules.map(rule=><div key={rule.title} className="rounded-[var(--radius-card)] bg-surface-muted p-5"><dt className="font-extrabold">{rule.title}</dt><dd className="mt-2 text-foreground-soft">{rule.title==='Cierre'&&copy.deadline?formattedDate(copy.deadline):rule.description}</dd></div>)}</dl><details className="mt-7 border-t border-b border-border py-5"><summary className="cursor-pointer font-extrabold">REGLAS COMPLETAS</summary><div className="mt-4 space-y-3 text-sm leading-7"><p>{challengeProps.challenge.description}</p><p>{challengeProps.challenge.instructions}</p><p>{copy.qualification} {copy.selection} {copy.membership} {copy.review}</p>{challengeProps.challenge.allowed_platforms?.length>0&&<p>Plataformas: {challengeProps.challenge.allowed_platforms.join(', ')}</p>}{challengeProps.challenge.required_hashtags?.length>0&&<p>Etiquetas: {challengeProps.challenge.required_hashtags.join(' ')}</p>}{challengeProps.challenge.required_mentions?.length>0&&<p>Menciones: {challengeProps.challenge.required_mentions.join(' ')}</p>}<Link href={nativeUrl} className="font-bold text-brand underline">Ver ficha y reglas del producto</Link></div></details></section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section className="bg-foreground py-16 text-background sm:py-24"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-background/70">JAKAWI</p><h2 className="mt-3 max-w-4xl text-4xl font-extrabold uppercase sm:text-6xl">{copy.finalHeadline}</h2><p className="mt-5 max-w-2xl text-background/75">{copy.reward} · {copy.selection}</p><div className="mt-8"><MarketingCTA action={action}/></div></div></section>
        </main>
    </>;
}
