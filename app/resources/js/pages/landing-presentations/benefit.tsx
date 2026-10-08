import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingBenefits, MarketingCTA, MarketingFAQ, MarketingHero } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Props = {
    benefit: { slug:string; title:string; terms:string|null };
    presentation: { hero_alt:string|null };
    copy: {
        eyebrow:string;headline:string;subheadline:string;value:string;valueNote:string|null;description:string|null;partner:string;
        deadline:string|null;membership:string;rules:string[];finalHeadline:string;
        places:Array<{id:number;name:string;city:string|null;address:string|null;zone:string|null}>;
        benefits:Array<{title:string;description:string}>;steps:Array<{title:string;description:string}>;
        faq:Array<{question:string;answer:string}>;action:{href:string;label:string;kind:string};
    };
    heroUrl:string|null;nativeUrl:string;canonical:string;noindex:boolean;preview:boolean;
};

export default function BenefitLanding({ benefit,presentation,copy,heroUrl,nativeUrl,canonical,noindex,preview }:Props) {
    const sent=useRef(false);
    const disabled=preview||copy.action.kind==='unavailable';
    const action={...copy.action,disabled,href:disabled?'#condiciones':copy.action.href,onClick:(event:React.MouseEvent<HTMLAnchorElement>)=>{
        if(disabled){event.preventDefault();return;}
        if(copy.action.kind==='guest'){event.preventDefault();router.post(copy.action.href);return;}
        if(copy.action.kind==='membership'&&!sent.current){sent.current=true;trackJourneyIntent('benefit',benefit.slug);}
    }};
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        <main className="overflow-x-clip break-words">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                {preview&&<StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5"/>}
                <MarketingHero eyebrow={copy.eyebrow} title={copy.headline} description={copy.subheadline} action={action} aside={<div className="overflow-hidden rounded-[var(--radius-card)] bg-surface-muted shadow-featured">{heroUrl&&<JakawiImage src={heroUrl} alt={presentation.hero_alt||benefit.title} className="aspect-[4/3] w-full object-cover" loading="eager" priority/>}<div className="p-6"><p className="discovery-eyebrow text-brand">EN {copy.partner}</p><p className="mt-3 break-words text-3xl leading-tight font-extrabold">{copy.value}</p><p className="mt-4 text-sm text-foreground-soft">{copy.membership}</p>{copy.deadline&&<p className="mt-3 text-sm font-bold">Válido hasta el {copy.deadline}</p>}</div></div>}/>
                <section aria-label="Tu beneficio" className="rounded-[var(--radius-card)] bg-brand p-7 text-brand-foreground sm:p-10"><p className="discovery-eyebrow">TU BENEFICIO</p><h2 className="mt-3 break-words text-4xl leading-tight font-extrabold uppercase sm:text-5xl">{copy.value}</h2><p className="mt-4 font-bold">En {copy.partner}</p>{copy.description&&<p className="mt-4 max-w-3xl leading-7 whitespace-pre-line">{copy.description}</p>}{copy.valueNote&&<p className="mt-4 text-sm">{copy.valueNote}</p>}</section>
                <MarketingBenefits eyebrow="POR QUÉ APROVECHARLO" title="CONOCE LO QUE OBTIENES" items={copy.benefits}/>
                <section className="border-t border-border py-14 sm:py-20"><p className="discovery-eyebrow text-brand">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase sm:text-5xl">TU BENEFICIO EN TRES PASOS</h2><div className="mt-10 grid gap-7 md:grid-cols-3">{copy.steps.map((step,index)=><article key={step.title} className="border-t border-border pt-5"><span className="text-sm font-extrabold text-brand">0{index+1}</span><h3 className="mt-4 text-xl font-extrabold">{step.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{step.description}</p></article>)}</div></section>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">QUIÉN LO OFRECE · DÓNDE USARLO</p><h2 className="mt-3 text-3xl font-extrabold uppercase">{copy.partner}</h2><p className="mt-4 text-foreground-soft">Un beneficio presentado por JAKAWI.</p>{copy.places.length?<ul className="mt-6 divide-y divide-border">{copy.places.map(place=><li key={place.id} className="py-4"><p className="font-bold">{place.name}{place.city?` · ${place.city}`:''}</p><p className="mt-1 text-sm text-foreground-soft">{[place.zone,place.address].filter(Boolean).join(' · ')}</p></li>)}</ul>:<p className="mt-6">No hay sucursales aplicables publicadas.</p>}</section>
                <section id="condiciones" className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">CONDICIONES ESENCIALES</p><h2 className="mt-3 text-3xl font-extrabold uppercase">ANTES DE TU VISITA</h2>{copy.action.kind==='unavailable'&&<StatusBanner title={copy.action.label} description="Revisa las condiciones o explora otros beneficios." className="mt-6"/>}<ul className="mt-6 space-y-4 text-foreground-soft">{copy.rules.map(rule=><li key={rule} className="border-l-2 border-brand pl-4 leading-7 whitespace-pre-line">{rule}</li>)}</ul><details className="mt-7 border-y border-border py-5"><summary className="cursor-pointer font-extrabold">CONDICIONES COMPLETAS</summary><p className="mt-4 leading-7 whitespace-pre-line">{benefit.terms||'Consulta la vigencia, las sucursales y los requisitos indicados arriba.'}</p><Link href={nativeUrl} className="mt-4 inline-flex min-h-11 items-center font-bold text-brand underline">Ver ficha del beneficio</Link></details></section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section aria-label="CTA final" className="bg-foreground py-16 text-background sm:py-24"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-background/70">JAKAWI · {copy.partner}</p><h2 className="mt-3 max-w-4xl text-4xl leading-tight font-extrabold uppercase sm:text-6xl">{copy.finalHeadline}</h2><p className="mt-5 max-w-2xl text-background/75">{copy.value} · {copy.membership}</p><div className="mt-8"><MarketingCTA action={action}/></div></div></section>
        </main>
    </>;
}
