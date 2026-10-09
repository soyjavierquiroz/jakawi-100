import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingCTA, MarketingFAQ, MarketingHero } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Session = { id:number; date:string; time:string; place:string|null; address:string|null; city:string|null };
type Partner = { name:string; slug:string; role?:string };
type Props = {
    presentation:{hero_alt:string|null}; heroUrl:string|null; nativeUrl:string; canonical:string; noindex:boolean; preview:boolean;
    copy:{title:string;slug:string;experienceType:string|null;eyebrow:string;headline:string;subheadline:string;valueNote:string|null;description:string|null;terms:string|null;
        organizers:Partner[];partners:Partner[];prices:Array<{label:string;value:string}>;next:Session|null;sessions:Session[];sessionCount:number;membership:string;
        steps:Array<{title:string;description:string}>;faq:Array<{question:string;answer:string}>;finalHeadline:string;
        action:{label:string;href:string;kind:string;sessionId:number|null}};
};
export default function ExperienceLanding({presentation,heroUrl,nativeUrl,canonical,noindex,preview,copy}:Props) {
    const sent=useRef(false);
    const disabled=preview||copy.action.kind==='unavailable';
    const action={...copy.action,disabled,onClick:(event:React.MouseEvent<HTMLAnchorElement>)=>{
        if(disabled){event.preventDefault();return;}
        if(copy.action.kind==='guest'){event.preventDefault();router.post(copy.action.href,{experience_session_id:copy.action.sessionId});return;}
        if(copy.action.kind==='membership'&&!sent.current){sent.current=true;trackJourneyIntent('experience',copy.slug);}
    }};
    const place=(session:Session)=>[session.place,session.city].filter(Boolean).join(' · ');
    const facts=[copy.next&&{label:'PRÓXIMA FECHA',value:copy.next.date},copy.next&&{label:'HORA',value:copy.next.time},copy.next?.place&&{label:'LUGAR',value:place(copy.next)},copy.prices.length>0&&{label:'PRECIO',value:copy.prices.map(price=>`${price.label}: ${price.value}`).join(' · ')}].filter((fact):fact is {label:string;value:string}=>Boolean(fact));
    return <>
        <Head title={copy.headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        <main className="overflow-x-clip break-words font-discovery">
            <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                {preview&&<StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5"/>}
                <div className="mt-6 overflow-hidden rounded-[var(--radius-card)] bg-surface-muted sm:mt-10"><JakawiImage src={heroUrl} alt={presentation.hero_alt||copy.title} className="aspect-[4/3] w-full object-cover sm:aspect-[16/7]" fallbackClassName="aspect-[4/3] sm:aspect-[16/7] border-0 bg-surface-muted" loading="eager" priority/></div>
                <MarketingHero eyebrow={copy.eyebrow} title={copy.headline} description={copy.subheadline} action={action} aside={<div className="rounded-[var(--radius-card)] bg-surface-muted p-6"><p className="discovery-eyebrow text-brand">{copy.experienceType||'EXPERIENCIA'}</p><p className="mt-3 text-2xl font-extrabold">{copy.title}</p>{copy.next&&<p className="mt-4 font-bold">{copy.next.date} · {copy.next.time}<span className="mt-2 block">{place(copy.next)}</span></p>}<p className="mt-4 text-sm leading-6 text-foreground-soft">{copy.membership}</p></div>}/>
                {facts.length>0&&<section aria-label="Datos de la experiencia" className="grid grid-cols-2 gap-6 rounded-[var(--radius-card)] bg-brand p-6 text-brand-foreground md:grid-cols-4">{facts.map(fact=><div key={fact.label}><p className="discovery-eyebrow">{fact.label}</p><p className="mt-2 font-bold">{fact.value}</p></div>)}</section>}
                {copy.sessionCount>1&&<p className="mt-4 text-sm font-bold text-foreground-soft">Hay más horarios disponibles. Elige tu sesión en la ficha de la experiencia.</p>}
                <section className="py-14 sm:py-20"><p className="discovery-eyebrow text-brand">QUÉ VAS A VIVIR</p><h2 className="mt-3 text-3xl font-extrabold uppercase sm:text-5xl">{copy.title}</h2>{copy.description&&<p className="mt-6 max-w-3xl whitespace-pre-line leading-7 text-foreground-soft">{copy.description}</p>}{copy.valueNote&&<p className="mt-4 leading-7">{copy.valueNote}</p>}</section>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">CUÁNDO Y DÓNDE</p><h2 className="mt-3 text-3xl font-extrabold uppercase">TU PRÓXIMO PLAN</h2>{copy.sessions.length?<ul className="mt-6 divide-y divide-border">{copy.sessions.map(session=><li key={session.id} className="py-4"><p className="font-bold">{session.date} · {session.time}</p>{session.place&&<p className="mt-2 font-bold">{place(session)}</p>}{session.address&&<p className="mt-1 text-foreground-soft">{session.address}</p>}</li>)}</ul>:<p className="mt-6">No hay próximas fechas publicadas.</p>}{copy.sessionCount>copy.sessions.length&&<p className="mt-4">Consulta los demás horarios en la ficha.</p>}<Link href={nativeUrl} className="mt-4 inline-flex min-h-11 items-center font-bold text-brand underline">Ver fechas y reserva</Link></section>
                <section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">CÓMO FUNCIONA</p><h2 className="mt-3 text-3xl font-extrabold uppercase">DE TU PLAN A LA EXPERIENCIA</h2><div className="mt-10 grid gap-7 md:grid-cols-3">{copy.steps.map((step,index)=><article key={step.title} className="border-t border-border pt-5"><span className="font-extrabold text-brand">0{index+1}</span><h3 className="mt-4 text-xl font-extrabold">{step.title}</h3><p className="mt-3 leading-7 text-foreground-soft">{step.description}</p></article>)}</div></section>
                {copy.partners.length>0&&<section className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">QUIÉN LO HACE POSIBLE</p>{copy.partners.map(partner=><div key={`${partner.slug}-${partner.role}`} className="mt-5"><p className="text-sm text-foreground-soft">{partner.role==='organizer'?'Organiza':'Partner · '+partner.role}</p><Link href={`/partners/${partner.slug}`} className="inline-flex min-h-11 items-center text-2xl font-extrabold text-brand">{partner.name}</Link></div>)}<p className="mt-4 text-foreground-soft">JAKAWI conecta esta experiencia contigo.</p></section>}
                <section id="condiciones" className="border-t border-border py-14"><p className="discovery-eyebrow text-brand">ANTES DE IR</p><h2 className="mt-3 text-3xl font-extrabold uppercase">LO QUE NECESITAS SABER</h2>{copy.action.kind==='unavailable'&&<StatusBanner title={copy.action.label} description="Consulta la ficha para conocer el estado de la experiencia." className="mt-6"/>}<ul className="mt-6 space-y-4 leading-7 text-foreground-soft"><li>{copy.membership}</li>{copy.next&&<li>{copy.next.date} · {copy.next.time} · {place(copy.next)}{copy.next.address?` · ${copy.next.address}`:''}</li>}{copy.prices.map(price=><li key={price.label}>{price.label}: {price.value}</li>)}</ul>{copy.terms&&<details className="mt-7 border-y border-border py-5"><summary className="cursor-pointer font-extrabold">CONDICIONES COMPLETAS</summary><p className="mt-4 whitespace-pre-line leading-7">{copy.terms}</p></details>}</section>
                <MarketingFAQ items={copy.faq}/>
            </div>
            <section aria-label="CTA final" className="bg-foreground py-16 text-background sm:py-24"><div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><p className="discovery-eyebrow text-background/70">EXPERIENCIA JAKAWI</p><h2 className="mt-3 max-w-4xl text-4xl leading-tight font-extrabold uppercase sm:text-6xl">{copy.finalHeadline}</h2><p className="mt-5 text-background/75">{copy.title}</p><div className="mt-8"><MarketingCTA action={action}/></div></div></section>
        </main>
    </>;
}
