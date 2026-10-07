import { Head, Link } from '@inertiajs/react';
import JourneyLayout from '@/layouts/journey-layout';
type Challenge = { id: number; title: string; slug: string; description: string | null; ends_at: string | null; evidence_type: string; reward_type: string };
export default function Index({ challenges }: { challenges: Challenge[] }) {
    return <JourneyLayout><Head title="Retos"/><main className="mx-auto max-w-5xl px-4 py-10"><h1 className="text-4xl font-bold">Retos</h1><p className="mt-2 text-muted-foreground">Participa y sigue tu progreso aquí.</p><div className="mt-8 grid gap-4 sm:grid-cols-2">{challenges.map(c=><Link key={c.id} href={`/retos/${c.slug}`} className="rounded-xl border border-border p-6 hover:border-primary"><h2 className="text-xl font-semibold">{c.title}</h2><p className="mt-2">{c.description}</p><p className="mt-4 text-sm">{c.evidence_type === 'SOCIAL_POST' ? 'Publicación social' : 'Participación manual'} · {c.ends_at ? `Hasta ${new Date(c.ends_at).toLocaleDateString('es-BO')}` : 'Abierto'}</p></Link>)}</div>{!challenges.length && <p className="mt-8">No hay retos abiertos para tu ciudad por ahora.</p>}</main></JourneyLayout>;
}
