import { Head, Link } from '@inertiajs/react';
type Item = { id: number; status: string; requested_at: string; journey_type: string | null; user: { name: string; email: string } };
export default function Index({ requests }: { requests: { data: Item[] } }) {
    return <main className="mx-auto min-h-screen max-w-5xl p-6 text-foreground"><Head title="Solicitudes de membresía" /><Link href="/admin" className="text-sm text-brand">← Admin</Link><h1 className="mt-5 text-3xl font-extrabold">Solicitudes de membresía</h1><div className="mt-7 space-y-3">{requests.data.map(item => <Link key={item.id} href={`/admin/solicitudes-membresia/${item.id}`} className="block rounded-xl border border-border bg-surface p-4"><span className="font-bold">{item.user.name}</span><span className="block text-sm text-muted-foreground">{item.user.email} · {new Date(item.requested_at).toLocaleDateString('es-BO')} · {item.status} · {item.journey_type ?? 'Sin producto específico'}</span></Link>)}</div></main>;
}
