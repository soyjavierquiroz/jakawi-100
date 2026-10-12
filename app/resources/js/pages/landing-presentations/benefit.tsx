import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingCTA, MarketingFAQ } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Props = {
    vsl?: LandingVideoConfig | null;
    benefit: { slug:string; title:string; terms:string|null };
    presentation: { id?:number; slug?:string; hero_alt:string|null };
    copy: {
        eyebrow:string;headline:string;subheadline:string;value:string;valueNote:string|null;description:string|null;partner:string;
        deadline:string|null;membership:string;rules:string[];finalHeadline:string;
        places:Array<{id:number;name:string;city:string|null;address:string|null;zone:string|null}>;
        benefits:Array<{title:string;description:string}>;steps:Array<{title:string;description:string}>;
        faq:Array<{question:string;answer:string}>;action:{href:string;label:string;kind:string};
    };
    heroUrl:string|null;nativeUrl:string;canonical:string;noindex:boolean;preview:boolean;
};

export default function BenefitLanding({ vsl, benefit,presentation,copy,heroUrl,nativeUrl,canonical,noindex,preview }:Props) {
    const sent=useRef(false);
    const disabled=preview||copy.action.kind==='unavailable';
    const action={...copy.action,label:copy.action.kind==='guest'?'CONTINUAR CON MI CUENTA':copy.action.kind==='native' && copy.action.label==='USAR BENEFICIO'?'VER CÓMO CANJEAR':copy.action.label,disabled,href:disabled?'#condiciones':copy.action.href,onClick:(event:React.MouseEvent<HTMLAnchorElement>)=>{
        if(disabled){event.preventDefault();return;}
        if(copy.action.kind==='guest'){event.preventDefault();router.post(copy.action.href);return;}
        if(copy.action.kind==='membership'&&!sent.current){sent.current=true;trackJourneyIntent('benefit',benefit.slug);}
    }};
    const qaCoffee=presentation.id===2 && presentation.slug==='qa-benefit-marketing-demo-beneficio-01' && benefit.slug==='demo-beneficio-01' && copy.value==='Café de cortesía con tu brunch';
    const headline=qaCoffee?'Tu brunch, con café de cortesía.':copy.headline;
    const hasMedia=Boolean(vsl||heroUrl);
    const cities=[...new Set(copy.places.map(place=>place.city).filter(Boolean))].join(' · ');
    const facts=[cities&&{label:'Lugar',value:cities},copy.deadline&&{label:'Vigencia',value:copy.deadline}].filter((fact):fact is {label:string;value:string}=>Boolean(fact));
    const trackGrowthClick = useGrowthLandingClicks();
    return <>
        <Head title={headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        <main onClickCapture={trackGrowthClick} className="benefit-discovery-v1 overflow-x-clip break-words font-discovery" data-marketing-surface="light">
            <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                {preview&&<StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5"/>}
                <section aria-label="Oferta del beneficio" className={`grid gap-y-4 pt-7 pb-12 lg:gap-x-14 lg:pt-16 lg:pb-20 ${hasMedia?'lg:grid-cols-[0.9fr_1.1fr]':'max-w-4xl'}`}>
                    <div className="min-w-0 lg:pb-5">
                        <p className="text-sm font-extrabold tracking-widest uppercase">Beneficio en {copy.partner}</p>
                        <h1 className="mt-4 text-[clamp(2.65rem,9.5vw,4rem)] leading-[0.98] font-extrabold tracking-[-0.05em] uppercase">{qaCoffee?<>Tu brunch,<br/>con <span className="text-[var(--marketing-emphasis)]">café de cortesía.</span></>:headline}</h1>
                        <p className="mt-5 text-base leading-6 text-[var(--marketing-text-secondary)]">{qaCoffee?'Para miembros JAKAWI.':copy.subheadline}</p>
                    </div>
                    {hasMedia&&<div data-landing-media className="min-w-0 lg:col-start-2 lg:row-span-2 lg:self-center">{vsl?<LandingVideo config={{...vsl,theme:'inherit'}}/>:<JakawiImage src={heroUrl!} alt={presentation.hero_alt||benefit.title} className="aspect-[4/3] w-full object-cover lg:aspect-square" loading="eager" priority/>}</div>}
                    <div className="min-w-0 lg:col-start-1 lg:row-start-2">
                        <div aria-label="Valor del beneficio" className="border-t border-border pt-4"><p className="text-sm text-[var(--marketing-text-secondary)]">Qué obtienes</p><p className="mt-1 text-2xl font-extrabold leading-tight">{qaCoffee?'Café de cortesía':copy.value}</p>{copy.valueNote&&<p className="mt-2 text-base leading-6">{copy.valueNote}</p>}</div>
                        {facts.length>0&&<dl aria-label="Datos esenciales" className="mt-4 grid grid-cols-2 gap-4 border-y border-border py-3 text-sm">{facts.map(fact=><div key={fact.label}><dt className="text-[var(--marketing-text-secondary)]">{fact.label}</dt><dd className="mt-1 font-bold">{fact.value}</dd></div>)}</dl>}
                        <p className="mt-3 text-base leading-6 font-semibold">{qaCoffee?'Con tu brunch. Membresía activa requerida.':copy.membership}</p>
                        <div className="mt-4"><MarketingCTA action={action} location="hero" className="text-base sm:w-full"/></div>
                        <p className="mt-3 text-sm leading-5 text-[var(--marketing-text-secondary)]">{copy.action.kind==='guest'?'Continúa con tu cuenta JAKAWI. El canje requiere membresía activa.':copy.action.kind==='membership'?'Activa tu membresía para continuar.':'Continúa en la ficha para revisar cómo usarlo.'} El partner valida tu código.</p>
                    </div>
                </section>
                <section aria-label="Cómo usar" className="border-t border-border py-12 sm:py-16"><p className="text-sm font-bold tracking-widest">CÓMO USARLO</p><h2 className="mt-3 text-3xl font-extrabold tracking-tight uppercase sm:text-5xl">De tu cuenta al canje</h2><ol className="mt-8 grid gap-7 md:grid-cols-3">{copy.steps.map((step,index)=><li key={step.title} className="border-t border-border pt-5"><span className="text-sm font-bold">{String(index+1).padStart(2,'0')}</span><h3 className="mt-3 text-xl font-extrabold">{step.title}</h3><p className="mt-3 text-base leading-7 text-[var(--marketing-text-secondary)]">{step.description}</p></li>)}</ol></section>
                <section id="condiciones" className="border-t border-border py-12 sm:py-16 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14">
                    <div><h2 className="text-3xl font-extrabold tracking-tight uppercase sm:text-5xl">Antes de usarlo</h2>{copy.description&&<p className="mt-5 text-base leading-7 text-[var(--marketing-text-secondary)]">{copy.description}</p>}{copy.action.kind==='unavailable'&&<StatusBanner title={copy.action.label} description="Revisa el estado en la ficha del beneficio." className="mt-6"/>}</div>
                    <div className="mt-7 lg:mt-0"><h3 className="text-xl font-extrabold">{copy.partner}</h3>{copy.places.length?<ul className="mt-4 divide-y divide-border">{copy.places.map(place=><li key={place.id} className="py-4 text-base leading-6"><p className="font-bold">{place.name}{place.city?` · ${place.city}`:''}</p><p className="mt-1 text-[var(--marketing-text-secondary)]">{[place.zone,place.address].filter(Boolean).join(' · ')}</p></li>)}</ul>:<p className="mt-4">No hay sucursales aplicables publicadas.</p>}
                        <details className="border-y border-border py-5"><summary className="cursor-pointer text-base font-bold">Vigencia y requisitos</summary><ul className="mt-4 space-y-3 text-base leading-7 text-[var(--marketing-text-secondary)]">{copy.rules.map(rule=><li key={rule} className="whitespace-pre-line">{rule}</li>)}</ul></details>
                        {benefit.terms&&<details className="border-b border-border py-5"><summary className="cursor-pointer text-base font-bold">Condiciones completas</summary><p className="mt-4 whitespace-pre-line text-base leading-7 text-[var(--marketing-text-secondary)]">{benefit.terms}</p></details>}
                        <Link href={nativeUrl} className="mt-4 inline-flex min-h-11 items-center text-base font-bold underline">Ver ficha del beneficio</Link>
                    </div>
                </section>
                <div className="benefit-faq border-t border-border"><MarketingFAQ items={copy.faq}/></div>
            </div>
            <section data-marketing-surface="dark" aria-label="CTA final" className="bg-[var(--ink)] py-14 text-[var(--cream)] sm:py-20"><div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12"><h2 className="max-w-4xl text-4xl leading-tight font-extrabold tracking-tight uppercase sm:text-6xl">{qaCoffee?'Tu brunch merece ese café.':copy.finalHeadline}</h2><p className="mt-5 max-w-xl text-base leading-7 text-[var(--marketing-text-secondary)]">{copy.value} en {copy.partner}. {qaCoffee?'Con tu brunch. Membresía activa requerida.':copy.membership}</p><div className="mt-8"><MarketingCTA action={action} className="text-base"/></div></div></section>
        </main>
    </>;
}
