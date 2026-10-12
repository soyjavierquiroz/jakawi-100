import { LandingVideo, type LandingVideoConfig } from '@/components/media/jakawi-vsl-player/LandingVideo';
import { useGrowthLandingClicks } from '@/lib/growth-landing-analytics';
import { Head, Link, router } from '@inertiajs/react';
import { useRef } from 'react';
import { JakawiImage } from '@/components/jakawi-image';
import { MarketingCTA, MarketingFAQ } from '@/components/marketing/primitives';
import { StatusBanner } from '@/components/state-panel';
import { trackJourneyIntent } from '@/lib/journey-analytics';

type Session = { id:number; date:string; time:string; endTime?:string|null; capacity?:number|null; place:string|null; address:string|null; city:string|null };
type Partner = { name:string; slug:string; role?:string };
type Props = {
    vsl?: LandingVideoConfig | null;
    presentation:{id?:number;slug?:string;hero_alt:string|null}; heroUrl:string|null; nativeUrl:string; canonical:string; noindex:boolean; preview:boolean;
    copy:{title:string;slug:string;experienceType:string|null;durationMinutes?:number|null;reservationMethod?:string;eyebrow:string;headline:string;subheadline:string;valueNote:string|null;description:string|null;terms:string|null;
        organizers:Partner[];partners:Partner[];prices:Array<{label:string;value:string}>;next:Session|null;sessions:Session[];sessionCount:number;membership:string;
        steps:Array<{title:string;description:string}>;faq:Array<{question:string;answer:string}>;finalHeadline:string;
        action:{label:string;href:string;kind:string;sessionId:number|null}};
};

// This editorial treatment belongs to the inspected QA presentation, not every event.
// The duration-led offer requires the inspected duration and city; domain actions remain authoritative.
export default function ExperienceLanding({vsl,presentation,heroUrl,nativeUrl,canonical,noindex,preview,copy}:Props) {
    const qaSunset = presentation.id === 3 && presentation.slug === 'qa-experience-marketing-sunset-jakawi' && copy.slug === 'demo-sunset-jakawi';
    const sunset = qaSunset && copy.durationMinutes === 180 && copy.next?.city === 'Cochabamba';
    const externalReservation=Boolean(copy.reservationMethod && copy.reservationMethod !== 'jakawi');
    const sent=useRef(false);
    const disabled=preview||copy.action.kind==='unavailable';
    const action={...copy.action,label:copy.action.kind==='native' && ['QUIERO IR','RESERVAR'].includes(copy.action.label)?'VER FECHAS Y RESERVAR':copy.action.label,disabled,onClick:(event:React.MouseEvent<HTMLAnchorElement>)=>{
        if(disabled){event.preventDefault();return;}
        if(copy.action.kind==='guest'){event.preventDefault();router.post(copy.action.href,{experience_session_id:copy.action.sessionId});return;}
        if(copy.action.kind==='membership'&&!sent.current){sent.current=true;trackJourneyIntent('experience',copy.slug);}
    }};
    const place=(session:Session)=>[session.place,session.city].filter(Boolean).join(' · ');
    const hasMedia=Boolean(vsl||heroUrl);
    const trackGrowthClick = useGrowthLandingClicks();
    const headline=sunset?'3 horas para vivir Cochabamba desde arriba.':copy.headline;
    const promise=sunset?`Música suave, vista a la ciudad y un atardecer compartido${copy.next?.place?` en ${copy.next.place}`:''}.`:copy.subheadline;
    const compactDate=(date:string)=>{const [day,month]=date.split('/');const name=['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'][Number(month)-1];return name?`${Number(day)} ${name}`:date;};
    const priceLabel=(value:string)=>value.replace(/\.00\b/g,'');
    // Preserve the reservation condition without promoting unverified scarcity.
    const terms=qaSunset && copy.terms==='Cupo limitado; reserva previa requerida.'?'Reserva previa requerida.':copy.terms;
    const points=sunset?[
        {title:'Música suave',description:'El ritmo de una tarde compartida.'},
        {title:'Vista a la ciudad',description:'Un encuentro en altura.'},
        {title:'Atardecer compartido',description:'Una tarde para encontrarnos.'},
    ]:[];
    return <>
        <Head title={headline}><link rel="canonical" href={canonical}/>{noindex&&<meta name="robots" content="noindex,follow"/>}</Head>
        <main onClickCapture={trackGrowthClick} className="experience-marketing-v3 overflow-x-clip break-words font-discovery" data-marketing-surface="light">
            <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                {preview&&<StatusBanner title="VISTA PREVIA" description="Las acciones están desactivadas en esta vista." className="mt-5"/>}
                <section aria-label="La experiencia" className={`experience-hero grid gap-y-4 pt-7 pb-12 sm:pt-10 sm:pb-16 lg:gap-x-14 lg:gap-y-0 lg:pt-16 lg:pb-20 ${hasMedia?'lg:grid-cols-[0.9fr_1.1fr]':'lg:max-w-4xl'}`}>
                    <div className="min-w-0 lg:self-end lg:pb-8">
                        <p className="text-sm font-extrabold tracking-[0.14em] uppercase">{sunset?'SUNSET JAKAWI':copy.eyebrow}{!sunset&&copy.next?.city&&<span className="block mt-1 text-[var(--marketing-text-secondary)]">{copy.next.city}</span>}</p>
                        <h1 className="mt-4 text-[clamp(2.65rem,9.5vw,5rem)] lg:text-[4rem] leading-[0.98] font-extrabold tracking-[-0.055em] uppercase">{sunset?<>3 horas para vivir<span className="mt-1 block"><span className="text-[var(--marketing-emphasis)]">Cochabamba</span> desde arriba.</span></>:copy.headline}</h1>
                        <p className="mt-5 max-w-lg text-base leading-6 text-[var(--marketing-text-secondary)] lg:text-lg lg:leading-7">{promise}</p>
                    </div>
                    {hasMedia&&<div data-landing-media className="min-w-0 lg:col-start-2 lg:row-span-2 lg:self-center">{vsl?<LandingVideo config={{...vsl,theme:'inherit'}}/>:<JakawiImage src={heroUrl!} alt={presentation.hero_alt||copy.title} className="aspect-[4/3] w-full object-cover lg:aspect-square" loading="eager" priority/>}</div>}
                    <div className="min-w-0 lg:col-start-1 lg:row-start-2">
                        {copy.next&&<dl aria-label="Datos esenciales" className="grid grid-cols-[0.7fr_1.1fr_1.2fr] gap-3 border-y border-border py-3 text-sm leading-5">
                            <div><dt className="text-[var(--marketing-text-secondary)]">Fecha</dt><dd className="mt-1 font-bold">{compactDate(copy.next.date)}</dd></div>
                            <div><dt className="text-[var(--marketing-text-secondary)]">Hora</dt><dd className="mt-1 font-bold">{copy.next.time}{copy.next.endTime?`–${copy.next.endTime}`:''}</dd></div>
                            <div><dt className="text-[var(--marketing-text-secondary)]">Lugar</dt><dd className="mt-1 font-bold">{copy.next.place||copy.next.city}</dd></div>
                        </dl>}
                        {copy.prices.length>0&&<dl aria-label="Precios" className="mt-4 grid grid-cols-2 gap-x-5 gap-y-4">{copy.prices.map((price,index)=><div key={price.label} className={index?'border-l border-border pl-5':''}><dt className="text-sm leading-5 text-[var(--marketing-text-secondary)]">{price.label}</dt><dd className="mt-1 text-3xl leading-tight font-extrabold tracking-tight">{priceLabel(price.value)}</dd></div>)}</dl>}
                        <div className="mt-4"><MarketingCTA action={action} location="hero" className="text-base sm:w-full"/></div>
                        <div data-reservation-explanation className="mt-3 space-y-1 text-sm leading-5 text-[var(--marketing-text-secondary)]">{externalReservation?<><p>La reserva continúa en la ficha y se gestiona con el partner.</p><p className="font-semibold text-foreground">No necesitas membresía para asistir.</p></>:<p>{copy.membership}</p>}</div>
                    </div>
                </section>
                <section aria-label="Lo que vas a vivir" className="border-t border-border py-12 sm:py-20 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14">
                    <div><h2 className="max-w-xl text-4xl leading-[1.04] font-extrabold tracking-tight uppercase sm:text-6xl">{sunset?'Por qué este plan':copy.title}</h2></div>
                    <div className="mt-7 lg:mt-0">{!sunset&&copy.description&&<p className="max-w-xl whitespace-pre-line text-lg leading-7 text-[var(--marketing-text-secondary)]">{copy.description}</p>}{points.length>0&&<div className="divide-y divide-border">{points.map((point,index)=><article key={point.title} className="flex gap-5 py-5"><span className="pt-1 text-sm font-bold text-[var(--marketing-text-secondary)]">0{index+1}</span><div><h3 className="text-xl font-extrabold">{point.title}</h3><p className="mt-2 text-base leading-6 text-[var(--marketing-text-secondary)]">{point.description}</p></div></article>)}</div>}{copy.valueNote&&<p className="mt-6 text-base leading-7">{copy.valueNote}</p>}</div>
                </section>
                <section id="condiciones" className="border-t border-border py-12 sm:py-16 lg:grid lg:grid-cols-[0.9fr_1.1fr] lg:gap-14">
                    <div><p className="text-sm font-extrabold tracking-widest">PREPARA TU PLAN</p><h2 className="mt-4 text-3xl leading-tight font-extrabold tracking-tight uppercase sm:text-5xl">Antes de reservar</h2><p className="mt-5 text-base leading-7 text-[var(--marketing-text-secondary)]">Revisa los datos de la sesión y las condiciones antes de continuar.</p>{copy.action.kind==='unavailable'&&<StatusBanner title={copy.action.label} description="Consulta la ficha para conocer el estado de la experiencia." className="mt-6"/>}</div>
                    <div className="mt-7 lg:mt-0">
                        <div><h3 className="text-lg font-extrabold">Cuándo y dónde</h3>{copy.sessions.length?<ul className="mt-3 divide-y divide-border">{copy.sessions.map(session=><li key={session.id} className="py-4 text-base leading-7"><p className="font-bold">{session.date} · {session.time}{session.endTime?`–${session.endTime}`:''}</p><p>{place(session)}</p>{session.address&&<p className="text-[var(--marketing-text-secondary)]">{session.address}</p>}{session.capacity!=null&&<p className="text-sm text-[var(--marketing-text-secondary)]">Capacidad de la sesión: {session.capacity} personas.</p>}</li>)}</ul>:<p className="mt-4">No hay próximas fechas publicadas.</p>}{copy.sessionCount>copy.sessions.length&&<p className="mt-4">Consulta los demás horarios en la ficha.</p>}</div>
                        <details className="border-y border-border py-5"><summary className="cursor-pointer text-base font-bold">Cómo reservar</summary><ol className="mt-4 space-y-5">{copy.steps.map(step=><li key={step.title}><h4 className="font-bold">{step.title}</h4><p className="mt-1 text-base leading-7 text-[var(--marketing-text-secondary)]">{step.description}</p></li>)}</ol></details>
                        {terms&&<details className="border-b border-border py-5"><summary className="cursor-pointer text-base font-bold">Condiciones completas</summary><p className="mt-4 whitespace-pre-line text-base leading-7 text-[var(--marketing-text-secondary)]">{terms}</p></details>}
                        {copy.partners.length>0&&<details className="border-b border-border py-5"><summary className="cursor-pointer text-base font-bold">Quién lo hace posible</summary><div className="mt-4 space-y-4">{copy.partners.map(partner=><p key={`${partner.slug}-${partner.role}`} className="text-base leading-6"><span className="block text-sm text-[var(--marketing-text-secondary)]">{partner.role==='organizer'?'Organiza':'Partner · '+partner.role}</span><Link href={`/partners/${partner.slug}`} className="inline-flex min-h-11 items-center font-bold underline">{partner.name}</Link></p>)}<p className="text-base leading-6 text-[var(--marketing-text-secondary)]">JAKAWI conecta esta experiencia contigo.</p></div></details>}
                        <Link href={nativeUrl} className="mt-5 inline-flex min-h-11 items-center text-base font-bold underline">Ver ficha, fechas y reserva</Link>
                    </div>
                </section>
                <div className="experience-faq border-t border-border"><MarketingFAQ items={copy.faq}/></div>
            </div>
            <section data-marketing-surface="dark" aria-label="CTA final" className="bg-[var(--ink)] py-14 text-[var(--cream)] sm:py-20"><div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12"><h2 className="max-w-4xl text-4xl leading-[1.02] font-extrabold tracking-tight uppercase sm:text-6xl">{sunset?'Haz del atardecer tu próximo plan.':copy.finalHeadline}</h2><p className="mt-5 max-w-xl text-base leading-7 text-[var(--marketing-text-secondary)]">{sunset?'Consulta la ficha y continúa tu reserva con el partner.':copy.title}</p><div className="mt-8"><MarketingCTA action={action} className="text-base"/></div></div></section>
        </main>
    </>;
}
