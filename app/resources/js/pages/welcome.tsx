import { Head, Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { CitySelector } from '@/components/city-selector';
import { HeroOpportunity } from '@/components/discovery/hero-opportunity';
import { OpportunityCard } from '@/components/discovery/opportunity-card';
import ThemeToggle from '@/components/theme-toggle';
import type { DiscoveryOpportunity, HomeDiscovery } from '@/types/discovery';

type MembershipSummary = { confirmed_savings: string; remaining_to_payback: string; has_paid_for_itself: boolean };
type HomeProps = { discovery: HomeDiscovery; myJakawi?: MembershipSummary | null; categories?: string[] };
type HomeSection = 'FOR_YOU' | 'HAPPENING_NOW' | 'DISCOVER_MORE';

const categoryLabels: Record<string, string> = { food: 'Comer', cafe: 'Café', fitness: 'Entrenar', wellness: 'Bienestar', beauty: 'Belleza', entertainment: 'Entretenimiento', nightlife: 'Salir', shopping: 'Comprar', services: 'Servicios', experiences: 'Experiencias' };

function greeting(name?: string) { return name ? `Hola, ${name.split(' ')[0]}` : 'Hola'; }

function OpportunitySection({ title, subtitle, opportunities, section, compact = false }: { title: string; subtitle?: string; opportunities: DiscoveryOpportunity[]; section: HomeSection; compact?: boolean }) {
    if (!opportunities.length) return null;
    return <section className="mt-[var(--space-section)]" aria-labelledby={`section-${section}`}><div><h2 id={`section-${section}`} className="text-2xl leading-tight font-extrabold tracking-tight">{title}</h2>{subtitle ? <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p> : null}</div><div className={`mt-4 flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] sm:grid sm:overflow-visible ${compact ? 'sm:grid-cols-4' : 'sm:grid-cols-3'}`}>{opportunities.map((opportunity, position) => <div key={`${opportunity.type}-${opportunity.source_id}`} className="snap-start"><OpportunityCard opportunity={opportunity} section={section} position={position} compact={compact} /></div>)}</div></section>;
}

function MyJakawi({ summary }: { summary: MembershipSummary }) {
    const paid = summary.has_paid_for_itself;
    return <section className={`mt-[var(--space-section)] overflow-hidden rounded-[var(--radius-card)] p-5 ${paid ? 'bg-success text-success-foreground' : 'bg-surface-muted text-foreground'}`} aria-labelledby="my-jakawi-title"><p id="my-jakawi-title" className="discovery-eyebrow opacity-70">TU JAKAWI</p><p className="mt-2 text-xl leading-tight font-extrabold">{paid ? 'Tu JAKAWI ya se pagó solo' : `Te faltan Bs ${summary.remaining_to_payback} para que tu JAKAWI se pague solo`}</p><p className="mt-2 text-sm font-semibold opacity-80">Bs {summary.confirmed_savings} ahorrados</p></section>;
}

export default function Welcome({ discovery, myJakawi, categories = [] }: HomeProps) {
    const { auth } = usePage().props;
    const visibleCategories = categories.slice(0, 6);
    return <><Head title="JAKAWI" /><main className="min-h-screen overflow-x-hidden bg-background pb-28 font-discovery text-foreground"><section className="mx-auto w-full max-w-5xl px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 lg:pt-10"><header><div className="flex items-center justify-between gap-4"><CitySelector /><ThemeToggle /></div><p className="mt-6 text-base font-medium text-muted-foreground">{greeting(auth.user?.name)}</p><h1 className="mt-1 max-w-2xl text-[2.15rem] leading-[.98] font-extrabold tracking-[-0.045em] sm:text-5xl">¿QUÉ HACEMOS HOY?</h1><Link href="/explorar" className="relative mt-7 flex min-h-13 items-center rounded-[var(--radius-control)] bg-surface-elevated py-3 pr-4 pl-12 text-base text-muted-foreground shadow-[0_5px_18px_color-mix(in_srgb,var(--foreground)_6%,transparent)] transition hover:bg-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"><Search className="absolute left-4 size-5" aria-hidden="true" />Buscar café, planes, lugares...</Link></header>{visibleCategories.length ? <nav aria-label="Categorías para explorar" className="mt-5 -mr-4 flex gap-2 overflow-x-auto pb-2 pr-4 [scrollbar-width:none] sm:mr-0 sm:pr-0">{visibleCategories.map(category => <Link key={category} href={`/explorar?category=${encodeURIComponent(category)}`} className="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-chip)] border border-border bg-surface px-4 text-sm font-bold transition hover:border-brand hover:bg-brand-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">{categoryLabels[category] ?? category}</Link>)}<Link href="/explorar" className="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-chip)] px-2 text-sm font-extrabold text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Ver todo</Link></nav> : null}{discovery.hero ? <HeroOpportunity opportunity={discovery.hero} /> : null}<OpportunitySection title="PARA TI" subtitle="Oportunidades para disfrutar más tu ciudad." opportunities={discovery.forYou} section="FOR_YOU" /><OpportunitySection title="ESTÁ PASANDO AHORA" opportunities={discovery.happeningNow} section="HAPPENING_NOW" compact />{myJakawi ? <MyJakawi summary={myJakawi} /> : null}<OpportunitySection title="SIGUE DESCUBRIENDO" opportunities={discovery.discoverMore} section="DISCOVER_MORE" compact /></section></main></>;
}
