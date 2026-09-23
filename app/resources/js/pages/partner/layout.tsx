import { Head, Link } from '@inertiajs/react';

type Partner = { name: string; slug: string };

export default function PartnerLayout({ title, partner, children }: { title: string; partner: Partner; children: React.ReactNode }) {
    const base = `/partner/${partner.slug}`;
    return <main className="min-h-screen bg-background p-5 text-foreground"><Head title={`${partner.name} · ${title}`} /><div className="mx-auto flex w-full max-w-5xl gap-8"><aside className="w-52 shrink-0 border-r border-border pr-5 text-sm"><p className="font-semibold">{partner.name}</p><p className="mt-1 text-xs text-muted-foreground">Portal Partner</p><nav className="mt-6 flex flex-col gap-3"><Link href={base}>Portal Partner / Inicio</Link><Link href={`${base}/reservas`}>Reservas</Link><Link href={`${base}/validar`}>Validar</Link><Link href="/">Ver JAKAWI</Link><Link href="/logout" method="post" as="button" className="text-left">Cerrar sesión</Link></nav></aside><section className="min-w-0 flex-1"><p className="text-sm font-medium text-muted-foreground">{partner.name}</p><h1 className="mt-1 text-3xl font-semibold">{title}</h1><div className="mt-6">{children}</div></section></div></main>;
}
