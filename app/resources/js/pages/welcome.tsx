import { Head, Link, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { CitySelector } from '@/components/city-selector';
import { HeroOpportunity } from '@/components/discovery/hero-opportunity';
import { OpportunityCard } from '@/components/discovery/opportunity-card';
import ThemeToggle from '@/components/theme-toggle';
import type { DiscoveryOpportunity, HomeDiscovery } from '@/types/discovery';

type MembershipSummary = { confirmed_savings: string; remaining_to_payback: string; has_paid_for_itself: boolean };
type HomeProps = { discovery: HomeDiscovery; challenges?: DiscoveryOpportunity[]; myJakawi?: MembershipSummary | null; categories?: string[] };

const categoryLabels: Record<string, string> = { food: 'Comer', cafe: 'Café', fitness: 'Entrenar', wellness: 'Bienestar', beauty: 'Belleza', entertainment: 'Entretenimiento', nightlife: 'Salir', shopping: 'Comprar', services: 'Servicios', experiences: 'Experiencias' };

function greeting(name?: string) { return name ? `Hola, ${name.split(' ')[0]}` : 'Hola'; }

function SearchDiscovery() {
    const [query, setQuery] = useState('');

    function submit(event: FormEvent<HTMLFormElement>) {
        if (!query.trim()) return;
        event.preventDefault();
        window.location.assign(`/explorar?q=${encodeURIComponent(query.trim())}`);
    }

    return <form action="/explorar" method="get" onSubmit={submit} className="relative mt-7 max-w-3xl">
        <Search className="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <input name="q" value={query} onChange={event => setQuery(event.target.value)} placeholder="Buscar café, planes, lugares..." className="min-h-13 w-full rounded-[var(--radius-control)] bg-surface-elevated py-3 pr-12 pl-12 text-base font-medium text-foreground shadow-[0_5px_18px_color-mix(in_srgb,var(--foreground)_6%,transparent)] outline-none transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-brand" />
        {query ? <button type="button" onClick={() => setQuery('')} className="absolute right-3 top-1/2 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground transition hover:bg-surface-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-brand" aria-label="Limpiar búsqueda"><X className="size-4" /></button> : null}
    </form>;
}

function ForYou({ opportunities }: { opportunities: DiscoveryOpportunity[] }) {
    if (!opportunities.length) return null;
    return <section className="mt-[var(--space-section)]" aria-labelledby="section-FOR_YOU">
        <div className="max-w-xl"><p className="discovery-eyebrow text-brand">SELECCIÓN PARA TI</p><h2 id="section-FOR_YOU" className="mt-1 text-[1.45rem] leading-tight font-extrabold tracking-[-0.025em] sm:text-2xl">PARA TI</h2><p className="mt-1 text-[0.9375rem] leading-relaxed font-medium text-muted-foreground">Una selección para aprovechar mejor tu ciudad.</p></div>
        <div className="mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 pr-4 [scrollbar-width:none] sm:grid sm:max-w-4xl sm:grid-cols-2 sm:overflow-visible sm:pr-0">
            {opportunities.map((opportunity, position) => <div key={`${opportunity.type}-${opportunity.source_id}`} className="snap-start"><OpportunityCard opportunity={opportunity} section="FOR_YOU" position={position} /></div>)}
        </div>
    </section>;
}

function HappeningNow({ opportunities }: { opportunities: DiscoveryOpportunity[] }) {
    if (!opportunities.length) return null;
    return <section className="mt-10 rounded-[var(--radius-card)] bg-surface-muted px-4 py-5 sm:mt-12 sm:px-6 sm:py-7" aria-labelledby="section-HAPPENING_NOW">
        <div className="flex items-end justify-between gap-4"><div><p className="discovery-eyebrow text-brand">AHORA</p><h2 id="section-HAPPENING_NOW" className="mt-1 text-[1.45rem] leading-tight font-extrabold tracking-[-0.025em] sm:text-2xl">ESTÁ PASANDO AHORA</h2></div><span className="mb-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand" aria-label="Oportunidades vigentes" /></div>
        <div className="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 pr-4 [scrollbar-width:none] sm:grid sm:grid-cols-2 sm:overflow-visible sm:pr-0 lg:grid-cols-4">
            {opportunities.map((opportunity, position) => <div key={`${opportunity.type}-${opportunity.source_id}`} className="snap-start"><OpportunityCard opportunity={opportunity} section="HAPPENING_NOW" position={position} compact /></div>)}
        </div>
    </section>;
}

function DiscoverMore({ opportunities }: { opportunities: DiscoveryOpportunity[] }) {
    if (!opportunities.length) return null;
    return <section className="mt-[var(--space-section)]" aria-labelledby="section-DISCOVER_MORE">
        <div className="flex items-end justify-between gap-4"><div><p className="discovery-eyebrow text-muted-foreground">MÁS CIUDAD</p><h2 id="section-DISCOVER_MORE" className="mt-1 text-[1.45rem] leading-tight font-extrabold tracking-[-0.025em] sm:text-2xl">SIGUE DESCUBRIENDO</h2></div><Link href="/explorar" className="mb-1 shrink-0 text-sm font-extrabold text-muted-foreground underline decoration-brand/60 decoration-2 underline-offset-4 transition hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Explorar todo</Link></div>
        <div className="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-3 pr-4 [scrollbar-width:none] sm:grid sm:grid-cols-2 sm:overflow-visible sm:pr-0 lg:grid-cols-3">
            {opportunities.map((opportunity, position) => <div key={`${opportunity.type}-${opportunity.source_id}`} className="snap-start"><OpportunityCard opportunity={opportunity} section="DISCOVER_MORE" position={position} compact /></div>)}
        </div>
    </section>;
}

function MyJakawi({ summary }: { summary: MembershipSummary }) {
    const paid = summary.has_paid_for_itself;
    return <section className="mt-[var(--space-section)] rounded-[var(--radius-card)] border border-border bg-success-surface p-5 sm:p-6" aria-labelledby="my-jakawi-title"><p id="my-jakawi-title" className="discovery-eyebrow text-muted-foreground">TU JAKAWI · VALOR RECIBIDO</p><p className="mt-2 max-w-2xl text-xl leading-tight font-extrabold tracking-[-0.025em]">{paid ? 'Tu JAKAWI ya se pagó solo' : `Te faltan Bs ${summary.remaining_to_payback} para que tu JAKAWI se pague solo`}</p><p className="mt-2 text-sm font-semibold text-muted-foreground">Bs {summary.confirmed_savings} ahorrados</p></section>;
}

export default function Welcome({ discovery, challenges = [], myJakawi, categories = [] }: HomeProps) {
    const { auth } = usePage().props;
    const visibleCategories = categories.slice(0, 6);

    return <><Head title="JAKAWI" /><main className="min-h-screen overflow-x-hidden bg-background pb-28 font-discovery text-foreground"><section className="mx-auto w-full max-w-6xl px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 lg:px-8 lg:pt-10"><header><div className="flex items-center justify-between gap-4"><CitySelector /><ThemeToggle /></div><p className="mt-6 text-base font-medium text-muted-foreground">{greeting(auth.user?.name)}</p><h1 className="mt-1 max-w-2xl text-[2rem] leading-[1.02] font-extrabold tracking-[-0.05em] sm:text-5xl">¿QUÉ HACEMOS HOY?</h1><SearchDiscovery /></header>{visibleCategories.length ? <nav aria-label="Categorías para explorar" className="mt-5 -mr-4 flex gap-2 overflow-x-auto pb-2 pr-4 [scrollbar-width:none] sm:mr-0 sm:pr-0">{visibleCategories.map(category => <Link key={category} href={`/explorar?category=${encodeURIComponent(category)}`} className="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-chip)] bg-surface-muted px-4 text-sm font-bold transition hover:bg-brand-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">{categoryLabels[category] ?? category}</Link>)}<Link href="/explorar" className="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-chip)] px-2 text-sm font-bold text-muted-foreground underline decoration-brand/60 decoration-2 underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Ver todo</Link></nav> : null}</section>{discovery.hero ? <section className="mt-5 sm:mt-7" aria-label="Oportunidad destacada"><div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8"><HeroOpportunity opportunity={discovery.hero} /></div></section> : null}<section className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8"><ForYou opportunities={discovery.forYou} /><HappeningNow opportunities={discovery.happeningNow} />{challenges.length > 0 ? <section className="mt-10" aria-label="Retos para ti"><h2 className="text-2xl font-extrabold">RETOS PARA TI</h2><div className="mt-4 flex gap-4 overflow-x-auto pb-3 sm:grid sm:grid-cols-2">{challenges.map((item, position) => <OpportunityCard key={item.source_id} opportunity={item} section="FOR_YOU" position={position} />)}</div></section> : null}{myJakawi ? <MyJakawi summary={myJakawi} /> : null}<DiscoverMore opportunities={discovery.discoverMore} /></section></main></>;
}
