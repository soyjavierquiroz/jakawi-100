import { AdminStatus } from '@/components/admin-status';
import { ListFilters } from '@/components/list-filters';
import { ListPagination, type ListPage } from '@/components/list-pagination';
import { Link } from '@inertiajs/react';
import AdminLayout from '../layout';
type Item = { id: number; status: string; requested_at: string; journey_type: string | null; user: { name: string; email: string } };
export default function Index({ requests }: { requests: ListPage & { data: Item[] } }) {
    return <AdminLayout title="Solicitudes asistidas"><ListFilters /><div className="mt-7 space-y-3">{requests.data.map(item => <Link key={item.id} href={`/admin/solicitudes-membresia/${item.id}`} className="block rounded-xl border border-border bg-surface p-4"><span className="font-bold">{item.user.name}</span><span className="block text-sm text-muted-foreground">{item.user.email} · {new Date(item.requested_at).toLocaleDateString('es-BO')} · <AdminStatus status={item.status} /> · {item.journey_type ?? 'Sin producto específico'}</span></Link>)}</div><ListPagination page={requests} /></AdminLayout>;
}
