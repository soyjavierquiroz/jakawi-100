import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import BenefitCard from '@/components/benefit-card';
import { BrandEnergy } from '@/components/brand-energy';
import ExperienceCard from '@/components/experience-card';
import { JakawiImage } from '@/components/jakawi-image';
import { EmptyState } from '@/components/state-panel';
import UnlockCard, { type Unlock } from '@/components/unlock-card';
import type { BenefitSummary } from '@/types';

type Experience = { id: number; slug: string; title: string; image_url?: string | null; image_srcset?: Array<{ src: string; width: number }>; category?: string | null; sessions?: Array<{ starts_at?: string; venue_label?: string | null; location?: { name?: string } | null }> };
type Partner = { id: number; slug: string; name: string; category?: string | null; description?: string | null; cover_url?: string | null; cover_srcset?: Array<{ src: string; width: number }> };
type Result = (BenefitSummary & { result_type: 'benefit' }) | (Experience & { result_type: 'experience' }) | (Partner & { result_type: 'partner' }) | (Unlock & { result_type: 'unlock' });

const categories = [['todos', 'Todo'], ['food', 'Comer'], ['cafe', 'Café'], ['fitness', 'Entrenar'], ['nightlife', 'Salir'], ['wellness', 'Bienestar']];

function PartnerResult({ partner, priority = false }: { partner: Partner; priority?: boolean }) {
    return <Link href={`/partners/${partner.slug}`} className="group grid min-h-36 grid-cols-[38%_1fr] overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface-elevated transition active:scale-[.99]"><div className="bg-surface-muted"><JakawiImage src={partner.cover_url} srcset={partner.cover_srcset} sizes="180px" alt={partner.name} className="h-full w-full object-cover" loading={priority ? 'eager' : 'lazy'} priority={priority} /></div><div className="flex min-w-0 flex-col justify-center p-[var(--space-card)]"><p className="discovery-eyebrow text-brand">Lugar</p><h2 className="mt-1 text-xl leading-tight font-extrabold">{partner.name}</h2>{partner.category ? <p className="mt-2 text-sm font-semibold text-foreground-soft">{partner.category}</p> : null}{partner.description ? <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{partner.description}</p> : null}</div></Link>;
}

function ExploreResult({ result, priority = false }: { result: Result; priority?: boolean }) {
    switch (result.result_type) {
        case 'partner': return <PartnerResult partner={result} priority={priority} />;
        case 'benefit': return <BenefitCard benefit={result} variant="list" />;
        case 'experience': return <ExperienceCard experience={result} variant="list" />;
        case 'unlock': return <UnlockCard unlock={result} variant="list" />;
        default: return null;
    }
}

export default function Explore({ query = '', category, type, results = [] }: { query?: string; category?: string | null; type?: string | null; results?: Result[] }) {
    const { selectedCity } = usePage<{ selectedCity: { name: string } }>().props;
    const [value, setValue] = useState(query);
    const activeTypeRef = useRef<HTMLButtonElement>(null);
    const visit = (next: Record<string, string>) => router.get('/explorar', { q: value, category: category ?? 'todos', type: type ?? '', ...next }, { preserveState: true, replace: true });
    const hasFilters = value !== '' || (category && category !== 'todos') || type;

    useEffect(() => { if (type === 'places') activeTypeRef.current?.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }, [type]);

    return <><Head title="Explorar" /><main className="min-h-screen overflow-x-hidden bg-background pb-28 font-discovery text-foreground"><section className="mx-auto w-full max-w-5xl px-[var(--space-gutter)] pt-[max(2rem,env(safe-area-inset-top))] sm:px-6"><div className="flex items-center gap-3"><p className="discovery-eyebrow text-brand">Descubre</p><BrandEnergy /></div><h1 className="mt-2 text-3xl font-extrabold tracking-tight">EXPLORA {selectedCity.name.toUpperCase()}</h1><form onSubmit={event => { event.preventDefault(); visit({}); }} className="relative mt-6"><Search className="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" /><input value={value} onChange={event => setValue(event.target.value)} placeholder="¿Qué quieres hacer?" className="min-h-13 w-full rounded-[var(--radius-control)] border border-border bg-surface py-3 pr-4 pl-12 text-base outline-none focus-visible:ring-2 focus-visible:ring-brand" /></form><nav aria-label="Tipo de descubrimiento" className="mt-5 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]"><button type="button" onClick={() => visit({ type: '' })} className={`min-h-11 shrink-0 rounded-[var(--radius-chip)] border px-4 text-sm font-extrabold ${type ? 'border-border bg-surface text-muted-foreground' : 'border-brand bg-brand text-brand-foreground'}`}>Todo</button><button type="button" onClick={() => visit({ type: 'benefits' })} className={`min-h-11 shrink-0 rounded-[var(--radius-chip)] border px-4 text-sm font-extrabold ${type === 'benefits' ? 'border-brand bg-brand text-brand-foreground' : 'border-border bg-surface text-muted-foreground'}`}>Beneficios</button><button type="button" onClick={() => visit({ type: 'experiences' })} className={`min-h-11 shrink-0 rounded-[var(--radius-chip)] border px-4 text-sm font-extrabold ${type === 'experiences' ? 'border-brand bg-brand text-brand-foreground' : 'border-border bg-surface text-muted-foreground'}`}>Experiencias</button><button ref={activeTypeRef} type="button" onClick={() => visit({ type: 'places' })} className={`min-h-11 shrink-0 rounded-[var(--radius-chip)] border px-4 text-sm font-extrabold ${type === 'places' ? 'border-brand bg-brand text-brand-foreground' : 'border-border bg-surface text-muted-foreground'}`}>Lugares</button></nav><div className="mt-3 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]" aria-label="Categorías">{categories.map(([key, label]) => <button type="button" key={key} onClick={() => visit({ category: key })} className={`min-h-10 shrink-0 rounded-[var(--radius-chip)] border px-4 text-sm font-semibold ${String(category ?? 'todos') === key ? 'border-brand bg-brand-subtle text-foreground' : 'border-border bg-surface text-muted-foreground'}`}>{label}</button>)}</div>{results.length ? <section className="mt-7"><p className="text-sm font-semibold text-muted-foreground">{results.length} {results.length === 1 ? 'resultado' : 'resultados'} para descubrir</p><div className="mt-4 grid gap-4 sm:grid-cols-2">{results.map((result, index) => <ExploreResult key={`${result.result_type}-${result.id}`} result={result} priority={index < 3} />)}</div></section> : <EmptyState className="mt-12" title="NO ENCONTRAMOS ESO" description="Prueba otra búsqueda o cambia los filtros." action={hasFilters ? <button type="button" onClick={() => { setValue(''); router.get('/explorar'); }} className="discovery-primary-action">LIMPIAR FILTROS</button> : undefined} />}</section></main></>;
}
