import { router, usePage } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';
import { useState } from 'react';

type City = { id: number; name: string; slug: string; status: string };

export function CitySelector() {
    const { selectedCity, availableCities } = usePage<{ selectedCity: City; availableCities: City[] }>().props;
    const [open, setOpen] = useState(false);
    const active = availableCities.filter(city => city.status === 'ACTIVE');
    const upcoming = availableCities.filter(city => city.status !== 'ACTIVE');
    const select = (city: City) => {
        if (city.status !== 'ACTIVE') return;
        router.post(`/ciudades/${city.slug}/seleccionar`, { return_to: window.location.pathname + window.location.search }, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return <div className="relative"><button type="button" onClick={() => setOpen(value => !value)} aria-expanded={open} className="inline-flex min-h-10 items-center gap-1.5 text-sm font-bold tracking-[0.12em] text-brand uppercase"><MapPin className="size-4" />{selectedCity.name}<ChevronDown className="size-4" /></button>{open ? <div className="absolute z-20 mt-2 w-64 overflow-hidden rounded-2xl border border-border bg-surface p-2 shadow-lg"><p className="px-3 pt-2 pb-1 text-[11px] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">Tu ciudad</p>{active.map(city => <button key={city.slug} type="button" onClick={() => select(city)} className={`flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-semibold ${city.slug === selectedCity.slug ? 'bg-brand-subtle text-foreground' : 'hover:bg-surface-muted'}`}>{city.name}{city.slug === selectedCity.slug ? <span className="text-xs text-brand">Actual</span> : null}</button>)}{upcoming.length ? <><p className="px-3 pt-4 pb-1 text-[11px] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">Próximamente</p>{upcoming.map(city => <div key={city.slug} className="flex items-center justify-between px-3 py-2.5 text-sm text-muted-foreground"><span>{city.name}</span><span className="text-[10px] font-extrabold tracking-[0.08em] uppercase">{city.status === 'UNLOCKING' ? 'Desbloqueando' : 'Próximamente'}</span></div>)}</> : null}</div> : null}</div>;
}
