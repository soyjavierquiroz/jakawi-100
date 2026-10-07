import { Head, Link } from '@inertiajs/react';
import JourneyLayout from '@/layouts/journey-layout';
type Challenge = { id: number; title: string; slug: string; description: string | null; ends_at: string | null; qualification_mode: string; reward_type: string };
export default function Index({ challenges }: { challenges: Challenge[] }) {
    return <JourneyLayout><Head title="Retos sociales"/><main className="mx-auto max-w-5xl px-4 py-10"><h1 className="text-4xl font-bold">Retos sociales</h1><p className="mt-2 text-muted-foreground">Participa con una publicación y sigue su verificación aquí.</p><div className="mt-8 grid gap-4 sm:grid-cols-2">{challenges.map(c=><Link key={c.id} href={`/retos/${c.slug}`} className="rounded-xl border border-border p-6 hover:border-primary"><h2 className="text-xl font-semibold">{c.title}</h2><p className="mt-2">{c.description}</p><p className="mt-4 text-sm">{c.ends_at ? `Hasta ${new Date(c.ends_at).toLocaleDateString('es-BO')}` : 'Abierto'}</p></Link>)}</div>{!challenges.length && <p className="mt-8">No hay retos abiertos para tu ciudad por ahora.</p>}</main></JourneyLayout>;
}
