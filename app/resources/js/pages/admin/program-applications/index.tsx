import { Link, router } from '@inertiajs/react';
import AdminLayout from '../layout';

type Application = { id: number; program_type: string; status: string; created_at: string; user: { name: string; email: string } };
type Props = { applications: { data: Application[]; links: { url: string | null; label: string; active: boolean }[] }; filters: { program?: string; status?: string }; programs: string[]; statuses: string[] };

export default function ProgramApplications({ applications, filters, programs, statuses }: Props) {
    return <AdminLayout title="Solicitudes de programas">
        <form className="flex flex-wrap gap-3" onSubmit={event => { event.preventDefault(); router.get('/admin/solicitudes-programas', Object.fromEntries(new FormData(event.currentTarget))); }}>
            <select name="program" defaultValue={filters.program ?? ''} className="rounded border border-border bg-background p-2"><option value="">Todos los programas</option>{programs.map(program => <option key={program} value={program}>{program}</option>)}</select>
            <select name="status" defaultValue={filters.status ?? ''} className="rounded border border-border bg-background p-2"><option value="">Todos los estados</option>{statuses.map(status => <option key={status} value={status}>{status}</option>)}</select>
            <button className="rounded bg-brand px-4 py-2 font-semibold text-brand-foreground">Filtrar</button>
        </form>
        <div className="mt-6 space-y-3">{applications.data.map(application => <Link href={`/admin/solicitudes-programas/${application.id}`} key={application.id} className="block rounded border border-border p-4 hover:bg-surface-muted"><b>{application.user.name}</b><span className="ml-3">{application.program_type} · {application.status}</span><span className="block text-sm text-foreground-soft">{application.user.email} · {application.created_at}</span></Link>)}{applications.data.length === 0 && <p>No hay solicitudes para estos filtros.</p>}</div>
        <nav className="mt-6 flex flex-wrap gap-3">{applications.links.map((link, index) => link.url ? <Link key={index} href={link.url} className={`rounded border px-3 py-2 ${link.active ? 'border-brand font-bold' : 'border-border'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : null)}</nav>
    </AdminLayout>;
}
