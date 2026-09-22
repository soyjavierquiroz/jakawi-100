import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Input } from '@/components/ui/input';
import AdminLayout from '../layout';

type Redemption = {
    public_id: string;
    created_at: string;
    member: { name?: string; email?: string };
    merchant_name: string;
    benefit_title: string;
    status: string;
    savings_amount?: string | null;
    confirmed_at?: string | null;
};

export default function AdminRedemptions({ redemptions, filters }: { redemptions: { data: Redemption[] }; filters: { search?: string; status?: string } }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get('/admin/redemptions', { search, status }, { preserveState: true });
    }

    return (
        <AdminLayout>
            <Head title="Canjes" />
            <div className="space-y-4 rounded-md border border-border bg-surface p-4">
                <h1 className="text-2xl font-semibold">Canjes</h1>
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar" />
                    <select className="h-9 rounded-md border border-input bg-transparent px-3 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Todos</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button className="rounded-md bg-brand px-4 text-sm font-semibold text-brand-foreground">Filtrar</button>
                </form>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead><tr className="border-b border-border"><th className="py-2">Fecha</th><th>Member</th><th>Merchant</th><th>Benefit</th><th>Status</th><th>Ahorro</th><th>Confirmed</th></tr></thead>
                        <tbody>
                            {redemptions.data.map((redemption) => (
                                <tr key={redemption.public_id} className="border-b border-border">
                                    <td className="py-2">{new Date(redemption.created_at).toLocaleDateString('es-BO')}</td>
                                    <td>{redemption.member.name}<br /><span className="text-muted-foreground">{redemption.member.email}</span></td>
                                    <td>{redemption.merchant_name}</td>
                                    <td>{redemption.benefit_title}</td>
                                    <td>{redemption.status}</td>
                                    <td>{redemption.savings_amount ? `Bs ${Number(redemption.savings_amount).toFixed(2)}` : ''}</td>
                                    <td>{redemption.confirmed_at ? new Date(redemption.confirmed_at).toLocaleString('es-BO') : ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
